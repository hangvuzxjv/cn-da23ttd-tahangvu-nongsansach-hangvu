<?php

declare(strict_types=1);

final class OrderReturnService
{
    private const MAX_IMAGE_BYTES = 2 * 1024 * 1024;
    private const OPEN_STATUSES = ['pending', 'approved'];
    private const CONSUMED_STATUSES = ['pending', 'approved', 'refunded'];
    private const REASONS = [
        'damaged' => 'Hư hỏng / dập nát',
        'wrong_product' => 'Sai sản phẩm',
        'missing_items' => 'Thiếu hàng',
        'quality' => 'Chất lượng không đạt / hỏng sớm',
        'other' => 'Lý do khác',
    ];
    private const STATUSES = [
        'pending' => 'Đang chờ cửa hàng xử lý',
        'approved' => 'Đã duyệt',
        'rejected' => 'Bị từ chối',
        'refunded' => 'Đã hoàn tiền',
        'cancelled' => 'Đã hủy',
    ];

    private PDO $database;

    public function __construct(?PDO $database = null)
    {
        $this->database = $database ?? Database::connection();
    }

    public static function reasons(): array
    {
        return self::REASONS;
    }

    public static function statuses(): array
    {
        return self::STATUSES;
    }

    public static function validateEvidenceFile(array $file, bool $requireUploadedFile = true): array
    {
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Vui lòng chọn ảnh hợp lệ và tải lên đầy đủ.');
        }
        $temporaryPath = (string) ($file['tmp_name'] ?? '');
        if ($temporaryPath === '' || !is_file($temporaryPath)
            || ($requireUploadedFile && !is_uploaded_file($temporaryPath))) {
            throw new InvalidArgumentException('Tệp ảnh tải lên không hợp lệ.');
        }
        $actualSize = filesize($temporaryPath);
        if ($actualSize === false || $actualSize < 1 || $actualSize > self::MAX_IMAGE_BYTES) {
            throw new InvalidArgumentException('Mỗi ảnh bằng chứng phải có dung lượng tối đa 2 MB.');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        $extensionByMime = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (!is_string($mime) || !isset($extensionByMime[$mime])) {
            throw new InvalidArgumentException('Chỉ chấp nhận ảnh JPG, PNG hoặc WebP.');
        }
        $image = @getimagesize($temporaryPath);
        if (!$image || ($image['mime'] ?? '') !== $mime
            || (int) ($image[0] ?? 0) < 1 || (int) ($image[1] ?? 0) < 1
            || (int) $image[0] * (int) $image[1] > 40000000) {
            throw new InvalidArgumentException('Tệp không phải ảnh hợp lệ hoặc có kích thước quá lớn.');
        }

        $originalName = str_replace('\\', '/', (string) ($file['name'] ?? ''));
        $originalName = basename($originalName);
        $originalName = preg_replace('/[\x00-\x1F\x7F]/u', '', $originalName) ?? '';
        $originalName = mb_substr($originalName !== '' ? $originalName : 'anh-bang-chung', 0, 255, 'UTF-8');

        return [
            'tmp_name' => $temporaryPath,
            'original_name' => $originalName,
            'size' => (int) $actualSize,
            'mime' => $mime,
            'extension' => $extensionByMime[$mime],
        ];
    }

    public static function storeEvidenceFiles(array $files): array
    {
        if (count($files) < 1 || count($files) > 3) {
            throw new InvalidArgumentException('Vui lòng tải lên từ 1 đến 3 ảnh bằng chứng.');
        }
        $directory = ROOT_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'order-returns';
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Không thể tạo thư mục lưu ảnh bằng chứng.');
        }

