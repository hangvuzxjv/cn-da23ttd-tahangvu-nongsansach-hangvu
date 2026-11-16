<?php

declare(strict_types=1);

final class OrderService
{
    private const MAINLINE_NEXT_STATUS = [
        'pending' => 'confirmed',
        'confirmed' => 'processing',
        'processing' => 'shipping',
    ];

    public const AUTO_COMPLETION_DAYS = 3;
    public const AUTO_COMPLETION_THROTTLE_SECONDS = 600;
    public const MAX_AUTO_COMPLETIONS_PER_RUN = 50;
    public const MAX_BULK_ORDER_ADVANCES = 50;
    public const DELIVERY_FAILURE_REASONS = [
        'customer_refused' => 'Khách từ chối nhận',
        'unreachable' => 'Không liên lạc được khách',
        'wrong_address' => 'Sai hoặc không tìm thấy địa chỉ',
        'other' => 'Lý do khác',
    ];

    private const AUTO_COMPLETION_SETTING = 'orders.auto_completion_last_run_at';

    private PDO $database;
    private ?array $paymentConfiguration;

    public function __construct(?PDO $database = null, ?array $paymentConfiguration = null)
    {
        $this->database = $database ?? Database::connection();
        $this->paymentConfiguration = $paymentConfiguration;
    }

    public function place(int $userId, array $input): array
    {
        return $this->placeAttempt($userId, $input, 0);
    }

