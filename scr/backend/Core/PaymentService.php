<?php

declare(strict_types=1);

final class PaymentService
{
    private PDO $database;
    private ?array $configuration;

    public function __construct(?PDO $database = null, ?array $configuration = null)
    {
        $this->database = $database ?? Database::connection();
        $this->configuration = $configuration;
    }

    public function settings(): array
    {
        $rows = $this->database->query(
            "SELECT setting_key, setting_value FROM settings
             WHERE setting_key LIKE 'payment\\_%' OR setting_key LIKE 'online\\_payment\\_%'
                OR setting_key LIKE 'vietqr\\_%'"
        )->fetchAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = (string) $row['setting_value'];
        }
        return $settings;
    }

    public function saveSettings(array $input, int $actorId): void
    {
        $minutes = filter_var($input['online_payment_hold_minutes'] ?? null, FILTER_VALIDATE_INT);
        if ($minutes === false || $minutes < 1 || $minutes > 1440) {
            throw new InvalidArgumentException('Thời gian giữ tồn phải từ 1 đến 1440 phút.');
        }
        $bankBin = trim((string) ($input['vietqr_bank_bin'] ?? ''));
        $accountNumber = trim((string) ($input['vietqr_account_number'] ?? ''));
        $accountName = trim((string) ($input['vietqr_account_name'] ?? ''));
        $vietqrEnabled = isset($input['payment_vietqr_enabled']) ? '1' : '0';
        if ($vietqrEnabled === '1' && (
            preg_match('/\A\d{6}\z/', $bankBin) !== 1
            || preg_match('/\A\d{6,20}\z/', $accountNumber) !== 1
            || $accountName === ''
            || mb_strlen($accountName, 'UTF-8') > 99
        )) {
            throw new InvalidArgumentException('Để bật VietQR, hãy nhập BIN 6 chữ số, số tài khoản và tên chủ tài khoản hợp lệ.');
        }
        $values = [
            'payment_cod_enabled' => isset($input['payment_cod_enabled']) ? '1' : '0',
            'payment_vietqr_enabled' => $vietqrEnabled,
            'payment_vnpay_enabled' => isset($input['payment_vnpay_enabled']) ? '1' : '0',
            'online_payment_hold_minutes' => (string) $minutes,
            'vietqr_bank_bin' => $bankBin,
            'vietqr_account_number' => $accountNumber,
            'vietqr_account_name' => $accountName,
        ];
        $this->database->beginTransaction();
        try {
            $statement = $this->database->prepare(
                'INSERT INTO settings (setting_key, setting_value, description)
                 VALUES (:setting_key, :setting_value, :description)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            );
            foreach ($values as $key => $value) {
                $statement->execute([
                    'setting_key' => $key,
                    'setting_value' => $value,
                    'description' => $key,
                ]);
            }
            AuditLog::record($actorId, 'payment_settings_updated', 'settings', null, 'Cập nhật phương thức thanh toán', $this->database);
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function enabledMethods(): array
    {
        $settings = $this->settings();
        $methods = [];
        if (($settings['payment_cod_enabled'] ?? '1') === '1') {
            $methods['cod'] = 'Thanh toán khi nhận hàng (COD)';
        }
        if (($settings['payment_vietqr_enabled'] ?? '1') === '1'
            && $this->bankSettingsConfigured($settings)) {
            $methods['bank_transfer'] = 'Chuyển khoản VietQR';
        }
        if (($settings['payment_vnpay_enabled'] ?? '0') === '1' && $this->vnpayConfigured()) {
            $methods['vnpay'] = 'VNPay (sandbox)';
        }
        return $methods;
    }

    public function holdMinutes(): int
    {
        $settings = $this->settings();
        $minutes = filter_var($settings['online_payment_hold_minutes'] ?? '30', FILTER_VALIDATE_INT);
        return $minutes !== false && $minutes >= 1 && $minutes <= 1440 ? $minutes : 30;
    }

    public function expirePending(): int
    {
        $ids = $this->database->query(
            "SELECT o.id FROM orders o
             WHERE o.status = 'pending' AND o.payment_status = 'pending'
               AND EXISTS (
                   SELECT 1 FROM payments p
                   WHERE p.order_id = o.id AND p.method IN ('bank_transfer','vnpay') AND p.status IN ('pending','failed')
               )
               AND EXISTS (SELECT 1 FROM stock_reservations sr WHERE sr.order_id = o.id)
               AND NOT EXISTS (
                   SELECT 1 FROM stock_reservations sr
                   WHERE sr.order_id = o.id AND sr.status = 'reserved' AND sr.expires_at > NOW()
               )
             ORDER BY o.id LIMIT 20"
        )->fetchAll(PDO::FETCH_COLUMN);
        $expired = 0;
        foreach ($ids as $id) {
            if ($this->expireOrder((int) $id, 'Đơn bị hủy do quá thời hạn thanh toán.')) {
                $expired++;
            }
        }
        return $expired;
    }

    public function expireForOrder(int $orderId): void
    {
        $statement = $this->database->prepare(
            "SELECT o.status, o.payment_status,
                    EXISTS (SELECT 1 FROM stock_reservations sr WHERE sr.order_id = o.id) AS has_reservation,
                    EXISTS (SELECT 1 FROM stock_reservations sr
                            WHERE sr.order_id = o.id AND sr.status = 'reserved' AND sr.expires_at > NOW()) AS has_live_reservation,
                    EXISTS (SELECT 1 FROM payments p
                            WHERE p.order_id = o.id AND p.method IN ('bank_transfer','vnpay') AND p.status IN ('pending','failed')) AS is_online
             FROM orders o WHERE o.id = :id"
        );
        $statement->execute(['id' => $orderId]);
        $order = $statement->fetch();
        if ($order && $order['status'] === 'pending' && $order['payment_status'] === 'pending'
            && (int) $order['has_reservation'] === 1 && (int) $order['has_live_reservation'] === 0
            && (int) $order['is_online'] === 1) {
            $this->expireOrder($orderId, 'Đơn bị hủy do quá thời hạn thanh toán.');
        }
    }

    public function expireOrder(int $orderId, string $reason): bool
    {
        $this->database->beginTransaction();
        try {
            $query = $this->database->prepare('SELECT * FROM orders WHERE id = :id FOR UPDATE');
            $query->execute(['id' => $orderId]);
            $order = $query->fetch();
            if (!$order || $order['status'] !== 'pending' || $order['payment_status'] !== 'pending') {
                $this->database->commit();
                return false;
            }
            $live = $this->database->prepare(
                "SELECT COUNT(*) FROM stock_reservations WHERE order_id = :id AND status = 'reserved' AND expires_at > NOW()"
            );
            $live->execute(['id' => $orderId]);
            if ((int) $live->fetchColumn() > 0) {
                $this->database->commit();
                return false;
            }
            $online = $this->database->prepare(
                "SELECT COUNT(*) FROM payments WHERE order_id = :id AND method IN ('bank_transfer','vnpay')"
            );
            $online->execute(['id' => $orderId]);
            if ((int) $online->fetchColumn() === 0) {
                $this->database->commit();
                return false;
            }

            $this->database->prepare(
                "UPDATE stock_reservations SET status = 'expired' WHERE order_id = :id AND status = 'reserved'"
            )->execute(['id' => $orderId]);
            $this->database->prepare(
                "UPDATE payments SET status = 'failed',
                    raw_response = JSON_OBJECT('reason', 'payment_expired')
                 WHERE order_id = :id AND status = 'pending'"
            )->execute(['id' => $orderId]);
            $this->database->prepare(
                "UPDATE orders SET status = 'cancelled', payment_status = 'failed' WHERE id = :id"
            )->execute(['id' => $orderId]);
            $this->reverseVoucher($orderId);
            $this->database->prepare(
                'INSERT INTO order_status_history (order_id, status, note)
                 VALUES (:id, \'cancelled\', :reason)'
            )->execute(['id' => $orderId, 'reason' => mb_substr($reason, 0, 500, 'UTF-8')]);
            AuditLog::record(null, 'online_payment_expired', 'order', $orderId, $reason, $this->database);
            $this->database->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function markSuccessful(
        int $paymentId,
        ?string $transactionRef,
        array $response,
        ?int $actorId = null,
        bool $allowFailedVnpay = true
    ): array
    {
        $this->expirePending();
        $this->database->beginTransaction();
        try {
            $lookup = $this->database->prepare('SELECT order_id FROM payments WHERE id = :id');
            $lookup->execute(['id' => $paymentId]);
            $orderId = (int) $lookup->fetchColumn();
            if ($orderId < 1) {
                throw new InvalidArgumentException('Không tìm thấy giao dịch thanh toán.');
            }
            $orderQuery = $this->database->prepare('SELECT * FROM orders WHERE id = :id FOR UPDATE');
            $orderQuery->execute(['id' => $orderId]);
            $order = $orderQuery->fetch();
            $paymentQuery = $this->database->prepare('SELECT * FROM payments WHERE id = :id FOR UPDATE');
            $paymentQuery->execute(['id' => $paymentId]);
            $payment = $paymentQuery->fetch();
            if (!$order || !$payment) {
                throw new InvalidArgumentException('Không tìm thấy đơn hàng hoặc giao dịch thanh toán.');
            }
            if ($order['payment_status'] === 'paid') {
                $this->database->commit();
                return ['order_id' => $orderId, 'already_paid' => true];
            }
            $allowedPaymentStatuses = ['pending'];
            if ($payment['method'] === 'vnpay' && $allowFailedVnpay) {
                $allowedPaymentStatuses[] = 'failed';
            }
            if (!in_array($payment['status'], $allowedPaymentStatuses, true)
                || $order['status'] !== 'pending'
                || $order['payment_status'] !== 'pending'
                || PricingService::moneyToVnd((string) $payment['amount']) !== PricingService::moneyToVnd((string) $order['total_amount'])) {
                throw new InvalidArgumentException('Giao dịch không còn hợp lệ hoặc số tiền không khớp.');
            }
            $reservation = $this->database->prepare(
                "SELECT COUNT(*) FROM stock_reservations
                 WHERE order_id = :id AND status = 'reserved' AND expires_at > NOW()"
            );
            $reservation->execute(['id' => $orderId]);
            if ((int) $reservation->fetchColumn() === 0) {
                throw new InvalidArgumentException('Thời gian giữ tồn đã hết. Đơn hàng đã được giải phóng.');
            }
            $items = $this->database->prepare(
                'SELECT product_id, SUM(quantity) AS quantity FROM order_items
                 WHERE order_id = :id AND product_id IS NOT NULL GROUP BY product_id'
            );
            $items->execute(['id' => $orderId]);
            $deductionItems = array_map(static fn (array $item): array => [
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
            ], $items->fetchAll());
            (new InventoryService($this->database))->deductForOrder($orderId, $deductionItems, $actorId);
            $sanitized = $this->sanitizeResponse($response);
            $this->database->prepare(
                "UPDATE payments SET status = 'paid', transaction_ref = COALESCE(:transaction_ref, transaction_ref),
                    paid_at = NOW(), raw_response = :response WHERE id = :id"
            )->execute([
                'transaction_ref' => $transactionRef,
                'response' => json_encode($sanitized, JSON_THROW_ON_ERROR),
                'id' => $paymentId,
            ]);
            if ($payment['method'] === 'vnpay') {
                $this->database->prepare(
                    "UPDATE payments SET status = 'failed',
                        raw_response = JSON_OBJECT('reason', 'another_vnpay_attempt_paid')
                     WHERE order_id = :order_id AND method = 'vnpay'
                       AND id <> :payment_id AND status = 'pending'"
                )->execute(['order_id' => $orderId, 'payment_id' => $paymentId]);
            }
            $this->database->prepare(
                "UPDATE orders SET payment_status = 'paid', status = 'confirmed' WHERE id = :id"
            )->execute(['id' => $orderId]);
            $this->database->prepare(
                'INSERT INTO order_status_history (order_id, status, note, changed_by)
                 VALUES (:id, \'confirmed\', :note, :actor)'
            )->execute([
                'id' => $orderId,
                'note' => 'Thanh toán trực tuyến đã được xác nhận.',
                'actor' => $actorId,
            ]);
            $isManualVnpay = ($sanitized['source'] ?? null) === 'manual_vnpay_reconciliation';
            $auditAction = $isManualVnpay ? 'vnpay_manual_reconciled' : 'payment_confirmed';
            $auditDetails = $isManualVnpay
                ? json_encode([
                    'vnp_transaction_no' => $sanitized['vnp_TransactionNo'] ?? null,
                    'reason' => $sanitized['reconciliation_reason'] ?? null,
                    'transaction_ref' => $transactionRef,
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
                : $transactionRef;
            AuditLog::record($actorId, $auditAction, 'payment', $paymentId, $auditDetails, $this->database);
            $this->database->commit();
            return ['order_id' => $orderId, 'already_paid' => false];
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function markFailed(int $paymentId, array $response): void
    {
        $this->database->prepare(
            "UPDATE payments SET status = 'failed', raw_response = :response
             WHERE id = :id AND status = 'pending'"
        )->execute([
            'response' => json_encode($this->sanitizeResponse($response), JSON_THROW_ON_ERROR),
            'id' => $paymentId,
        ]);
    }

    public function createVnpayAttempt(int $orderId, int $userId): string
    {
        $this->expireForOrder($orderId);
        $orderQuery = $this->database->prepare(
            "SELECT o.* FROM orders o WHERE o.id = :id AND o.user_id = :user_id
             AND o.status = 'pending' AND o.payment_status = 'pending'
             AND EXISTS (SELECT 1 FROM payments p WHERE p.order_id = o.id AND p.method = 'vnpay')"
        );
        $orderQuery->execute(['id' => $orderId, 'user_id' => $userId]);
        $order = $orderQuery->fetch();
        if (!$order) {
            throw new InvalidArgumentException('Đơn không còn chờ thanh toán.');
        }
        $live = $this->database->prepare(
            "SELECT UNIX_TIMESTAMP(MIN(expires_at)) FROM stock_reservations
             WHERE order_id = :id AND status = 'reserved' AND expires_at > NOW()"
        );
        $live->execute(['id' => $orderId]);
        $expiresAt = $live->fetchColumn();
        if (!$expiresAt) {
            $this->expireForOrder($orderId);
            throw new InvalidArgumentException('Thời gian giữ tồn đã hết.');
        }
        $settings = $this->vnpaySettings();
        $this->database->beginTransaction();
        try {
            $lockedOrderQuery = $this->database->prepare(
                "SELECT o.id, o.order_no, o.total_amount FROM orders o
                 WHERE o.id = :id AND o.user_id = :user_id
                   AND o.status = 'pending' AND o.payment_status = 'pending'
                 FOR UPDATE"
            );
            $lockedOrderQuery->execute(['id' => $orderId, 'user_id' => $userId]);
            $order = $lockedOrderQuery->fetch();
            if (!$order) {
                throw new InvalidArgumentException('Đơn không còn chờ thanh toán.');
            }
            $lockedReservationQuery = $this->database->prepare(
                "SELECT UNIX_TIMESTAMP(MIN(expires_at)) FROM stock_reservations
                 WHERE order_id = :id AND status = 'reserved' AND expires_at > NOW()"
            );
            $lockedReservationQuery->execute(['id' => $orderId]);
            $expiresAt = $lockedReservationQuery->fetchColumn();
            if (!$expiresAt) {
                throw new InvalidArgumentException('Thời gian giữ tồn đã hết.');
            }
            $pendingPayment = $this->database->prepare(
                "SELECT id, transaction_ref, raw_response FROM payments
                 WHERE order_id = :order_id AND method = 'vnpay' AND status = 'pending'
                 ORDER BY id DESC LIMIT 1 FOR UPDATE"
            );
            $pendingPayment->execute(['order_id' => $orderId]);
            $existingPayment = $pendingPayment->fetch();
            $timezone = new DateTimeZone('Asia/Ho_Chi_Minh');
            $now = new DateTimeImmutable('now', $timezone);
            $reference = $this->newVnpayReference($now);
            if ($existingPayment
                && $existingPayment['transaction_ref'] === null
                && $existingPayment['raw_response'] === null) {
                $this->database->prepare('UPDATE payments SET transaction_ref = :reference WHERE id = :id')
                    ->execute(['reference' => $reference, 'id' => $existingPayment['id']]);
            } else {
                $this->database->prepare(
                    "INSERT INTO payments (order_id, method, amount, status, transaction_ref)
                     VALUES (:order_id, 'vnpay', :amount, 'pending', :reference)"
                )->execute([
                    'order_id' => $orderId,
                    'amount' => $order['total_amount'],
                    'reference' => $reference,
                ]);
            }
            $amount = PricingService::moneyToVnd((string) $order['total_amount']) * 100;
            $expiresAtLocal = (new DateTimeImmutable('@' . (int) $expiresAt))->setTimezone($timezone);
            $parameters = [
                'vnp_Version' => '2.1.0',
                'vnp_Command' => 'pay',
                'vnp_TmnCode' => $settings['tmn_code'],
                'vnp_Amount' => $amount,
                'vnp_CreateDate' => $now->format('YmdHis'),
                'vnp_CurrCode' => 'VND',
                'vnp_IpAddr' => $this->clientIp(),
                'vnp_Locale' => 'vn',
                'vnp_OrderInfo' => $this->vnpayOrderInfo((string) $order['order_no']),
                'vnp_OrderType' => 'other',
                'vnp_ReturnUrl' => $settings['return_url'],
                'vnp_TxnRef' => $reference,
                'vnp_ExpireDate' => $expiresAtLocal->format('YmdHis'),
            ];
            $signatureData = VnpaySignature::signatureData($parameters);
            $signature = VnpaySignature::sign($parameters, $settings['hash_secret']);
            $attemptData = json_encode([
                'source' => 'vnpay_attempt_created',
                'parameters' => $parameters,
                'signature_data' => $signatureData,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $attemptQuery = $this->database->prepare(
                "UPDATE payments SET raw_response = :attempt_data
                 WHERE transaction_ref = :reference AND method = 'vnpay' AND status = 'pending'"
            );
            $attemptQuery->execute(['attempt_data' => $attemptData, 'reference' => $reference]);
            if ($attemptQuery->rowCount() !== 1) {
                throw new RuntimeException('Không thể lưu lần thử thanh toán VNPay.');
            }
            $this->database->commit();
            $this->logVnpaySignature($signatureData, $signature);
            return $settings['url'] . '?' . $signatureData . '&vnp_SecureHash=' . $signature;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function handleVnpay(array $parameters, string $source = 'callback'): array
    {
        if (!in_array($source, ['return', 'ipn'], true)) {
            $source = 'callback';
        }
        $receivedParameters = $parameters;
        $signature = is_scalar($parameters['vnp_SecureHash'] ?? null)
            ? (string) $parameters['vnp_SecureHash']
            : '';
        $reference = is_scalar($parameters['vnp_TxnRef'] ?? null)
            ? (string) $parameters['vnp_TxnRef']
            : '';
        $payment = null;
        if ($reference !== '' && strlen($reference) <= 150) {
            try {
                $lookup = $this->database->prepare(
                    'SELECT p.*, o.id AS matched_order_id, o.order_no, o.total_amount, o.payment_status
                     FROM payments p JOIN orders o ON o.id = p.order_id
                     WHERE p.transaction_ref = :ref AND p.method = \'vnpay\'
                     ORDER BY p.id DESC LIMIT 1'
                );
                $lookup->execute(['ref' => $reference]);
                $payment = $lookup->fetch() ?: null;
            } catch (Throwable $exception) {
                $this->logVnpayCallback($source, $receivedParameters, false, null, 'payment_lookup_failed');
                throw $exception;
            }
        }
        foreach ($parameters as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'vnp_')
                && !is_scalar($value) && $value !== null) {
                $this->logVnpayCallback($source, $receivedParameters, false, $payment, 'invalid_parameter');
                return ['code' => '97', 'message' => 'Tham số giao dịch không hợp lệ.'];
            }
        }
        unset($parameters['vnp_SecureHash'], $parameters['vnp_SecureHashType']);
        try {
            $settings = $this->vnpaySettings();
        } catch (Throwable $exception) {
            $this->logVnpayCallback($source, $receivedParameters, false, $payment, 'vnpay_configuration_error');
            throw $exception;
        }
        $signatureValid = VnpaySignature::verify($parameters, $settings['hash_secret'], $signature);
        if (!$signatureValid) {
            $this->logVnpayCallback($source, $receivedParameters, false, $payment, 'invalid_signature');
            return ['code' => '97', 'message' => 'Chữ ký không hợp lệ.'];
        }
        foreach ([
            'vnp_TmnCode',
            'vnp_TxnRef',
            'vnp_Amount',
            'vnp_ResponseCode',
            'vnp_TransactionStatus',
        ] as $requiredParameter) {
            if (!isset($parameters[$requiredParameter]) || (string) $parameters[$requiredParameter] === '') {
                $reason = 'missing_required_parameter:' . $requiredParameter;
                $this->logVnpayCallback($source, $receivedParameters, true, $payment, $reason);
                return ['code' => '97', 'message' => 'Kết quả VNPay thiếu thông tin bắt buộc (' . $requiredParameter . ').'];
            }
        }
        if ((string) ($parameters['vnp_TmnCode'] ?? '') !== $settings['tmn_code']) {
            $this->logVnpayCallback($source, $receivedParameters, true, $payment, 'tmn_code_mismatch');
            return ['code' => '97', 'message' => 'Mã website VNPay không hợp lệ.'];
        }
        if (!$payment) {
            $this->logVnpayCallback($source, $receivedParameters, true, null, 'transaction_reference_not_found');
            return ['code' => '01', 'message' => 'Không tìm thấy giao dịch.'];
        }
        $expectedPaymentAmount = PricingService::moneyToVnd((string) $payment['amount']);
        $expectedOrderAmount = PricingService::moneyToVnd((string) $payment['total_amount']);
        $mismatchReason = $expectedPaymentAmount !== $expectedOrderAmount
            ? 'stored_payment_amount_mismatch'
            : VnpaySignature::paymentMismatchReason(
                $parameters,
                $reference,
                $expectedPaymentAmount,
                $this->vnpayOrderInfo((string) $payment['order_no'])
            );
        if ($mismatchReason !== null) {
            $this->logVnpayCallback($source, $receivedParameters, true, $payment, $mismatchReason);
            return ['code' => '04', 'message' => $this->vnpayMismatchMessage($mismatchReason)];
        }
        $safe = $this->sanitizeResponse($parameters);
        if (in_array($payment['status'], ['paid', 'refunded'], true) || $payment['payment_status'] === 'paid') {
            $this->logVnpayCallback($source, $receivedParameters, true, $payment, null);
            return [
                'code' => '00',
                'message' => 'Kết quả giao dịch đã được ghi nhận trước đó.',
                'payment' => $payment,
                'success' => true,
                'already_paid' => true,
            ];
        }
        if ((string) ($parameters['vnp_ResponseCode'] ?? '') !== '00'
            || (string) ($parameters['vnp_TransactionStatus'] ?? '') !== '00') {
            $responseCode = (string) ($parameters['vnp_ResponseCode'] ?? '');
            $reason = $responseCode !== '00'
                ? 'response_code_not_00:' . $responseCode
                : 'transaction_status_not_00:' . (string) ($parameters['vnp_TransactionStatus'] ?? '');
            try {
                $this->markFailed((int) $payment['id'], $safe);
            } catch (Throwable $exception) {
                error_log('VNPay failure callback processing failed for ' . $reference . ': ' . $exception->getMessage());
                $this->logVnpayCallback($source, $receivedParameters, true, $payment, 'payment_processing_failed');
                return ['code' => '99', 'message' => 'Chưa thể xác nhận kết quả thanh toán. Vui lòng liên hệ hỗ trợ.', 'payment' => $payment, 'success' => false];
            }
            $this->logVnpayCallback($source, $receivedParameters, true, $payment, $reason);
            $message = $responseCode !== '00'
                ? 'VNPay từ chối thanh toán (mã ' . $responseCode . '). Vui lòng thử lại hoặc liên hệ hỗ trợ.'
                : 'VNPay chưa xác nhận giao dịch thành công (trạng thái '
                    . (string) ($parameters['vnp_TransactionStatus'] ?? '')
                    . '). Vui lòng thử lại hoặc liên hệ hỗ trợ.';
            return ['code' => '00', 'message' => $message, 'payment' => $payment, 'success' => false];
        }
        try {
            $result = $this->markSuccessful(
                (int) $payment['id'],
                $reference,
                $safe
            );
            $this->logVnpayCallback($source, $receivedParameters, true, $payment, null);
            return ['code' => '00', 'message' => 'Thanh toán thành công.', 'payment' => $payment, 'success' => true] + $result;
        } catch (Throwable $exception) {
            error_log('VNPay callback processing failed for ' . $reference . ': ' . $exception->getMessage());
            $this->logVnpayCallback($source, $receivedParameters, true, $payment, 'payment_processing_failed');
            return ['code' => '99', 'message' => 'Chưa thể xác nhận thanh toán. Vui lòng liên hệ hỗ trợ.', 'payment' => $payment, 'success' => false];
        }
    }

    public function saveProof(int $orderId, int $userId, array $file): void
    {
        $this->expireForOrder($orderId);
        $payment = $this->ownedPendingPayment($orderId, $userId, 'bank_transfer');
        if (!$payment) {
            throw new InvalidArgumentException('Đơn không còn chờ xác nhận chuyển khoản.');
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return;
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (int) ($file['size'] ?? 0) > 3 * 1024 * 1024) {
            throw new InvalidArgumentException('Biên lai phải là ảnh tối đa 3 MB.');
        }
        $temporaryPath = (string) ($file['tmp_name'] ?? '');
        if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
            throw new InvalidArgumentException('Không nhận được tệp biên lai tải lên hợp lệ.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime]) || @getimagesize($temporaryPath) === false) {
            throw new InvalidArgumentException('Chỉ chấp nhận ảnh JPEG, PNG hoặc WebP.');
        }
        $directory = BACKEND_PATH . '/storage/payment-proofs';
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Không thể tạo thư mục lưu biên lai.');
        }
        $filename = bin2hex(random_bytes(20)) . '.' . $extensions[$mime];
        if (!move_uploaded_file($temporaryPath, $directory . DIRECTORY_SEPARATOR . $filename)) {
            throw new RuntimeException('Không thể lưu biên lai đã tải lên.');
        }
        $relativePath = 'payment-proofs/' . $filename;
        $update = $this->database->prepare(
            "UPDATE payments SET proof_path = :path, raw_response = :response
             WHERE id = :id AND order_id = :order_id AND status = 'pending'
               AND EXISTS (
                   SELECT 1 FROM orders o
                   WHERE o.id = :order_check AND o.status = 'pending' AND o.payment_status = 'pending'
               )
               AND EXISTS (
                   SELECT 1 FROM stock_reservations sr
                   WHERE sr.order_id = :reservation_order AND sr.status = 'reserved' AND sr.expires_at > NOW()
               )"
        );
        $update->execute([
            'path' => $relativePath,
            'response' => json_encode(['proof_mime' => $mime, 'proof_uploaded_at' => date(DATE_ATOM)], JSON_THROW_ON_ERROR),
            'id' => $payment['id'],
            'order_id' => $orderId,
            'order_check' => $orderId,
            'reservation_order' => $orderId,
        ]);
        if ($update->rowCount() !== 1) {
            unlink($directory . DIRECTORY_SEPARATOR . $filename);
            throw new InvalidArgumentException('Thời gian thanh toán đã hết hoặc đơn không còn chờ xác nhận.');
        }
    }

    public function confirmBankTransfer(int $paymentId, int $actorId, int $receivedAmount): void
    {
        $query = $this->database->prepare('SELECT p.*, o.total_amount FROM payments p JOIN orders o ON o.id = p.order_id WHERE p.id = :id AND p.method = \'bank_transfer\'');
        $query->execute(['id' => $paymentId]);
        $payment = $query->fetch();
        if (!$payment || $receivedAmount !== PricingService::moneyToVnd((string) $payment['amount'])
            || PricingService::moneyToVnd((string) $payment['amount']) !== PricingService::moneyToVnd((string) $payment['total_amount'])) {
            throw new InvalidArgumentException('Số tiền nhận phải khớp chính xác với tổng tiền đơn hàng.');
        }
        $this->markSuccessful($paymentId, null, ['source' => 'manual_bank_transfer', 'received_amount' => $receivedAmount], $actorId);
    }

    public function reconcileVnpayManually(int $paymentId, int $actorId, string $transactionNo, string $reason): void
    {
        $transactionNo = trim($transactionNo);
        $reason = trim($reason);
        if (preg_match('/\A[A-Za-z0-9._-]{1,100}\z/', $transactionNo) !== 1) {
            throw new InvalidArgumentException('Nhập mã giao dịch VNPay hợp lệ.');
        }
        if ($reason === '' || mb_strlen($reason, 'UTF-8') > 500) {
            throw new InvalidArgumentException('Lý do đối soát phải có từ 1 đến 500 ký tự.');
        }
        $query = $this->database->prepare(
            "SELECT p.id, p.status, p.amount, o.status AS order_status, o.payment_status, o.total_amount
             FROM payments p JOIN orders o ON o.id = p.order_id
             WHERE p.id = :id AND p.method = 'vnpay'"
        );
        $query->execute(['id' => $paymentId]);
        $payment = $query->fetch();
        if (!$payment || $payment['status'] !== 'pending'
            || $payment['order_status'] !== 'pending'
            || $payment['payment_status'] !== 'pending') {
            throw new InvalidArgumentException('Chỉ có thể đối soát giao dịch VNPay đang chờ thanh toán.');
        }
        if (PricingService::moneyToVnd((string) $payment['amount'])
            !== PricingService::moneyToVnd((string) $payment['total_amount'])) {
            throw new InvalidArgumentException('Số tiền giao dịch không khớp tổng đơn hàng; không thể đối soát.');
        }
        $result = $this->markSuccessful(
            $paymentId,
            null,
            [
                'source' => 'manual_vnpay_reconciliation',
                'vnp_TransactionNo' => $transactionNo,
                'reconciliation_reason' => $reason,
            ],
            $actorId,
            false
        );
        if (!empty($result['already_paid'])) {
            throw new InvalidArgumentException('Giao dịch đã được ghi nhận; không thể đối soát lần nữa.');
        }
    }

    public function rejectBankTransfer(int $paymentId, int $actorId, string $reason): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Nhập lý do từ chối giao dịch.');
        }
        $this->database->beginTransaction();
        try {
            $query = $this->database->prepare(
                "SELECT p.order_id FROM payments p WHERE p.id = :id AND p.method = 'bank_transfer' FOR UPDATE"
            );
            $query->execute(['id' => $paymentId]);
            $orderId = (int) $query->fetchColumn();
            if ($orderId < 1) {
                throw new InvalidArgumentException('Không tìm thấy giao dịch chuyển khoản.');
            }
            $this->database->prepare(
                "UPDATE payments SET status = 'failed', raw_response = :response
                 WHERE id = :id AND status = 'pending'"
            )->execute([
                'response' => json_encode(['reason' => trim($reason)], JSON_THROW_ON_ERROR),
                'id' => $paymentId,
            ]);
            $this->cancelUnpaidOrder($orderId, $actorId, trim($reason));
            AuditLog::record($actorId, 'payment_rejected', 'payment', $paymentId, trim($reason), $this->database);
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function refund(int $paymentId, int $actorId, string $reason): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Nhập lý do hoàn tiền.');
        }
        $this->database->beginTransaction();
        try {
            $query = $this->database->prepare('SELECT p.*, o.status AS order_status FROM payments p JOIN orders o ON o.id = p.order_id WHERE p.id = :id FOR UPDATE');
            $query->execute(['id' => $paymentId]);
            $payment = $query->fetch();
            if (!$payment || $payment['status'] !== 'paid' || $payment['order_status'] === 'refunded') {
                throw new InvalidArgumentException('Chỉ có thể hoàn giao dịch đã thanh toán và chưa hoàn.');
            }
            $orderId = (int) $payment['order_id'];
            (new InventoryService($this->database))->restoreForOrder($orderId, $actorId);
            $this->reverseVoucher($orderId);
            $this->database->prepare("UPDATE payments SET status = 'refunded', raw_response = :response WHERE id = :id")
                ->execute([
                    'response' => json_encode(['reason' => trim($reason)], JSON_THROW_ON_ERROR),
                    'id' => $paymentId,
                ]);
            $this->database->prepare("UPDATE orders SET status = 'refunded', payment_status = 'refunded' WHERE id = :id")
                ->execute(['id' => $orderId]);
            $this->database->prepare(
                'INSERT INTO order_status_history (order_id, status, note, changed_by)
                 VALUES (:id, \'refunded\', :note, :actor)'
            )->execute(['id' => $orderId, 'note' => trim($reason), 'actor' => $actorId]);
            AuditLog::record($actorId, 'payment_refunded', 'payment', $paymentId, trim($reason), $this->database);
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function ownedPendingPayment(int $orderId, int $userId, string $method): ?array
    {
        $query = $this->database->prepare(
            "SELECT p.*, o.order_no, o.total_amount, o.user_id
             FROM payments p JOIN orders o ON o.id = p.order_id
             WHERE p.order_id = :order_id AND o.user_id = :user_id AND o.status = 'pending'
               AND o.payment_status = 'pending' AND p.method = :method AND p.status IN ('pending','failed')
             ORDER BY p.id DESC LIMIT 1"
        );
        $query->execute(['order_id' => $orderId, 'user_id' => $userId, 'method' => $method]);
        return $query->fetch() ?: null;
    }

    public function proofFile(int $paymentId): ?array
    {
        $query = $this->database->prepare('SELECT proof_path FROM payments WHERE id = :id');
        $query->execute(['id' => $paymentId]);
        $proofPath = $query->fetchColumn();
        if (!is_string($proofPath) || preg_match('/\Apayment-proofs\/[a-f0-9]{40}\.(?:jpg|png|webp)\z/', $proofPath) !== 1) {
            return null;
        }
        $fullPath = BACKEND_PATH . '/storage/' . $proofPath;
        if (!is_file($fullPath)) {
            return null;
        }
        $extension = pathinfo($fullPath, PATHINFO_EXTENSION);
        $mime = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$extension] ?? null;
        return $mime === null ? null : ['path' => $fullPath, 'mime' => $mime];
    }

    public function paymentDeadline(int $orderId): ?string
    {
        $query = $this->database->prepare(
            "SELECT MIN(expires_at) FROM stock_reservations
             WHERE order_id = :id AND status = 'reserved'"
        );
        $query->execute(['id' => $orderId]);
        $deadline = $query->fetchColumn();
        return is_string($deadline) && $deadline !== '' ? $deadline : null;
    }

    public function reverseVoucher(int $orderId): void
    {
        $query = $this->database->prepare(
            "SELECT voucher_id FROM voucher_usages WHERE order_id = :id AND status = 'used' FOR UPDATE"
        );
        $query->execute(['id' => $orderId]);
        $voucherId = $query->fetchColumn();
        if ($voucherId === false) {
            return;
        }
        $this->database->prepare(
            "UPDATE voucher_usages SET status = 'reversed', reversed_at = NOW()
             WHERE order_id = :id AND status = 'used'"
        )->execute(['id' => $orderId]);
        $this->database->prepare(
            'UPDATE vouchers SET used_count = GREATEST(used_count - 1, 0) WHERE id = :id'
        )->execute(['id' => $voucherId]);
    }

    public function sanitizeResponse(array $response): array
    {
        $allowed = [
            'source', 'reason', 'reconciliation_reason', 'received_amount', 'proof_mime', 'proof_uploaded_at',
            'vnp_ResponseCode', 'vnp_TransactionStatus', 'vnp_TransactionNo', 'vnp_BankCode', 'vnp_BankTranNo',
            'vnp_PayDate', 'vnp_TxnRef', 'vnp_Amount', 'vnp_OrderInfo',
        ];
        $safe = [];
        foreach ($allowed as $key) {
            if (isset($response[$key]) && is_scalar($response[$key])) {
                $limit = $key === 'reconciliation_reason' ? 500 : 200;
                $safe[$key] = mb_substr((string) $response[$key], 0, $limit, 'UTF-8');
            }
        }
        return $safe;
    }

    public function vnpayConfigured(): bool
    {
        $config = $this->configuration ?? require BACKEND_PATH . '/config/config.php';
        $vnpay = $config['vnpay'] ?? [];
        $url = trim((string) ($vnpay['url'] ?? ''));
        $urlParts = parse_url($url);
        return trim((string) ($vnpay['tmn_code'] ?? '')) !== ''
            && trim((string) ($vnpay['hash_secret'] ?? '')) !== ''
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && $urlParts !== false
            && strtolower((string) ($urlParts['scheme'] ?? '')) === 'https'
            && !empty($urlParts['host'])
            && !isset($urlParts['user'])
            && !isset($urlParts['pass'])
            && !isset($urlParts['query'])
            && !isset($urlParts['fragment']);
    }

    private function bankSettingsConfigured(array $settings): bool
    {
        return preg_match('/\A\d{6}\z/', $settings['vietqr_bank_bin'] ?? '') === 1
            && preg_match('/\A\d{6,20}\z/', $settings['vietqr_account_number'] ?? '') === 1
            && trim($settings['vietqr_account_name'] ?? '') !== '';
    }

    private function vnpaySettings(): array
    {
        $config = $this->configuration ?? require BACKEND_PATH . '/config/config.php';
        $settings = $config['vnpay'] ?? [];
        $settings['return_url'] = absolute_url('/thanh-toan/vnpay/return');
        $settings['ipn_url'] = absolute_url('/thanh-toan/vnpay/ipn');
        $settings['tmn_code'] = trim((string) ($settings['tmn_code'] ?? ''));
        $settings['hash_secret'] = trim((string) ($settings['hash_secret'] ?? ''));
        $settings['url'] = trim((string) ($settings['url'] ?? ''));
        $missing = [];
        foreach ([
            'tmn_code' => 'mã website (tmn_code)',
            'hash_secret' => 'khóa bí mật (hash_secret)',
            'url' => 'URL cổng thanh toán (url)',
        ] as $key => $label) {
            if ($settings[$key] === '') {
                $missing[] = $label;
            }
        }
        if ($missing !== []) {
            throw new RuntimeException('Thiếu cấu hình VNPay trong config.php: ' . implode(', ', $missing) . '.');
        }
        $urlParts = parse_url($settings['url']);
        if ($urlParts === false
            || strtolower((string) ($urlParts['scheme'] ?? '')) !== 'https'
            || empty($urlParts['host'])
            || isset($urlParts['user'])
            || isset($urlParts['pass'])
            || isset($urlParts['query'])
            || isset($urlParts['fragment'])) {
            throw new RuntimeException('Cấu hình vnpay.url phải là URL HTTPS hợp lệ, không chứa query hoặc thông tin đăng nhập.');
        }
        return $settings;
    }

    private function clientIp(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ip : '127.0.0.1';
    }

    private function vnpayOrderInfo(string $orderNumber): string
    {
        return 'Thanh toan don ' . preg_replace('/[^A-Za-z0-9 ]/', '', $orderNumber);
    }

    private function vnpayMismatchMessage(string $reason): string
    {
        $messages = [
            'transaction_reference_mismatch' => 'Mã giao dịch VNPay không khớp đơn hàng.',
            'amount_mismatch' => 'Số tiền VNPay xác nhận không khớp tổng đơn hàng.',
            'currency_mismatch' => 'Đơn vị tiền tệ VNPay không phải VND.',
            'order_information_mismatch' => 'Thông tin đơn hàng VNPay không khớp.',
            'stored_payment_amount_mismatch' => 'Số tiền lưu trong giao dịch không khớp tổng đơn hàng.',
        ];
        return ($messages[$reason] ?? 'Thông tin giao dịch VNPay không khớp (' . $reason . ').')
            . ' Đơn hàng vẫn được giữ; vui lòng thử thanh toán lại hoặc liên hệ hỗ trợ.';
    }

    private function newVnpayReference(DateTimeImmutable $now): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $reference = 'VP' . $now->format('ymdHis') . strtoupper(bin2hex(random_bytes(16)));
            $query = $this->database->prepare('SELECT 1 FROM payments WHERE transaction_ref = :reference LIMIT 1');
            $query->execute(['reference' => $reference]);
            if ($query->fetchColumn() === false) {
                return $reference;
            }
        }
        throw new RuntimeException('Không thể tạo mã giao dịch VNPay duy nhất.');
    }

    private function logVnpayCallback(
        string $source,
        array $parameters,
        bool $signatureValid,
        ?array $payment,
        ?string $reason
    ): void {
        $safeParameters = [];
        foreach ($parameters as $key => $value) {
            if (!is_string($key)
                || !str_starts_with($key, 'vnp_')
                || $key === 'vnp_SecureHash'
                || preg_match('/card|pan|otp|cvv|cvc/i', $key) === 1) {
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $safeParameters[$key] = is_string($value) ? mb_substr($value, 0, 1000, 'UTF-8') : $value;
            } else {
                $safeParameters[$key] = '[invalid type]';
            }
        }

        $rawAmount = $parameters['vnp_Amount'] ?? null;
        $entry = [
            'event' => 'vnpay_callback',
            'received_at' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format(DATE_ATOM),
            'source' => $source,
            'parameters' => $safeParameters,
            'secure_hash_valid' => $signatureValid,
            'txn_ref' => is_scalar($parameters['vnp_TxnRef'] ?? null) ? (string) $parameters['vnp_TxnRef'] : null,
            'received_amount' => is_scalar($rawAmount) ? (string) $rawAmount : null,
            'received_amount_vnd' => VnpaySignature::amountVnd($rawAmount),
            'response_code' => is_scalar($parameters['vnp_ResponseCode'] ?? null)
                ? (string) $parameters['vnp_ResponseCode']
                : null,
            'order_id' => $payment ? (string) $payment['matched_order_id'] : 'not found',
            'payment_id' => $payment ? (string) $payment['id'] : 'not found',
            'stored_payment_amount_vnd' => $payment
                ? PricingService::moneyToVnd((string) $payment['amount'])
                : null,
            'expected_order_amount_vnd' => $payment
                ? PricingService::moneyToVnd((string) $payment['total_amount'])
                : null,
            'rejection_reason' => $reason,
        ];
        $this->writeVnpayDebugEntry($entry);
    }

    private function writeVnpayDebugEntry(array $entry): void
    {
        $config = $this->configuration ?? require BACKEND_PATH . '/config/config.php';
        if (($config['app']['debug'] ?? false) !== true) {
            return;
        }
        $line = json_encode(
            $entry,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        );
        $written = file_put_contents(
            BACKEND_PATH . '/storage/logs/vnpay-debug.log',
            $line . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
        if ($written === false) {
            error_log('Unable to append VNPay diagnostics to backend/storage/logs/vnpay-debug.log.');
        }
    }

    private function logVnpaySignature(string $signatureData, string $signature): void
    {
        $config = $this->configuration ?? require BACKEND_PATH . '/config/config.php';
        if (($config['app']['debug'] ?? false) !== true) {
            return;
        }

        $this->writeVnpayDebugEntry([
            'event' => 'vnpay_request_created',
            'created_at' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format(DATE_ATOM),
            'signature_data' => $signatureData,
            'signature' => $signature,
        ]);
    }

    private function cancelUnpaidOrder(int $orderId, int $actorId, string $reason): void
    {
        $orderQuery = $this->database->prepare('SELECT * FROM orders WHERE id = :id FOR UPDATE');
        $orderQuery->execute(['id' => $orderId]);
        $order = $orderQuery->fetch();
        if (!$order || $order['payment_status'] === 'paid') {
            throw new InvalidArgumentException('Đơn hàng đã thanh toán hoặc không tồn tại.');
        }
        (new InventoryService($this->database))->restoreForOrder($orderId, $actorId);
        $this->database->prepare("UPDATE stock_reservations SET status = 'released' WHERE order_id = :id AND status = 'reserved'")
            ->execute(['id' => $orderId]);
        $this->database->prepare("UPDATE orders SET status = 'cancelled', payment_status = 'failed' WHERE id = :id")
            ->execute(['id' => $orderId]);
        $this->reverseVoucher($orderId);
        $this->database->prepare(
            'INSERT INTO order_status_history (order_id, status, note, changed_by)
             VALUES (:id, \'cancelled\', :note, :actor)'
        )->execute(['id' => $orderId, 'note' => mb_substr($reason, 0, 500, 'UTF-8'), 'actor' => $actorId]);
    }
}