        $stored = [];
        try {
            foreach ($files as $file) {
                $validated = self::validateEvidenceFile($file);
                $filename = bin2hex(random_bytes(20)) . '.' . $validated['extension'];
                $destination = $directory . DIRECTORY_SEPARATOR . $filename;
                if (!move_uploaded_file($validated['tmp_name'], $destination)) {
                    throw new RuntimeException('Không thể lưu ảnh bằng chứng.');
                }
                $stored[] = [
                    'path' => 'uploads/order-returns/' . $filename,
                    'original_name' => $validated['original_name'],
                    'size' => $validated['size'],
                ];
            }
        } catch (Throwable $exception) {
            self::deleteStoredEvidence($stored);
            throw $exception;
        }
        return $stored;
    }

    public static function deleteStoredEvidence(array $files): void
    {
        foreach ($files as $file) {
            $path = (string) ($file['path'] ?? '');
            if (preg_match('/\Auploads\/order-returns\/[a-f0-9]{40}\.(?:jpg|png|webp)\z/', $path) !== 1) {
                continue;
            }
            $absolutePath = ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
            if (is_file($absolutePath)) {
                unlink($absolutePath);
            }
        }
    }

    public function customerOrderContext(int $orderId, int $userId): array
    {
        $order = $this->customerOrder($orderId, $userId);
        $deliveredAt = $this->deliveredAt($orderId);
        $hours = $this->returnWindowHours();
        $eligibleUntil = $deliveredAt === null
            ? null
            : $deliveredAt->modify('+' . $hours . ' hours');
        $open = $this->database->prepare(
            'SELECT id FROM order_returns
             WHERE order_id = :order_id AND status IN (\'pending\', \'approved\')
             ORDER BY requested_at DESC, id DESC LIMIT 1'
        );
        $open->execute(['order_id' => $orderId]);
        $openReturnId = $open->fetchColumn();
        $availableItems = $this->availableItems($orderId);
        $eligible = $order['status'] === 'completed'
            && $eligibleUntil !== null
            && new DateTimeImmutable(PaymentRevenueService::now(), new DateTimeZone('Asia/Ho_Chi_Minh')) <= $eligibleUntil
            && $openReturnId === false
            && $availableItems !== [];

        $returnsQuery = $this->database->prepare(
            'SELECT id, reason_code, status, resolution, refund_amount, admin_note, requested_at,
                    decided_at, refunded_at
             FROM order_returns WHERE order_id = :order_id AND user_id = :user_id
             ORDER BY requested_at DESC, id DESC'
        );
        $returnsQuery->execute(['order_id' => $orderId, 'user_id' => $userId]);

        return [
            'order' => $order,
            'eligible' => $eligible,
            'return_window_hours' => $hours,
            'delivered_at' => $deliveredAt?->format('Y-m-d H:i:s'),
            'eligible_until' => $eligibleUntil?->format('Y-m-d H:i:s'),
            'open_return_id' => $openReturnId === false ? null : (int) $openReturnId,
            'items' => $availableItems,
            'returns' => $returnsQuery->fetchAll(),
        ];
    }

    public function createRequest(int $orderId, int $userId, array $input, array $images): int
    {
        $reasonCode = is_string($input['reason_code'] ?? null) ? $input['reason_code'] : '';
        $description = is_string($input['description'] ?? null) ? trim($input['description']) : '';
        if (!isset(self::REASONS[$reasonCode])) {
            throw new InvalidArgumentException('Chọn lý do trả hàng phù hợp.');
        }
        $descriptionLength = mb_strlen($description, 'UTF-8');
        if ($descriptionLength < 10 || $descriptionLength > 1000) {
            throw new InvalidArgumentException('Mô tả cần từ 10 đến 1.000 ký tự.');
        }
        if (count($images) < 1 || count($images) > 3) {
            throw new InvalidArgumentException('Vui lòng tải lên từ 1 đến 3 ảnh bằng chứng.');
        }
        foreach ($images as $image) {
            if (preg_match('/\Auploads\/order-returns\/[a-f0-9]{40}\.(?:jpg|png|webp)\z/', (string) ($image['path'] ?? '')) !== 1
                || (int) ($image['size'] ?? 0) < 1 || (int) $image['size'] > self::MAX_IMAGE_BYTES) {
                throw new InvalidArgumentException('Ảnh bằng chứng không hợp lệ.');
            }
        }

        $quantities = $this->parseQuantities($input['quantities'] ?? null);
        $orderNo = '';
        $returnId = 0;
        $emailMessage = '';
        $shouldEmail = false;
        $this->database->beginTransaction();
        try {
            $order = $this->lockCustomerOrder($orderId, $userId);
            $userLock = $this->database->prepare('SELECT id FROM users WHERE id = :id FOR UPDATE');
            $userLock->execute(['id' => $userId]);
            if (!$userLock->fetchColumn()) {
                throw new HttpException(404, 'Không tìm thấy tài khoản khách hàng.');
            }
            $this->assertOrderEligible($orderId, $order);
            $this->assertOpenRequestLimits($orderId, $userId);
            $this->assertRequestedQuantitiesAvailable($orderId, $quantities);
            $orderNo = (string) $order['order_no'];

            $this->database->prepare(
                'INSERT INTO order_returns (order_id, user_id, reason_code, description)
                 VALUES (:order_id, :user_id, :reason_code, :description)'
            )->execute([
                'order_id' => $orderId,
                'user_id' => $userId,
                'reason_code' => $reasonCode,
                'description' => $description,
            ]);
            $returnId = (int) $this->database->lastInsertId();

            $itemInsert = $this->database->prepare(
                'INSERT INTO order_return_items (return_id, order_item_id, quantity)
                 VALUES (:return_id, :order_item_id, :quantity)'
            );
            foreach ($quantities as $itemId => $quantity) {
                $itemInsert->execute([
                    'return_id' => $returnId,
                    'order_item_id' => $itemId,
                    'quantity' => $quantity,
                ]);
            }
            $imageInsert = $this->database->prepare(
                'INSERT INTO order_return_images (return_id, path, original_name, size)
                 VALUES (:return_id, :path, :original_name, :size)'
            );
            foreach ($images as $image) {
                $imageInsert->execute([
                    'return_id' => $returnId,
                    'path' => $image['path'],
                    'original_name' => $image['original_name'],
                    'size' => (int) $image['size'],
                ]);
            }
            $this->insertHistory($returnId, 'pending', 'Khách hàng đã gửi yêu cầu.', $userId);
            $notifier = new NotificationService($this->database);
            $shouldEmail = $notifier->notifyOrderReturnEvent(
                $userId,
                $returnId,
                $orderId,
                'requested',
                'Đã nhận yêu cầu trả hàng/hoàn tiền',
                'Cửa hàng đã nhận yêu cầu cho đơn ' . $orderNo . ' và sẽ sớm phản hồi.'
            );
            $notifier->notifyAdminsNewOrderReturn($returnId, $orderId, $orderNo);
            AuditLog::record($userId, 'order_return_requested', 'order_return', $returnId, 'Đơn ' . $orderNo, $this->database);
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }

        if ($shouldEmail) {
            (new NotificationService($this->database))->sendOrderReturnEmail(
                $userId,
                $returnId,
                $orderNo,
                'requested',
                'Đã nhận yêu cầu trả hàng/hoàn tiền',
                'Cửa hàng đã nhận yêu cầu của bạn và sẽ sớm phản hồi.'
            );
        }
        return $returnId;
    }

    public function customerReturn(int $returnId, int $userId): array
    {
        $detail = $this->returnDetail($returnId);
        if ((int) $detail['user_id'] !== $userId) {
            throw new HttpException(404, 'Không tìm thấy yêu cầu trả hàng.');
        }
        return $detail;
    }

    public function adminReturn(int $returnId): array
    {
        return $this->returnDetail($returnId);
    }

    public function customerImage(int $returnId, int $imageId, int $userId): array
    {
        return $this->imageForReturn($returnId, $imageId, 'user_id', $userId);
    }

    public function adminImage(int $returnId, int $imageId): array
    {
        return $this->imageForReturn($returnId, $imageId, null, null);
    }

    public function cancelRequest(int $returnId, int $userId): void
    {
        $emailData = null;
        $this->database->beginTransaction();
        try {
            $orderId = $this->returnOrderId($returnId);
            $order = $this->lockOrder($orderId);
            $return = $this->lockReturn($returnId);
            if ((int) $return['user_id'] !== $userId) {
                throw new HttpException(404, 'Không tìm thấy yêu cầu trả hàng.');
            }
            if ($return['status'] !== 'pending') {
                throw new InvalidArgumentException('Chỉ có thể hủy yêu cầu đang chờ cửa hàng xử lý.');
            }
            $this->database->prepare(
                'UPDATE order_returns SET status = \'cancelled\', decided_at = :now, decided_by = :user_id
                 WHERE id = :id'
            )->execute(['now' => PaymentRevenueService::now(), 'user_id' => $userId, 'id' => $returnId]);
            $this->insertHistory($returnId, 'cancelled', 'Khách hàng đã hủy yêu cầu.', $userId);
            $notifier = new NotificationService($this->database);
            $shouldEmail = $notifier->notifyOrderReturnEvent(
                $userId,
                $returnId,
                $orderId,
                'cancelled',
                'Yêu cầu trả hàng đã được hủy',
                'Yêu cầu trả hàng cho đơn ' . $order['order_no'] . ' đã được hủy theo yêu cầu của bạn.'
            );
            AuditLog::record($userId, 'order_return_cancelled', 'order_return', $returnId, null, $this->database);
            $this->database->commit();
            $emailData = $shouldEmail
                ? [$userId, $returnId, (string) $order['order_no'], 'cancelled', 'Yêu cầu trả hàng đã được hủy',
                    'Yêu cầu trả hàng cho đơn ' . $order['order_no'] . ' đã được hủy theo yêu cầu của bạn.']
                : null;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
        if ($emailData !== null) {
            (new NotificationService($this->database))->sendOrderReturnEmail(...$emailData);
        }
    }

    public function approve(int $returnId, int $adminId, array $input): void
    {
        $resolution = is_string($input['resolution'] ?? null) ? $input['resolution'] : '';
        if (!in_array($resolution, ['refund', 'replace'], true)) {
            throw new InvalidArgumentException('Chọn phương án hoàn tiền hoặc đổi/bù hàng.');
        }
        $note = is_string($input['admin_note'] ?? null) ? trim($input['admin_note']) : '';
        if (mb_strlen($note, 'UTF-8') > 1000) {
            throw new InvalidArgumentException('Ghi chú không được vượt quá 1.000 ký tự.');
        }
        $refundAmount = null;
        if ($resolution === 'refund') {
            $refundAmount = $this->parseMoney($input['refund_amount'] ?? null);
        } elseif ($note === '') {
            throw new InvalidArgumentException('Vui lòng ghi cách cửa hàng sẽ đổi hoặc bù hàng.');
        }

        $emailData = null;
        $this->database->beginTransaction();
        try {
            $orderId = $this->returnOrderId($returnId);
            $this->lockOrder($orderId);
            $return = $this->lockReturn($returnId);
            if ($return['status'] !== 'pending') {
                throw new InvalidArgumentException('Yêu cầu này không còn ở trạng thái chờ xử lý.');
            }
            if ($resolution === 'refund') {
                $maximum = $this->requestedMerchandiseAmount($returnId);
                if ($refundAmount === null || $refundAmount <= 0 || $refundAmount > $maximum) {
                    throw new InvalidArgumentException(
                        'Số tiền hoàn phải lớn hơn 0 và không vượt ' . number_format($maximum, 0, ',', '.') . ' đ.'
                    );
                }
            }
            $this->database->prepare(
                'UPDATE order_returns
                 SET status = \'approved\', resolution = :resolution, refund_amount = :refund_amount,
                     admin_note = :admin_note, decided_at = :decided_at, decided_by = :decided_by
                 WHERE id = :id'
            )->execute([
                'resolution' => $resolution,
                'refund_amount' => $refundAmount,
                'admin_note' => $note !== '' ? $note : null,
                'decided_at' => PaymentRevenueService::now(),
                'decided_by' => $adminId,
                'id' => $returnId,
            ]);
            $historyNote = $resolution === 'refund'
                ? 'Đã duyệt hoàn ' . number_format((float) $refundAmount, 0, ',', '.') . ' đ.'
                : 'Đã duyệt phương án đổi/bù hàng. ' . $note;
            $this->insertHistory($returnId, 'approved', $historyNote, $adminId);
            $message = $resolution === 'refund'
                ? 'Yêu cầu trả hàng cho đơn ' . $return['order_no'] . ' đã được duyệt hoàn '
                    . number_format((float) $refundAmount, 0, ',', '.') . ' đ. Cửa hàng sẽ cập nhật khi hoàn tất.'
                : 'Yêu cầu trả hàng cho đơn ' . $return['order_no'] . ' đã được duyệt. '
                    . ($note !== '' ? $note : 'Cửa hàng sẽ liên hệ để đổi/bù hàng.');
            $notifier = new NotificationService($this->database);
            $shouldEmail = $notifier->notifyOrderReturnEvent(
                (int) $return['user_id'],
                $returnId,
                $orderId,
                'approved',
                'Yêu cầu trả hàng đã được duyệt',
                $message
            );
            AuditLog::record($adminId, 'order_return_approved', 'order_return', $returnId, $historyNote, $this->database);
            $this->database->commit();
            $emailData = $shouldEmail
                ? [(int) $return['user_id'], $returnId, (string) $return['order_no'], 'approved',
                    'Yêu cầu trả hàng đã được duyệt', $message]
                : null;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
        if ($emailData !== null) {
            (new NotificationService($this->database))->sendOrderReturnEmail(...$emailData);
        }
    }

    public function reject(int $returnId, int $adminId, string $reason): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason, 'UTF-8') < 3 || mb_strlen($reason, 'UTF-8') > 1000) {
            throw new InvalidArgumentException('Lý do từ chối cần từ 3 đến 1.000 ký tự.');
        }
        $emailData = null;
        $this->database->beginTransaction();
        try {
            $orderId = $this->returnOrderId($returnId);
            $this->lockOrder($orderId);
            $return = $this->lockReturn($returnId);
            if ($return['status'] !== 'pending') {
                throw new InvalidArgumentException('Chỉ có thể từ chối yêu cầu đang chờ xử lý.');
            }
            $this->database->prepare(
                'UPDATE order_returns
                 SET status = \'rejected\', admin_note = :reason, decided_at = :decided_at, decided_by = :decided_by
                 WHERE id = :id'
            )->execute([
                'reason' => $reason,
                'decided_at' => PaymentRevenueService::now(),
                'decided_by' => $adminId,
                'id' => $returnId,
            ]);
            $this->insertHistory($returnId, 'rejected', $reason, $adminId);
            $message = 'Yêu cầu trả hàng cho đơn ' . $return['order_no'] . ' chưa được chấp nhận. Lý do: ' . $reason;
            $notifier = new NotificationService($this->database);
            $shouldEmail = $notifier->notifyOrderReturnEvent(
                (int) $return['user_id'],
                $returnId,
                $orderId,
                'rejected',
                'Yêu cầu trả hàng bị từ chối',
                $message
            );
            AuditLog::record($adminId, 'order_return_rejected', 'order_return', $returnId, $reason, $this->database);
            $this->database->commit();
            $emailData = $shouldEmail
                ? [(int) $return['user_id'], $returnId, (string) $return['order_no'], 'rejected',
                    'Yêu cầu trả hàng bị từ chối', $message]
                : null;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
        if ($emailData !== null) {
            (new NotificationService($this->database))->sendOrderReturnEmail(...$emailData);
        }
    }

    public function markRefunded(int $returnId, int $adminId, string $reference, bool $restock): void
    {
        $reference = trim($reference);
        if ($reference === '' || mb_strlen($reference, 'UTF-8') > 150) {
            throw new InvalidArgumentException('Nhập mã hoặc ghi chú tham chiếu hoàn tiền (tối đa 150 ký tự).');
        }
        $emailData = null;
        $this->database->beginTransaction();
        try {
            $orderId = $this->returnOrderId($returnId);
            $order = $this->lockOrder($orderId);
            $return = $this->lockReturn($returnId);
            if ($return['status'] !== 'approved' || $return['resolution'] !== 'refund') {
                throw new InvalidArgumentException('Chỉ yêu cầu hoàn tiền đã duyệt mới có thể đánh dấu đã hoàn.');
            }
            if ($restock) {
                $items = $this->returnItemsForRestock($returnId);
                (new InventoryService($this->database))->restockForReturn($orderId, $returnId, $items, $adminId);
            }

            $now = PaymentRevenueService::now();
            $this->database->prepare(
                'UPDATE order_returns
                 SET status = \'refunded\', restock = :restock, refunded_at = :refunded_at,
                     refund_reference = :refund_reference
                 WHERE id = :id'
            )->execute([
                'restock' => $restock ? 1 : 0,
                'refunded_at' => $now,
                'refund_reference' => $reference,
                'id' => $returnId,
            ]);
            $this->insertHistory(
                $returnId,
                'refunded',
                'Đã ghi nhận hoàn ' . number_format((float) $return['refund_amount'], 0, ',', '.') . ' đ. Tham chiếu: ' . $reference,
                $adminId
            );

            $totalRefundedQuery = $this->database->prepare(
                'SELECT COALESCE(SUM(refund_amount), 0) FROM order_returns
                 WHERE order_id = :order_id AND status = \'refunded\''
            );
            $totalRefundedQuery->execute(['order_id' => $orderId]);
            $fullyRefunded = (float) $totalRefundedQuery->fetchColumn() >= (float) $order['total_amount'];
            $paymentQuery = $this->database->prepare(
                'SELECT id FROM payments
                 WHERE order_id = :order_id AND status IN (\'paid\', \'refunded\')
                 ORDER BY paid_at DESC, id DESC LIMIT 1 FOR UPDATE'
            );
            $paymentQuery->execute(['order_id' => $orderId]);
            $paymentId = $paymentQuery->fetchColumn();
            if ($paymentId !== false) {
                $this->database->prepare(
                    'UPDATE payments SET refund_required = 0, refund_reference = :reference
                     WHERE id = :id'
                )->execute(['reference' => $reference, 'id' => (int) $paymentId]);
                if ($fullyRefunded) {
                    $this->database->prepare(
                        'UPDATE payments SET status = \'refunded\', refunded_at = :refunded_at WHERE id = :id'
                    )->execute(['refunded_at' => $now, 'id' => (int) $paymentId]);
                }
            }
            if ($fullyRefunded && $order['status'] !== 'refunded') {
                $this->database->prepare(
                    'UPDATE orders SET status = \'refunded\', payment_status = \'refunded\' WHERE id = :id'
                )->execute(['id' => $orderId]);
                $this->database->prepare(
                    'INSERT INTO order_status_history (order_id, status, note, changed_by)
                     VALUES (:order_id, \'refunded\', :note, :changed_by)'
                )->execute([
                    'order_id' => $orderId,
                    'note' => 'Đã hoàn đủ số tiền thanh toán qua các yêu cầu trả hàng.',
                    'changed_by' => $adminId,
                ]);
            }

            $message = 'Cửa hàng đã ghi nhận hoàn tiền ' . number_format((float) $return['refund_amount'], 0, ',', '.')
                . ' đ cho đơn ' . $return['order_no'] . '.';
            $notifier = new NotificationService($this->database);
            $shouldEmail = $notifier->notifyOrderReturnEvent(
                (int) $return['user_id'],
                $returnId,
                $orderId,
                'refunded',
                'Đã hoàn tiền yêu cầu trả hàng',
                $message
            );
            AuditLog::record(
                $adminId,
                'order_return_refunded',
                'order_return',
                $returnId,
                $reference . ($restock ? ' | Đã nhập lại kho' : ''),
                $this->database
            );
            $this->database->commit();
            $emailData = $shouldEmail
                ? [(int) $return['user_id'], $returnId, (string) $return['order_no'], 'refunded',
                    'Đã hoàn tiền yêu cầu trả hàng', $message]
                : null;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
        if ($emailData !== null) {
            (new NotificationService($this->database))->sendOrderReturnEmail(...$emailData);
        }
    }

    public function adminReturns(array $filters, int $requestedPage, int $perPage = 20): array
    {
        $conditions = [];
        $parameters = [];
        $status = (string) ($filters['status'] ?? '');
        if (isset(self::STATUSES[$status])) {
            $conditions[] = 'r.status = :status';
            $parameters['status'] = $status;
        } else {
            $status = '';
        }
        $search = is_string($filters['q'] ?? null) ? mb_substr(trim($filters['q']), 0, 100, 'UTF-8') : '';
        if ($search !== '') {
            $orderNumber = preg_match('/\A#?([A-Za-z0-9_-]+)\z/u', $search, $matches) === 1
                ? $matches[1]
                : '';
            $conditions[] = '(o.order_no = :order_no OR u.name LIKE :customer_name
                              OR u.email LIKE :customer_email OR u.phone LIKE :customer_phone)';
            $parameters['order_no'] = $orderNumber;
            $parameters['customer_name'] = '%' . $search . '%';
            $parameters['customer_email'] = '%' . $search . '%';
            $parameters['customer_phone'] = '%' . $search . '%';
        }
        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
        $count = $this->database->prepare(
            'SELECT COUNT(*) FROM order_returns r
             JOIN orders o ON o.id = r.order_id JOIN users u ON u.id = r.user_id' . $where
        );
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $perPage = min(max($perPage, 1), 100);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max($requestedPage, 1), $pages);
        $query = $this->database->prepare(
            'SELECT r.id, r.status, r.reason_code, r.requested_at, r.decided_at, r.refunded_at,
                    o.id AS order_id, o.order_no, u.name AS customer_name, u.email AS customer_email,
                    u.phone AS customer_phone
             FROM order_returns r
             JOIN orders o ON o.id = r.order_id JOIN users u ON u.id = r.user_id'
            . $where
            . ' ORDER BY (r.status = \'pending\') DESC, r.requested_at DESC, r.id DESC
               LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $name => $value) {
            $query->bindValue(':' . $name, $value);
        }
        $query->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $query->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $query->execute();
        return [
            'returns' => $query->fetchAll(),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'filters' => ['status' => $status, 'q' => $search],
        ];
    }

    private function returnDetail(int $returnId): array
    {
        $query = $this->database->prepare(
            'SELECT r.*, o.order_no, o.status AS order_status, o.payment_status, o.total_amount,
                    u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone
             FROM order_returns r
             JOIN orders o ON o.id = r.order_id
             JOIN users u ON u.id = r.user_id
             WHERE r.id = :id LIMIT 1'
        );
        $query->execute(['id' => $returnId]);
        $return = $query->fetch();
        if (!$return) {
            throw new HttpException(404, 'Không tìm thấy yêu cầu trả hàng.');
        }
        $items = $this->database->prepare(
            'SELECT ri.order_item_id, ri.quantity AS return_quantity, oi.product_name, oi.sku,
                    oi.unit, oi.unit_price, oi.line_total, oi.quantity AS ordered_quantity
             FROM order_return_items ri JOIN order_items oi ON oi.id = ri.order_item_id
             WHERE ri.return_id = :return_id ORDER BY oi.id'
        );
        $items->execute(['return_id' => $returnId]);
        $images = $this->database->prepare(
            'SELECT id, original_name, size, created_at FROM order_return_images
             WHERE return_id = :return_id ORDER BY id'
        );
        $images->execute(['return_id' => $returnId]);
        $history = $this->database->prepare(
            'SELECT h.status, h.note, h.created_at, u.name AS actor_name
             FROM order_return_history h LEFT JOIN users u ON u.id = h.changed_by
             WHERE h.return_id = :return_id ORDER BY h.created_at, h.id'
        );
        $history->execute(['return_id' => $returnId]);
        $return['items'] = $items->fetchAll();
        $return['images'] = $images->fetchAll();
        $return['history'] = $history->fetchAll();
        $return['maximum_refund'] = array_sum(array_map(
            static fn (array $item): float => (float) $item['unit_price'] * (int) $item['return_quantity'],
            $return['items']
        ));
        return $return;
    }

    private function imageForReturn(int $returnId, int $imageId, ?string $ownerColumn, ?int $ownerId): array
    {
        $sql = 'SELECT i.path, i.original_name FROM order_return_images i
                JOIN order_returns r ON r.id = i.return_id
                WHERE i.id = :image_id AND r.id = :return_id';
        if ($ownerColumn !== null) {
            if ($ownerColumn !== 'user_id') {
                throw new InvalidArgumentException('Trường sở hữu ảnh không hợp lệ.');
            }
            $sql .= ' AND r.user_id = :owner_id';
        }
        $query = $this->database->prepare($sql . ' LIMIT 1');
        $parameters = ['image_id' => $imageId, 'return_id' => $returnId];
        if ($ownerColumn !== null) {
            $parameters['owner_id'] = $ownerId;
        }
        $query->execute($parameters);
        $image = $query->fetch();
        if (!$image) {
            throw new HttpException(404, 'Không tìm thấy ảnh bằng chứng.');
        }
        $path = (string) $image['path'];
        if (preg_match('/\Auploads\/order-returns\/[a-f0-9]{40}\.(?:jpg|png|webp)\z/', $path) !== 1) {
            throw new RuntimeException('Đường dẫn ảnh lưu trữ không hợp lệ.');
        }
        $absolutePath = ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        $mime = is_file($absolutePath) ? (new finfo(FILEINFO_MIME_TYPE))->file($absolutePath) : false;
        if (!is_string($mime) || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new HttpException(404, 'Không tìm thấy ảnh bằng chứng.');
        }
        return ['path' => $absolutePath, 'mime' => $mime, 'name' => (string) $image['original_name']];
    }

    private function customerOrder(int $orderId, int $userId): array
    {
        $query = $this->database->prepare('SELECT * FROM orders WHERE id = :id AND user_id = :user_id');
        $query->execute(['id' => $orderId, 'user_id' => $userId]);
        $order = $query->fetch();
        if (!$order) {
            throw new HttpException(404, 'Không tìm thấy đơn hàng.');
        }
        return $order;
    }

    private function lockCustomerOrder(int $orderId, int $userId): array
    {
        $query = $this->database->prepare(
            'SELECT * FROM orders WHERE id = :id AND user_id = :user_id FOR UPDATE'
        );
        $query->execute(['id' => $orderId, 'user_id' => $userId]);
        $order = $query->fetch();
        if (!$order) {
            throw new HttpException(404, 'Không tìm thấy đơn hàng.');
        }
        return $order;
    }

    private function lockOrder(int $orderId): array
    {
        $query = $this->database->prepare('SELECT * FROM orders WHERE id = :id FOR UPDATE');
        $query->execute(['id' => $orderId]);
        $order = $query->fetch();
        if (!$order) {
            throw new HttpException(404, 'Không tìm thấy đơn hàng.');
        }
        return $order;
    }

    private function deliveredAt(int $orderId): ?DateTimeImmutable
    {
        $query = $this->database->prepare(
            'SELECT UNIX_TIMESTAMP(MIN(created_at)) FROM order_status_history
             WHERE order_id = :order_id AND status = \'completed\''
        );
        $query->execute(['order_id' => $orderId]);
        $value = $query->fetchColumn();
        if (!is_numeric($value) || (int) $value < 1) {
            return null;
        }
        return (new DateTimeImmutable('@' . (int) $value))
            ->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'));
    }

    private function returnWindowHours(): int
    {
        $query = $this->database->prepare(
            'SELECT setting_value FROM settings WHERE setting_key = \'return_window_hours\' LIMIT 1'
        );
        $query->execute();
        $value = filter_var($query->fetchColumn(), FILTER_VALIDATE_INT);
        return $value !== false && $value > 0 ? (int) $value : 48;
    }

    private function assertOrderEligible(int $orderId, array $order): void
    {
        if ($order['status'] !== 'completed') {
            throw new InvalidArgumentException('Chỉ đơn hàng đã giao mới được yêu cầu trả hàng.');
        }
        $deliveredAt = $this->deliveredAt($orderId);
        if ($deliveredAt === null) {
            throw new InvalidArgumentException('Không tìm thấy thời điểm giao hàng để kiểm tra thời hạn trả hàng.');
        }
        $deadline = $deliveredAt->modify('+' . $this->returnWindowHours() . ' hours');
        if (new DateTimeImmutable(PaymentRevenueService::now(), new DateTimeZone('Asia/Ho_Chi_Minh')) > $deadline) {
            throw new InvalidArgumentException('Đã quá thời hạn gửi yêu cầu trả hàng của đơn này.');
        }
    }

    private function assertOpenRequestLimits(int $orderId, int $userId): void
    {
        $orderOpen = $this->database->prepare(
            'SELECT COUNT(*) FROM order_returns
             WHERE order_id = :order_id AND status IN (\'pending\', \'approved\')'
        );
        $orderOpen->execute(['order_id' => $orderId]);
        if ((int) $orderOpen->fetchColumn() > 0) {
            throw new InvalidArgumentException('Đơn này đã có yêu cầu trả hàng đang được xử lý.');
        }
        $userOpen = $this->database->prepare(
            'SELECT COUNT(*) FROM order_returns
             WHERE user_id = :user_id AND status IN (\'pending\', \'approved\')'
        );
        $userOpen->execute(['user_id' => $userId]);
        if ((int) $userOpen->fetchColumn() >= 3) {
            throw new InvalidArgumentException('Mỗi khách hàng chỉ được có tối đa 3 yêu cầu trả hàng đang mở.');
        }
    }

    private function availableItems(int $orderId): array
    {
        $query = $this->database->prepare(
            'SELECT oi.id, oi.product_name, oi.sku, oi.unit, oi.unit_price, oi.quantity,
                    GREATEST(oi.quantity - COALESCE(used.used_quantity, 0), 0) AS available_quantity
             FROM order_items oi
             LEFT JOIN (
                 SELECT ri.order_item_id, SUM(ri.quantity) AS used_quantity
                 FROM order_return_items ri
                 JOIN order_returns r ON r.id = ri.return_id
                 WHERE r.order_id = :used_order_id
                   AND r.status IN (\'pending\', \'approved\', \'refunded\')
                 GROUP BY ri.order_item_id
             ) used ON used.order_item_id = oi.id
             WHERE oi.order_id = :order_id
             HAVING available_quantity > 0
             ORDER BY oi.id'
        );
        $query->execute(['used_order_id' => $orderId, 'order_id' => $orderId]);
        return $query->fetchAll();
    }

    private function parseQuantities(mixed $raw): array
    {
        if (!is_array($raw)) {
            throw new InvalidArgumentException('Chọn sản phẩm và số lượng cần trả.');
        }
        $quantities = [];
        foreach ($raw as $itemId => $value) {
            $validatedId = filter_var($itemId, FILTER_VALIDATE_INT);
            $validatedQuantity = filter_var($value, FILTER_VALIDATE_INT);
            if ($validatedId === false || $validatedId < 1 || $validatedQuantity === false || $validatedQuantity < 0) {
                throw new InvalidArgumentException('Sản phẩm hoặc số lượng trả lại không hợp lệ.');
            }
            if ($validatedQuantity > 0) {
                $quantities[(int) $validatedId] = (int) $validatedQuantity;
            }
        }
        if ($quantities === []) {
            throw new InvalidArgumentException('Chọn ít nhất một sản phẩm cần trả.');
        }
        return $quantities;
    }

    private function assertRequestedQuantitiesAvailable(int $orderId, array $quantities): void
    {
        $itemQuery = $this->database->prepare(
            'SELECT oi.quantity,
                    COALESCE((
                        SELECT SUM(ri.quantity)
                        FROM order_return_items ri
                        JOIN order_returns r ON r.id = ri.return_id
                        WHERE ri.order_item_id = oi.id AND r.order_id = :return_order_id
                          AND r.status IN (\'pending\', \'approved\', \'refunded\')
                    ), 0) AS used_quantity
             FROM order_items oi WHERE oi.id = :item_id AND oi.order_id = :order_id'
        );
        foreach ($quantities as $itemId => $quantity) {
            $itemQuery->execute([
                'return_order_id' => $orderId,
                'item_id' => $itemId,
                'order_id' => $orderId,
            ]);
            $item = $itemQuery->fetch();
            if (!$item || $quantity > (int) $item['quantity'] - (int) $item['used_quantity']) {
                throw new InvalidArgumentException('Số lượng trả lại vượt số sản phẩm còn có thể yêu cầu trong đơn.');
            }
        }
    }

    private function returnOrderId(int $returnId): int
    {
        $query = $this->database->prepare('SELECT order_id FROM order_returns WHERE id = :id');
        $query->execute(['id' => $returnId]);
        $orderId = $query->fetchColumn();
        if ($orderId === false) {
            throw new HttpException(404, 'Không tìm thấy yêu cầu trả hàng.');
        }
        return (int) $orderId;
    }

    private function lockReturn(int $returnId): array
    {
        $query = $this->database->prepare(
            'SELECT r.*, o.order_no, o.total_amount, o.status AS order_status
             FROM order_returns r JOIN orders o ON o.id = r.order_id
             WHERE r.id = :id FOR UPDATE'
        );
        $query->execute(['id' => $returnId]);
        $return = $query->fetch();
        if (!$return) {
            throw new HttpException(404, 'Không tìm thấy yêu cầu trả hàng.');
        }
        return $return;
    }

    private function requestedMerchandiseAmount(int $returnId): float
    {
        $query = $this->database->prepare(
            'SELECT COALESCE(SUM(oi.unit_price * ri.quantity), 0)
             FROM order_return_items ri JOIN order_items oi ON oi.id = ri.order_item_id
             WHERE ri.return_id = :return_id'
        );
        $query->execute(['return_id' => $returnId]);
        return (float) $query->fetchColumn();
    }

    private function parseMoney(mixed $value): ?float
    {
        if (!is_scalar($value) || preg_match('/\A(?:\d+)(?:\.\d{1,2})?\z/', trim((string) $value)) !== 1) {
            return null;
        }
        return round((float) $value, 2);
    }

    private function returnItemsForRestock(int $returnId): array
    {
        $query = $this->database->prepare(
            'SELECT order_item_id, quantity FROM order_return_items
             WHERE return_id = :return_id ORDER BY order_item_id'
        );
        $query->execute(['return_id' => $returnId]);
        return $query->fetchAll();
    }

    private function insertHistory(int $returnId, string $status, string $note, ?int $changedBy): void
    {
        $this->database->prepare(
            'INSERT INTO order_return_history (return_id, status, note, changed_by)
             VALUES (:return_id, :status, :note, :changed_by)'
        )->execute([
            'return_id' => $returnId,
            'status' => $status,
            'note' => mb_substr($note, 0, 1000, 'UTF-8'),
            'changed_by' => $changedBy,
        ]);
    }
}