    private function placeAttempt(int $userId, array $input, int $attempt): array
    {
        $token = (string) ($input['idempotency_token'] ?? '');
        $paymentMethod = (string) ($input['payment_method'] ?? 'cod');
        if ($userId < 1 || preg_match('/\A[a-f0-9]{64}\z/', $token) !== 1) {
            throw new InvalidArgumentException('Phiên đặt hàng không hợp lệ. Hãy tải lại trang thanh toán.');
        }
        if (!in_array($paymentMethod, ['cod', 'bank_transfer', 'vnpay'], true)
            || !isset((new PaymentService($this->database, $this->paymentConfiguration))->enabledMethods()[$paymentMethod])) {
            throw new InvalidArgumentException('Phương thức thanh toán hiện không khả dụng.');
        }
        (new PaymentService($this->database, $this->paymentConfiguration))->expirePending();
        $this->database->beginTransaction();
        try {
            $existingQuery = $this->database->prepare('SELECT id, order_no FROM orders WHERE user_id = :user_id AND idempotency_token = :token FOR UPDATE');
            $existingQuery->execute(['user_id' => $userId, 'token' => $token]);
            $existing = $existingQuery->fetch();
            if ($existing) {
                $this->database->commit();
                return ['id' => (int) $existing['id'], 'order_no' => $existing['order_no'], 'existing' => true];
            }

            $cartQuery = $this->database->prepare('SELECT id FROM carts WHERE user_id = :user_id AND status = \'active\' ORDER BY id LIMIT 1 FOR UPDATE');
            $cartQuery->execute(['user_id' => $userId]);
            $cartId = $cartQuery->fetchColumn();
            if ($cartId === false) {
                throw new InventoryException('Giỏ hàng đang trống.');
            }
            $existingQuery->execute(['user_id' => $userId, 'token' => $token]);
            $existing = $existingQuery->fetch();
            if ($existing) {
                $this->database->commit();
                return ['id' => (int) $existing['id'], 'order_no' => $existing['order_no'], 'existing' => true];
            }
            $itemsQuery = $this->database->prepare(
                'SELECT ci.product_id, ci.quantity, p.name, p.sku, p.unit, p.category_id,
                        p.price, p.sale_price, p.status, p.deleted_at,
                        COALESCE(NULLIF(p.sale_price, 0), p.price) AS current_price
                 FROM cart_items ci JOIN products p ON p.id = ci.product_id
                 WHERE ci.cart_id = :cart_id ORDER BY ci.id FOR UPDATE'
            );
            $itemsQuery->execute(['cart_id' => $cartId]);
            $items = $itemsQuery->fetchAll();
            if (!$items) {
                throw new InventoryException('Giỏ hàng đang trống.');
            }
            $maxQuantity = $this->maxCartQuantity();
            foreach ($items as $item) {
                if ($item['status'] !== 'active' || $item['deleted_at'] !== null) {
                    throw new InventoryException('Sản phẩm "' . $item['name'] . '" hiện không còn được bán. Hãy xóa sản phẩm này khỏi giỏ.');
                }
                if ((int) $item['quantity'] < 1 || (int) $item['quantity'] > $maxQuantity) {
                    throw new InventoryException('Số lượng "' . $item['name'] . '" vượt giới hạn mỗi dòng là ' . $maxQuantity . '.');
                }
            }

            $address = $this->resolveAddress($userId, $input);
            $pricingItems = array_map(static fn (array $item): array => [
                'product_id' => (int) $item['product_id'],
                'category_id' => (int) $item['category_id'],
                'quantity' => (int) $item['quantity'],
                'unit_price' => (string) $item['current_price'],
            ], $items);
            $voucherCode = trim((string) ($input['voucher_code'] ?? ''));
            $quote = (new PricingService($this->database))->quote(
                $pricingItems,
                (int) $address['region_id'],
                $voucherCode !== '' ? $voucherCode : null,
                $userId,
                true
            );
            $orderNo = $this->nextOrderNumber();
            $onlinePayment = $paymentMethod !== 'cod';
            $this->database->prepare(
                'INSERT INTO orders
                    (user_id, order_no, status, payment_status, recipient_name, recipient_phone,
                     shipping_address, region_name, region_id, subtotal, shipping_fee, discount_amount,
                     total_amount, voucher_code, customer_note, idempotency_token, placed_at)
                 VALUES
                    (:user_id, :order_no, \'pending\', :payment_status, :name, :phone, :address,
                     :region_name, :region_id, :subtotal, :shipping_fee, :discount, :total,
                     :voucher_code, :note, :token, NOW())'
            )->execute([
                'user_id' => $userId,
                'order_no' => $orderNo,
                'payment_status' => $onlinePayment ? 'pending' : 'unpaid',
                'name' => $address['recipient_name'],
                'phone' => $address['phone'],
                'address' => $address['full_address'],
                'region_name' => $address['region_name'],
                'region_id' => $address['region_id'],
                'subtotal' => $this->decimal($quote['subtotal']),
                'shipping_fee' => $this->decimal($quote['shipping_fee']),
                'discount' => $this->decimal($quote['discount']),
                'total' => $this->decimal($quote['total']),
                'voucher_code' => $quote['voucher']['code'] ?? null,
                'note' => trim((string) ($input['customer_note'] ?? '')) ?: null,
                'token' => $token,
            ]);
            $orderId = (int) $this->database->lastInsertId();

            $inventory = new InventoryService($this->database);
            if ($onlinePayment) {
                $holdMinutes = (new PaymentService($this->database, $this->paymentConfiguration))->holdMinutes();
                $allocations = $inventory->reserveForOrder(
                    $orderId,
                    $pricingItems,
                    (new DateTimeImmutable())->modify('+' . $holdMinutes . ' minutes')
                );
            } else {
                $allocations = $inventory->deductForOrder($orderId, $pricingItems, $userId);
            }
            $itemByProduct = [];
            foreach ($items as $item) {
                $itemByProduct[(int) $item['product_id']] = $item;
            }
            foreach ($quote['items'] as $pricedItem) {
                $itemByProduct[(int) $pricedItem['product_id']] = array_replace(
                    $itemByProduct[(int) $pricedItem['product_id']],
                    $pricedItem
                );
            }
            $insertItem = $this->database->prepare(
                'INSERT INTO order_items
                    (order_id, product_id, flash_sale_item_id, batch_id, product_name, sku, unit,
                     unit_price, quantity, line_total)
                 VALUES (:order_id, :product_id, :flash_sale_item_id, :batch_id, :name, :sku, :unit,
                         :price, :quantity, :line_total)'
            );
            foreach ($allocations as $allocation) {
                $item = $itemByProduct[(int) $allocation['product_id']];
                $price = PricingService::moneyToVnd((string) $item['current_price']);
                $lineTotal = PricingService::lineTotal((string) $price, (int) $allocation['quantity']);
                $insertItem->execute([
                    'order_id' => $orderId,
                    'product_id' => $allocation['product_id'],
                    'flash_sale_item_id' => $item['flash_sale_item_id'] ?? null,
                    'batch_id' => $allocation['batch_id'],
                    'name' => $item['name'],
                    'sku' => $item['sku'],
                    'unit' => $item['unit'],
                    'price' => $this->decimal($price),
                    'quantity' => $allocation['quantity'],
                    'line_total' => $this->decimal($lineTotal),
                ]);
            }
            (new FlashSaleService($this->database))->recordOrderPurchases(
                $orderId,
                $userId,
                $quote['items'],
                $onlinePayment
            );

            if ($quote['voucher']) {
                $updateVoucher = $this->database->prepare(
                    'UPDATE vouchers SET used_count = used_count + 1
                     WHERE id = :id AND status = \'active\'
                       AND (usage_limit IS NULL OR used_count < usage_limit)'
                );
                $updateVoucher->execute(['id' => $quote['voucher']['id']]);
                if ($updateVoucher->rowCount() !== 1) {
                    throw new PricingException('Mã giảm giá vừa hết lượt sử dụng. Hãy thử đặt hàng lại mà không dùng mã.');
                }
                $this->database->prepare(
                    'INSERT INTO voucher_usages (voucher_id, user_id, order_id, discount_amount, status)
                     VALUES (:voucher_id, :user_id, :order_id, :discount, \'used\')'
                )->execute([
                    'voucher_id' => $quote['voucher']['id'],
                    'user_id' => $userId,
                    'order_id' => $orderId,
                    'discount' => $this->decimal($quote['discount']),
                ]);
            }

            $this->database->prepare(
                'INSERT INTO payments (order_id, method, amount, status, transaction_ref)
                 VALUES (:id, :method, :amount, \'pending\', :transaction_ref)'
            )->execute([
                'id' => $orderId,
                'method' => $paymentMethod,
                'amount' => $this->decimal($quote['total']),
                'transaction_ref' => null,
            ]);
            $paymentId = (int) $this->database->lastInsertId();
            $historyNote = $onlinePayment
                ? 'Đơn được tạo; chờ xác nhận thanh toán.'
                : 'Đơn hàng được tạo; chờ xác nhận.';
            $this->database->prepare(
                'INSERT INTO order_status_history (order_id, status, note, changed_by)
                 VALUES (:id, \'pending\', :note, :user_id)'
            )->execute(['id' => $orderId, 'note' => $historyNote, 'user_id' => $userId]);
            $historyId = (int) $this->database->lastInsertId();
            $this->database->prepare(
                'INSERT IGNORE INTO notifications
                    (user_id, title, message, link_url, source_type, source_id)
                 VALUES (:user_id, :title, :message, :link, \'order_status\', :source_id)'
            )->execute([
                'user_id' => $userId,
                'title' => 'Đặt hàng thành công',
                'message' => $onlinePayment
                    ? 'Đơn hàng ' . $orderNo . ' đang chờ thanh toán.'
                    : 'Đơn hàng ' . $orderNo . ' đã được tiếp nhận.',
                'link' => '/tai-khoan/don-hang/' . $orderId,
                'source_id' => $historyId,
            ]);
            $this->database->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id')->execute(['cart_id' => $cartId]);
            AuditLog::record($userId, 'order_placed', 'order', $orderId, $orderNo, $this->database);
            $this->database->commit();
            return [
                'id' => $orderId,
                'order_no' => $orderNo,
                'payment_id' => $paymentId,
                'payment_method' => $paymentMethod,
                'transaction_ref' => null,
                'existing' => false,
                'email' => $this->emailForUser($userId),
            ];
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            $mysqlError = $exception instanceof PDOException ? (int) ($exception->errorInfo[1] ?? 0) : 0;
            if ($exception instanceof PDOException && $attempt < 2
                && ($exception->getCode() === '40001' || in_array($mysqlError, [1205, 1213], true))) {
                usleep(random_int(10000, 40000) * ($attempt + 1));
                return $this->placeAttempt($userId, $input, $attempt + 1);
            }
            if ($exception instanceof PDOException && $exception->getCode() === '23000') {
                $existingQuery = $this->database->prepare('SELECT id, order_no FROM orders WHERE user_id = :user_id AND idempotency_token = :token');
                $existingQuery->execute(['user_id' => $userId, 'token' => $token]);
                $existing = $existingQuery->fetch();
                if ($existing) {
                    return ['id' => (int) $existing['id'], 'order_no' => $existing['order_no'], 'existing' => true, 'email' => $this->emailForUser($userId)];
                }
            }
            throw $exception;
        }
    }

    public function cancel(int $orderId, int $userId, string $reason, bool $admin = false, bool $refund = false): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Vui lòng nhập lý do hủy/hoàn trả.');
        }
        $this->database->beginTransaction();
        try {
            $query = $this->database->prepare('SELECT * FROM orders WHERE id = :id AND (:admin = 1 OR user_id = :user_id) FOR UPDATE');
            $query->execute(['id' => $orderId, 'admin' => $admin ? 1 : 0, 'user_id' => $userId]);
            $order = $query->fetch();
            if (!$order || ($admin ? !in_array($order['status'], ['pending', 'confirmed', 'processing', 'shipping', 'completed'], true) : $order['status'] !== 'pending')) {
                throw new InventoryException($admin
                    ? 'Đơn hàng không tồn tại hoặc không thể hủy/hoàn ở trạng thái này.'
                    : 'Chỉ có thể hủy đơn hàng đang chờ xác nhận.');
            }
            $this->restoreOrderResources($orderId, $userId);
            $nextStatus = $refund ? 'refunded' : 'cancelled';
            $nextPayment = $order['payment_status'] === 'paid'
                ? 'refunded'
                : ($order['payment_status'] === 'pending' ? 'failed' : $order['payment_status']);
            $this->database->prepare('UPDATE orders SET status = :status, payment_status = :payment WHERE id = :id')
                ->execute(['status' => $nextStatus, 'payment' => $nextPayment, 'id' => $orderId]);
            $this->database->prepare(
                'INSERT INTO order_status_history (order_id, status, note, changed_by) VALUES (:id, :status, :note, :user_id)'
            )->execute([
                'id' => $orderId,
                'status' => $nextStatus,
                'note' => trim($reason),
                'user_id' => $userId,
            ]);
            (new NotificationService($this->database))->notifyOrderStatus(
                (int) $order['user_id'],
                $orderId,
                (int) $this->database->lastInsertId(),
                (string) $order['order_no'],
                $nextStatus
            );
            $this->database->prepare(
                "UPDATE payments SET status = CASE WHEN :refunded = 1 AND status = 'paid' THEN 'refunded'
                                                   WHEN status = 'pending' THEN 'failed' ELSE status END
                 WHERE order_id = :id"
            )->execute(['refunded' => $refund ? 1 : 0, 'id' => $orderId]);
            if ($nextPayment === 'refunded') {
                $this->database->prepare(
                    'UPDATE payments SET status = \'refunded\', transaction_ref = :reason WHERE order_id = :id AND status = \'paid\''
                )->execute(['reason' => mb_substr(trim($reason), 0, 150, 'UTF-8'), 'id' => $orderId]);
            }
            AuditLog::record($userId, $refund ? 'order_refunded' : 'order_cancelled', 'order', $orderId, trim($reason), $this->database);
            $this->database->commit();
            (new NotificationService($this->database))->sendOrderStatusEmail(
                (int) $order['user_id'],
                $orderId,
                (string) $order['order_no'],
                $nextStatus
            );
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public static function defaultTransitionNote(string $status): string
    {
        return [
            'confirmed' => 'Đơn hàng đã được xác nhận',
            'processing' => 'Đơn hàng đang được đóng gói',
            'shipping' => 'Đơn hàng đang trên đường giao',
            'completed' => 'Đơn hàng đã giao thành công',
        ][$status] ?? 'Đơn hàng đã được cập nhật';
    }

    public function nextMainlineStep(int $orderId, bool $canManage): array
    {
        $query = $this->database->prepare(
            'SELECT id, order_no, status, payment_status FROM orders WHERE id = :id LIMIT 1'
        );
        $query->execute(['id' => $orderId]);
        $order = $query->fetch();
        if (!$order) {
            return ['status' => null, 'order_no' => '#' . $orderId, 'reason' => 'Đơn hàng không tồn tại.'];
        }

        $nextStatus = self::MAINLINE_NEXT_STATUS[$order['status']] ?? null;
        if ($nextStatus === null) {
            return [
                'status' => null,
                'order_no' => (string) $order['order_no'],
                'reason' => $order['status'] === 'shipping'
                    ? 'Chờ khách xác nhận'
                    : 'Trạng thái hiện tại không có bước kế tiếp.',
            ];
        }
        if ($nextStatus === 'confirmed' && !$canManage) {
            return [
                'status' => null,
                'order_no' => (string) $order['order_no'],
                'reason' => 'Bạn không có quyền xác nhận đơn hàng.',
            ];
        }
        if ($nextStatus === 'confirmed' && $order['payment_status'] === 'pending') {
            $online = $this->database->prepare(
                "SELECT COUNT(*) FROM payments
                 WHERE order_id = :id AND method IN ('bank_transfer', 'vnpay')"
            );
            $online->execute(['id' => $orderId]);
            if ((int) $online->fetchColumn() > 0) {
                return [
                    'status' => null,
                    'order_no' => (string) $order['order_no'],
                    'reason' => 'Đơn thanh toán trực tuyến chưa được xác nhận.',
                ];
            }
        }

        return ['status' => $nextStatus, 'order_no' => (string) $order['order_no'], 'reason' => null];
    }

    public function transitionToNext(
        int $orderId,
        ?int $userId,
        bool $canManage,
        string $source = 'quick'
    ): string {
        $step = $this->nextMainlineStep($orderId, $canManage);
        if ($step['status'] === null) {
            throw new InventoryException((string) $step['reason']);
        }

        $nextStatus = (string) $step['status'];
        $this->transition(
            $orderId,
            $userId,
            $nextStatus,
            self::defaultTransitionNote($nextStatus),
            $canManage,
            null,
            $source
        );

        return $nextStatus;
    }

    public function advanceMany(array $orderIds, int $userId, bool $canManage): array
    {
        $uniqueIds = [];
        $seen = [];
        $skipped = [];
        foreach ($orderIds as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT);
            if ($id === false || $id < 1) {
                $skipped[] = [
                    'order_no' => 'Đơn không hợp lệ',
                    'reason' => 'Mã đơn không hợp lệ.',
                ];
                continue;
            }
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            if (count($uniqueIds) >= self::MAX_BULK_ORDER_ADVANCES) {
                $skipped[] = [
                    'order_no' => '#' . $id,
                    'reason' => 'Vượt giới hạn ' . self::MAX_BULK_ORDER_ADVANCES . ' đơn mỗi lần.',
                ];
                continue;
            }
            $uniqueIds[] = (int) $id;
        }

        $advanced = [];
        foreach ($uniqueIds as $orderId) {
            try {
                $step = $this->nextMainlineStep($orderId, $canManage);
                if ($step['status'] === null) {
                    $skipped[] = ['order_no' => $step['order_no'], 'reason' => $step['reason']];
                    continue;
                }
                $nextStatus = (string) $step['status'];
                $this->transition(
                    $orderId,
                    $userId,
                    $nextStatus,
                    self::defaultTransitionNote($nextStatus),
                    $canManage,
                    null,
                    'bulk'
                );
                $advanced[] = [
                    'order_id' => $orderId,
                    'order_no' => $step['order_no'],
                    'status' => $nextStatus,
                ];
            } catch (Throwable $exception) {
                error_log('Bulk order advance failed (order=' . $orderId . '): ' . $exception->getMessage());
                $reason = $exception instanceof InventoryException || $exception instanceof InvalidArgumentException
                    ? $exception->getMessage()
                    : 'Lỗi xử lý đơn hàng.';
                $skipped[] = ['order_no' => '#' . $orderId, 'reason' => $reason];
            }
        }

        return ['advanced' => $advanced, 'skipped' => $skipped];
    }

    public function confirmReceived(int $orderId, int $userId): void
    {
        if ($userId < 1) {
            throw new InvalidArgumentException('Tài khoản không hợp lệ.');
        }
        $this->transition(
            $orderId,
            $userId,
            'completed',
            'Khách hàng xác nhận đã nhận hàng',
            false,
            $userId,
            'customer_received'
        );
    }

    public function adminConfirmDelivered(int $orderId, int $adminId, string $reason, bool $canManage): void
    {
        $reason = trim($reason);
        if (!$canManage || $adminId < 1) {
            throw new InventoryException('Chỉ người có quyền quản lý đơn hàng mới được xác nhận thay khách.');
        }
        if ($reason === '' || mb_strlen($reason, 'UTF-8') > 450) {
            throw new InvalidArgumentException('Vui lòng nhập lý do xác nhận đã giao (tối đa 450 ký tự).');
        }

        $this->transition(
            $orderId,
            $adminId,
            'completed',
            'Quản trị viên xác nhận thay khách: ' . $reason,
            true,
            null,
            'admin_delivery_override'
        );
    }

    public function recordDeliveryFailure(
        int $orderId,
        int $adminId,
        string $reason,
        string $note,
        bool $canManage
    ): void {
        $note = trim($note);
        if (!$canManage || $adminId < 1) {
            throw new InventoryException('Bạn không có quyền ghi nhận giao hàng thất bại.');
        }
        if (!array_key_exists($reason, self::DELIVERY_FAILURE_REASONS)) {
            throw new InvalidArgumentException('Vui lòng chọn lý do giao thất bại hợp lệ.');
        }
        if (($reason === 'other' && $note === '')
            || mb_strlen($note, 'UTF-8') > 450) {
            throw new InvalidArgumentException(
                $reason === 'other'
                    ? 'Vui lòng nhập ghi chú cho lý do khác (tối đa 450 ký tự).'
                    : 'Ghi chú không được vượt quá 450 ký tự.'
            );
        }

        $this->database->beginTransaction();
        try {
            $query = $this->database->prepare('SELECT * FROM orders WHERE id = :id FOR UPDATE');
            $query->execute(['id' => $orderId]);
            $order = $query->fetch();
            if (!$order || $order['status'] !== 'shipping') {
                throw new InventoryException('Chỉ có thể ghi nhận giao thất bại cho đơn đang giao.');
            }

            $paymentQuery = $this->database->prepare(
                'SELECT id, method, status FROM payments WHERE order_id = :id ORDER BY id DESC LIMIT 1 FOR UPDATE'
            );
            $paymentQuery->execute(['id' => $orderId]);
            $payment = $paymentQuery->fetch() ?: null;

            $this->restoreOrderResources($orderId, $adminId);

            $paymentStatus = (string) $order['payment_status'];
            if ($payment !== null && $payment['method'] === 'cod' && $paymentStatus !== 'paid') {
                $paymentStatus = 'failed';
                $this->database->prepare(
                    "UPDATE payments SET status = 'failed'
                     WHERE order_id = :id AND method = 'cod' AND status = 'pending'"
                )->execute(['id' => $orderId]);
            } elseif ($payment !== null
                && in_array($payment['method'], ['bank_transfer', 'vnpay', 'online'], true)
                && $paymentStatus === 'paid') {
                $this->database->prepare(
                    "UPDATE payments SET refund_required = 1
                     WHERE order_id = :id AND method IN ('bank_transfer', 'vnpay', 'online')
                       AND status = 'paid' AND refund_required = 0"
                )->execute(['id' => $orderId]);
            }

            $this->database->prepare(
                'UPDATE orders SET status = \'delivery_failed\', payment_status = :payment_status WHERE id = :id'
            )->execute(['payment_status' => $paymentStatus, 'id' => $orderId]);

            $label = self::DELIVERY_FAILURE_REASONS[$reason];
            $historyNote = $label . ($note === '' ? '' : ': ' . $note);
            $this->database->prepare(
                'INSERT INTO order_status_history (order_id, status, note, changed_by)
                 VALUES (:id, \'delivery_failed\', :note, :user_id)'
            )->execute(['id' => $orderId, 'note' => $historyNote, 'user_id' => $adminId]);
            $historyId = (int) $this->database->lastInsertId();

            $phone = DeliveryFailureNormalizer::normalizePhone((string) $order['recipient_phone']);
            if ($phone === '') {
                throw new InventoryException('Số điện thoại người nhận của đơn hàng không hợp lệ.');
            }
            $addressKey = DeliveryFailureNormalizer::addressKey((string) $order['shipping_address']);
            $this->database->prepare(
                'INSERT INTO delivery_failures
                    (order_id, user_id, phone_norm, address_key, reason, note, counts_against_customer)
                 VALUES (:order_id, :user_id, :phone, :address_key, :reason, :note, :counts)'
            )->execute([
                'order_id' => $orderId,
                'user_id' => (int) $order['user_id'],
                'phone' => $phone,
                'address_key' => $addressKey,
                'reason' => $reason,
                'note' => $note === '' ? null : $note,
                'counts' => in_array($reason, ['customer_refused', 'unreachable'], true) ? 1 : 0,
            ]);

            (new NotificationService($this->database))->notifyOrderStatus(
                (int) $order['user_id'],
                $orderId,
                $historyId,
                (string) $order['order_no'],
                'delivery_failed'
            );
            AuditLog::record(
                $adminId,
                'order_delivery_failed',
                'order',
                $orderId,
                'reason=' . $reason . ($note === '' ? '' : '; note=' . $note),
                $this->database
            );
            $this->database->commit();
            (new NotificationService($this->database))->sendOrderStatusEmail(
                (int) $order['user_id'],
                $orderId,
                (string) $order['order_no'],
                'delivery_failed'
            );
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function autoCompleteShippingOrders(): array
    {
        if (!$this->claimAutoCompletionRun()) {
            return ['processed' => 0, 'failed' => 0, 'throttled' => true];
        }

        $orders = $this->database->query(
            "SELECT o.id
             FROM orders o
             JOIN order_status_history h ON h.order_id = o.id AND h.status = 'shipping'
             WHERE o.status = 'shipping'
               AND h.created_at <= DATE_SUB(NOW(), INTERVAL " . self::AUTO_COMPLETION_DAYS . " DAY)
               AND NOT EXISTS (
                    SELECT 1 FROM order_status_history newer
                    WHERE newer.order_id = h.order_id AND newer.id > h.id
               )
             ORDER BY h.created_at, h.id
             LIMIT " . self::MAX_AUTO_COMPLETIONS_PER_RUN
        )->fetchAll(PDO::FETCH_COLUMN);

        $processed = 0;
        $failed = 0;
        foreach ($orders as $orderId) {
            try {
                $this->transition(
                    (int) $orderId,
                    null,
                    'completed',
                    'Hệ thống tự xác nhận sau ' . self::AUTO_COMPLETION_DAYS . ' ngày',
                    true,
                    null,
                    'auto_complete'
                );
                $processed++;
            } catch (Throwable $exception) {
                $failed++;
                error_log('Automatic order completion failed (order=' . (int) $orderId . '): ' . $exception->getMessage());
            }
        }

        return ['processed' => $processed, 'failed' => $failed, 'throttled' => false];
    }

    public function runLazyAutoCompletionSafely(): void
    {
        try {
            $this->autoCompleteShippingOrders();
        } catch (Throwable $exception) {
            error_log('Lazy automatic order completion failed: ' . $exception->getMessage());
        }
    }

    public function transition(
        int $orderId,
        ?int $userId,
        string $nextStatus,
        string $note,
        bool $canManage,
        ?int $expectedOwnerId = null,
        string $source = 'manual'
    ): void {
        $allowed = [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['processing', 'cancelled'],
            'processing' => ['shipping', 'cancelled'],
            'shipping' => ['completed', 'refunded'],
            'completed' => ['refunded'],
        ];
        $this->database->beginTransaction();
        try {
            $sql = 'SELECT * FROM orders WHERE id = :id';
            $parameters = ['id' => $orderId];
            if ($expectedOwnerId !== null) {
                $sql .= ' AND user_id = :expected_owner_id';
                $parameters['expected_owner_id'] = $expectedOwnerId;
            }
            $sql .= ' FOR UPDATE';
            $query = $this->database->prepare($sql);
            $query->execute($parameters);
            $order = $query->fetch();
            if (!$order || !in_array($nextStatus, $allowed[$order['status']] ?? [], true)) {
                throw new InventoryException($expectedOwnerId === null
                    ? 'Không thể chuyển đơn hàng sang trạng thái đã chọn.'
                    : 'Đơn hàng không tồn tại hoặc không ở trạng thái đang giao.');
            }
            if ($nextStatus === 'completed') {
                $customerConfirmation = $expectedOwnerId !== null
                    && $userId === $expectedOwnerId;
                $automaticConfirmation = $source === 'auto_complete' && $userId === null;
                $adminOverride = $source === 'admin_delivery_override'
                    && $canManage
                    && $userId !== null
                    && str_starts_with($note, 'Quản trị viên xác nhận thay khách: ');
                if (!$customerConfirmation && !$automaticConfirmation && !$adminOverride) {
                    throw new InventoryException('Chỉ khách hàng, hệ thống hoặc quản trị viên có lý do mới được xác nhận đã giao.');
                }
            }
            if ($order['payment_status'] === 'pending') {
                $online = $this->database->prepare(
                    "SELECT COUNT(*) FROM payments WHERE order_id = :id AND method IN ('bank_transfer','vnpay')"
                );
                $online->execute(['id' => $orderId]);
                if ((int) $online->fetchColumn() > 0 && !in_array($nextStatus, ['cancelled'], true)) {
                    throw new InventoryException('Chỉ có thể xác nhận đơn trực tuyến sau khi đã ghi nhận thanh toán.');
                }
            }
            if (!$canManage && !in_array($nextStatus, ['processing', 'shipping', 'completed'], true)) {
                throw new InventoryException('Nhân viên kho chỉ được chuyển bước đóng gói/giao hàng.');
            }
            if (in_array($nextStatus, ['cancelled', 'refunded'], true)) {
                if (trim($note) === '') {
                    throw new InvalidArgumentException('Bắt buộc nhập lý do hủy hoặc hoàn trả.');
                }
                if ($userId === null || $userId < 1) {
                    throw new InventoryException('Cần có người thực hiện để hủy hoặc hoàn trả đơn hàng.');
                }
                (new InventoryService($this->database))->restoreForOrder($orderId, $userId);
                $this->reverseVoucherUsage($orderId);
            }
            $note = trim($note);
            if ($note === '') {
                $note = self::defaultTransitionNote($nextStatus);
            }
            $paymentStatus = $order['payment_status'];
            if ($nextStatus === 'completed') {
                $paymentStatus = 'paid';
                $this->database->prepare(
                    'UPDATE payments SET status = \'paid\', paid_at = NOW()
                     WHERE order_id = :id AND method = \'cod\' AND status = \'pending\''
                )->execute(['id' => $orderId]);
            } elseif ($nextStatus === 'cancelled' && $paymentStatus === 'pending') {
                $paymentStatus = 'failed';
                $this->database->prepare(
                    "UPDATE payments SET status = 'failed' WHERE order_id = :id AND status = 'pending'"
                )->execute(['id' => $orderId]);
            } elseif ($nextStatus === 'refunded' && $paymentStatus === 'paid') {
                $paymentStatus = 'refunded';
                $this->database->prepare('UPDATE payments SET status = \'refunded\' WHERE order_id = :id AND status = \'paid\'')
                    ->execute(['id' => $orderId]);
            }
            $this->database->prepare('UPDATE orders SET status = :status, payment_status = :payment WHERE id = :id')
                ->execute(['status' => $nextStatus, 'payment' => $paymentStatus, 'id' => $orderId]);
            $this->database->prepare(
                'INSERT INTO order_status_history (order_id, status, note, changed_by) VALUES (:id, :status, :note, :user_id)'
            )->execute(['id' => $orderId, 'status' => $nextStatus, 'note' => trim($note) ?: null, 'user_id' => $userId]);
            (new NotificationService($this->database))->notifyOrderStatus(
                (int) $order['user_id'],
                $orderId,
                (int) $this->database->lastInsertId(),
                (string) $order['order_no'],
                $nextStatus
            );
            AuditLog::record(
                $userId,
                'order_status_changed',
                'order',
                $orderId,
                $order['status'] . ' -> ' . $nextStatus . '; source=' . $source,
                $this->database
            );
            $this->database->commit();
            (new NotificationService($this->database))->sendOrderStatusEmail(
                (int) $order['user_id'],
                $orderId,
                (string) $order['order_no'],
                $nextStatus
            );
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    private function claimAutoCompletionRun(): bool
    {
        $this->database->beginTransaction();
        try {
            $this->database->prepare(
                'INSERT IGNORE INTO settings (setting_key, setting_value, description)
                 VALUES (:setting_key, \'1970-01-01 00:00:00\', :description)'
            )->execute([
                'setting_key' => self::AUTO_COMPLETION_SETTING,
                'description' => 'Thời điểm gần nhất kiểm tra đơn đang giao quá hạn.',
            ]);
            $query = $this->database->prepare(
                'SELECT setting_value,
                        TIMESTAMPDIFF(
                            SECOND,
                            STR_TO_DATE(setting_value, \'%Y-%m-%d %H:%i:%s\'),
                            NOW()
                        ) AS elapsed_seconds
                 FROM settings WHERE setting_key = :setting_key FOR UPDATE'
            );
            $query->execute(['setting_key' => self::AUTO_COMPLETION_SETTING]);
            $lastRun = $query->fetch();
            if (!$lastRun) {
                throw new RuntimeException('Automatic order completion throttle setting is unavailable.');
            }
            if ($lastRun['elapsed_seconds'] !== null
                && (int) $lastRun['elapsed_seconds'] >= 0
                && (int) $lastRun['elapsed_seconds'] < self::AUTO_COMPLETION_THROTTLE_SECONDS) {
                $this->database->commit();
                return false;
            }

            $this->database->prepare(
                'UPDATE settings SET setting_value = DATE_FORMAT(NOW(), \'%Y-%m-%d %H:%i:%s\')
                 WHERE setting_key = :setting_key'
            )->execute(['setting_key' => self::AUTO_COMPLETION_SETTING]);
            $this->database->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    private function resolveAddress(int $userId, array $input): array
    {
        $addressId = filter_var($input['address_id'] ?? null, FILTER_VALIDATE_INT);
        if ($addressId) {
            $query = $this->database->prepare(
                'SELECT a.recipient_name, a.phone, a.region_id, a.address_line, a.ward, r.name AS region_name
                 FROM addresses a JOIN regions r ON r.id = a.region_id
                 WHERE a.id = :id AND a.user_id = :user_id AND r.is_active = 1 FOR UPDATE'
            );
            $query->execute(['id' => $addressId, 'user_id' => $userId]);
            $address = $query->fetch();
            if (!$address) {
                throw new InvalidArgumentException('Hãy chọn địa chỉ nhận hàng thuộc tài khoản của bạn.');
            }
        } else {
            $name = trim((string) ($input['recipient_name'] ?? ''));
            $phone = trim((string) ($input['phone'] ?? ''));
            $addressLine = trim((string) ($input['address_line'] ?? ''));
            $ward = trim((string) ($input['ward'] ?? ''));
            $regionId = filter_var($input['region_id'] ?? null, FILTER_VALIDATE_INT);
            if ($name === '' || mb_strlen($name, 'UTF-8') > 150
                || preg_match('/\A[0-9+()\-\s]{6,30}\z/', $phone) !== 1
                || $addressLine === '' || mb_strlen($addressLine, 'UTF-8') > 255 || $regionId === false) {
                throw new InvalidArgumentException('Tên, số điện thoại, địa chỉ hoặc tỉnh/thành nhận hàng chưa hợp lệ.');
            }
            $regionQuery = $this->database->prepare('SELECT name FROM regions WHERE id = :id AND level = 2 AND is_active = 1');
            $regionQuery->execute(['id' => $regionId]);
            $regionName = $regionQuery->fetchColumn();
            if ($regionName === false) {
                throw new InvalidArgumentException('Vui lòng chọn tỉnh/thành nhận hàng hợp lệ.');
            }
            $address = [
                'recipient_name' => $name,
                'phone' => $phone,
                'region_id' => (int) $regionId,
                'address_line' => $addressLine,
                'ward' => $ward,
                'region_name' => $regionName,
            ];
            $this->database->prepare(
                'INSERT INTO addresses (user_id, recipient_name, phone, region_id, address_line, ward, is_default)
                 VALUES (:user_id, :name, :phone, :region_id, :address, :ward, 0)'
            )->execute([
                'user_id' => $userId,
                'name' => $name,
                'phone' => $phone,
                'region_id' => $regionId,
                'address' => $addressLine,
                'ward' => $ward,
            ]);
        }
        $address['full_address'] = implode(', ', array_filter([
            $address['address_line'],
            $address['ward'],
            $address['region_name'],
        ]));
        return $address;
    }

    private function nextOrderNumber(): string
    {
        $date = date('ymd');
        $key = 'order:' . $date;
        $this->database->prepare('INSERT IGNORE INTO document_sequences (sequence_key, next_value) VALUES (:key, 1)')
            ->execute(['key' => $key]);
        $query = $this->database->prepare('SELECT next_value FROM document_sequences WHERE sequence_key = :key FOR UPDATE');
        $query->execute(['key' => $key]);
        $number = (int) $query->fetchColumn();
        if ($number < 1 || $number > 9999) {
            throw new RuntimeException('Đã vượt số đơn hàng có thể cấp trong ngày.');
        }
        $this->database->prepare('UPDATE document_sequences SET next_value = :next WHERE sequence_key = :key')
            ->execute(['next' => $number + 1, 'key' => $key]);
        return 'DH-' . $date . '-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }

    private function reverseVoucherUsage(int $orderId): void
    {
        $query = $this->database->prepare(
            'SELECT voucher_id FROM voucher_usages WHERE order_id = :id AND status = \'used\' FOR UPDATE'
        );
        $query->execute(['id' => $orderId]);
        $voucherId = $query->fetchColumn();
        if ($voucherId === false) {
            return;
        }

        $this->database->prepare(
            'UPDATE voucher_usages SET status = \'reversed\', reversed_at = NOW()
             WHERE order_id = :id AND status = \'used\''
        )->execute(['id' => $orderId]);
        $this->database->prepare('UPDATE vouchers SET used_count = CASE WHEN used_count > 0 THEN used_count - 1 ELSE 0 END WHERE id = :id')
            ->execute(['id' => $voucherId]);
    }

    private function restoreOrderResources(int $orderId, int $userId): void
    {
        (new InventoryService($this->database))->restoreForOrder($orderId, $userId);
        (new FlashSaleService($this->database))->releaseForOrder($orderId);
        $this->reverseVoucherUsage($orderId);
    }

    private function maxCartQuantity(): int
    {
        $value = $this->database->query("SELECT setting_value FROM settings WHERE setting_key = 'cart_max_quantity'")->fetchColumn();
        $limit = filter_var($value, FILTER_VALIDATE_INT);
        return $limit !== false && $limit >= 1 && $limit <= 100 ? $limit : 20;
    }

    private function decimal(int $value): string
    {
        return (string) $value . '.00';
    }

    private function emailForUser(int $userId): string
    {
        $query = $this->database->prepare('SELECT email FROM users WHERE id = :id');
        $query->execute(['id' => $userId]);
        return (string) $query->fetchColumn();
    }
}
