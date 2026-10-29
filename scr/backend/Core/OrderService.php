<?php

declare(strict_types=1);

final class OrderService
{
    private PDO $database;

    public function __construct(?PDO $database = null)
    {
        $this->database = $database ?? Database::connection();
    }

    public function place(int $userId, array $input): array
    {
        return $this->placeAttempt($userId, $input, 0);
    }

    private function placeAttempt(int $userId, array $input, int $attempt): array
    {
        $token = (string) ($input['idempotency_token'] ?? '');
        if ($userId < 1 || preg_match('/\A[a-f0-9]{64}\z/', $token) !== 1) {
            throw new InvalidArgumentException('Phiên đặt hàng không hợp lệ. Hãy tải lại trang thanh toán.');
        }
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
            $this->database->prepare(
                'INSERT INTO orders
                    (user_id, order_no, status, payment_status, recipient_name, recipient_phone,
                     shipping_address, region_name, region_id, subtotal, shipping_fee, discount_amount,
                     total_amount, voucher_code, customer_note, idempotency_token, placed_at)
                 VALUES
                    (:user_id, :order_no, \'pending\', \'unpaid\', :name, :phone, :address,
                     :region_name, :region_id, :subtotal, :shipping_fee, :discount, :total,
                     :voucher_code, :note, :token, NOW())'
            )->execute([
                'user_id' => $userId,
                'order_no' => $orderNo,
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

            $allocations = (new InventoryService($this->database))->deductForOrder($orderId, $pricingItems, $userId);
            $itemByProduct = [];
            foreach ($items as $item) {
                $itemByProduct[(int) $item['product_id']] = $item;
            }
            $insertItem = $this->database->prepare(
                'INSERT INTO order_items
                    (order_id, product_id, batch_id, product_name, sku, unit, unit_price, quantity, line_total)
                 VALUES (:order_id, :product_id, :batch_id, :name, :sku, :unit, :price, :quantity, :line_total)'
            );
            foreach ($allocations as $allocation) {
                $item = $itemByProduct[(int) $allocation['product_id']];
                $price = PricingService::moneyToVnd((string) $item['current_price']);
                $lineTotal = PricingService::lineTotal((string) $price, (int) $allocation['quantity']);
                $insertItem->execute([
                    'order_id' => $orderId,
                    'product_id' => $allocation['product_id'],
                    'batch_id' => $allocation['batch_id'],
                    'name' => $item['name'],
                    'sku' => $item['sku'],
                    'unit' => $item['unit'],
                    'price' => $this->decimal($price),
                    'quantity' => $allocation['quantity'],
                    'line_total' => $this->decimal($lineTotal),
                ]);
            }

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

            $this->database->prepare('INSERT INTO payments (order_id, method, amount, status) VALUES (:id, \'cod\', :amount, \'pending\')')
                ->execute(['id' => $orderId, 'amount' => $this->decimal($quote['total'])]);
            $this->database->prepare(
                'INSERT INTO order_status_history (order_id, status, note, changed_by)
                 VALUES (:id, \'pending\', \'Đơn hàng được tạo; chờ xác nhận.\', :user_id)'
            )->execute(['id' => $orderId, 'user_id' => $userId]);
            $this->database->prepare(
                'INSERT INTO notifications (user_id, title, message, link_url)
                 VALUES (:user_id, :title, :message, :link)'
            )->execute([
                'user_id' => $userId,
                'title' => 'Đặt hàng thành công',
                'message' => 'Đơn hàng ' . $orderNo . ' đã được tiếp nhận.',
                'link' => '/tai-khoan/don-hang/' . $orderId,
            ]);
            $this->database->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id')->execute(['cart_id' => $cartId]);
            AuditLog::record($userId, 'order_placed', 'order', $orderId, $orderNo, $this->database);
            $this->database->commit();
            return ['id' => $orderId, 'order_no' => $orderNo, 'existing' => false, 'email' => $this->emailForUser($userId)];
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
            (new InventoryService($this->database))->restoreForOrder($orderId, $userId);
            $nextStatus = $refund ? 'refunded' : 'cancelled';
            $nextPayment = $order['payment_status'] === 'paid' ? 'refunded' : $order['payment_status'];
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
            $this->reverseVoucherUsage($orderId);
            if ($nextPayment === 'refunded') {
                $this->database->prepare(
                    'UPDATE payments SET status = \'refunded\', transaction_ref = :reason WHERE order_id = :id AND status = \'paid\''
                )->execute(['reason' => mb_substr(trim($reason), 0, 150, 'UTF-8'), 'id' => $orderId]);
            }
            AuditLog::record($userId, $refund ? 'order_refunded' : 'order_cancelled', 'order', $orderId, trim($reason), $this->database);
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function transition(int $orderId, int $userId, string $nextStatus, string $note, bool $canManage): void
    {
        $allowed = [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['processing', 'cancelled'],
            'processing' => ['shipping', 'cancelled'],
            'shipping' => ['completed', 'refunded'],
            'completed' => ['refunded'],
        ];
        $this->database->beginTransaction();
        try {
            $query = $this->database->prepare('SELECT * FROM orders WHERE id = :id FOR UPDATE');
            $query->execute(['id' => $orderId]);
            $order = $query->fetch();
            if (!$order || !in_array($nextStatus, $allowed[$order['status']] ?? [], true)) {
                throw new InventoryException('Không thể chuyển đơn hàng sang trạng thái đã chọn.');
            }
            if (!$canManage && !in_array($nextStatus, ['processing', 'shipping', 'completed'], true)) {
                throw new InventoryException('Nhân viên kho chỉ được chuyển bước đóng gói/giao hàng.');
            }
            if (in_array($nextStatus, ['cancelled', 'refunded'], true)) {
                if (trim($note) === '') {
                    throw new InvalidArgumentException('Bắt buộc nhập lý do hủy hoặc hoàn trả.');
                }
                (new InventoryService($this->database))->restoreForOrder($orderId, $userId);
                $this->reverseVoucherUsage($orderId);
            }
            $paymentStatus = $order['payment_status'];
            if ($nextStatus === 'completed') {
                $paymentStatus = 'paid';
                $this->database->prepare(
                    'UPDATE payments SET status = \'paid\', paid_at = NOW()
                     WHERE order_id = :id AND method = \'cod\' AND status = \'pending\''
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
            AuditLog::record($userId, 'order_status_changed', 'order', $orderId, $order['status'] . ' -> ' . $nextStatus, $this->database);
            $this->database->commit();
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
