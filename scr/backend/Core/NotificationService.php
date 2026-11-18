<?php

declare(strict_types=1);

final class NotificationService
{
    public function __construct(private ?PDO $database = null)
    {
        $this->database ??= Database::connection();
    }

    public function forUser(int $userId, int $limit = 20): array
    {
        $this->announceFlashSales($userId);
        $limit = min(max($limit, 1), 50);
        $statement = $this->database->prepare(
            'SELECT id, title, message, link_url, read_at, created_at
             FROM notifications WHERE user_id = :user_id
             ORDER BY created_at DESC, id DESC LIMIT :limit'
        );
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        $items = $statement->fetchAll();
        $unread = $this->database->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND read_at IS NULL');
        $unread->execute(['user_id' => $userId]);

        return ['items' => $items, 'unread_count' => (int) $unread->fetchColumn()];
    }

    public function forUserPage(int $userId, int $page, int $perPage = 20): array
    {
        $this->announceFlashSales($userId);
        $perPage = min(max($perPage, 1), 50);
        $count = $this->database->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :user_id');
        $count->execute(['user_id' => $userId]);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max($page, 1), $pages);
        $statement = $this->database->prepare(
            'SELECT id, title, message, link_url, read_at, created_at
             FROM notifications WHERE user_id = :user_id
             ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();
        $items = $statement->fetchAll();
        $unread = $this->database->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND read_at IS NULL');
        $unread->execute(['user_id' => $userId]);

        return [
            'items' => $items,
            'unread_count' => (int) $unread->fetchColumn(),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ];
    }

    public function markRead(int $userId, int $notificationId): bool
    {
        $statement = $this->database->prepare(
            'UPDATE notifications SET read_at = NOW()
             WHERE id = :id AND user_id = :user_id AND read_at IS NULL'
        );
        $statement->execute(['id' => $notificationId, 'user_id' => $userId]);
        return $statement->rowCount() > 0;
    }

    public function markAllRead(int $userId): int
    {
        $statement = $this->database->prepare(
            'UPDATE notifications SET read_at = NOW()
             WHERE user_id = :user_id AND read_at IS NULL'
        );
        $statement->execute(['user_id' => $userId]);
        return $statement->rowCount();
    }

    public function deleteForUser(int $userId, int $notificationId): bool
    {
        $statement = $this->database->prepare(
            'DELETE FROM notifications WHERE id = :id AND user_id = :user_id'
        );
        $statement->execute(['id' => $notificationId, 'user_id' => $userId]);
        return $statement->rowCount() > 0;
    }

    public function deleteReadForUser(int $userId): int
    {
        $statement = $this->database->prepare(
            'DELETE FROM notifications WHERE user_id = :user_id AND read_at IS NOT NULL'
        );
        $statement->execute(['user_id' => $userId]);
        return $statement->rowCount();
    }

    public function deleteAllForUser(int $userId): int
    {
        $statement = $this->database->prepare('DELETE FROM notifications WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);
        return $statement->rowCount();
    }

    public function pageAfterDelete(int $userId, int $page, int $perPage = 20): int
    {
        $perPage = min(max($perPage, 1), 50);
        $count = $this->database->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :user_id');
        $count->execute(['user_id' => $userId]);
        $pages = max(1, (int) ceil((int) $count->fetchColumn() / $perPage));
        return min(max(1, $page), $pages);
    }

    public function notifyOrderPlaced(
        int $userId,
        int $orderId,
        int $historyId,
        string $orderNo,
        string $paymentMethod,
        int $holdMinutes
    ): void {
        $online = in_array($paymentMethod, ['bank_transfer', 'vnpay'], true);
        $paymentLabels = ['bank_transfer' => 'VietQR', 'vnpay' => 'VNPay'];
        $title = $online ? 'Đơn hàng đang chờ thanh toán' : 'Đặt hàng thành công';
        $message = $online
            ? 'Đơn ' . $orderNo . ' đang chờ thanh toán qua ' . $paymentLabels[$paymentMethod]
                . '. Vui lòng hoàn tất trong ' . max(1, $holdMinutes) . ' phút để giữ hàng.'
            : 'Đơn hàng ' . $orderNo . ' đã được tiếp nhận.';
        $this->insertOrderNotification($userId, $orderId, $historyId, $title, $message);
    }

    public function notifyOrderPaid(int $userId, int $orderId, int $historyId, string $orderNo): void
    {
        $this->insertOrderNotification(
            $userId,
            $orderId,
            $historyId,
            'Đặt hàng thành công',
            'Đơn hàng ' . $orderNo . ' đã được thanh toán và xác nhận.'
        );
    }

    public function notifyOrderExpired(int $userId, int $orderId, int $historyId, string $orderNo): void
    {
        $this->insertOrderNotification(
            $userId,
            $orderId,
            $historyId,
            'Đơn hàng đã hết hạn giữ hàng',
            'Đơn hàng ' . $orderNo . ' đã hết hạn giữ hàng do chưa hoàn tất thanh toán.'
        );
    }

    public function notifyPaymentIncomplete(
        int $userId,
        int $orderId,
        int $paymentId,
        string $orderNo,
        string $paymentMethod
    ): void {
        $method = $paymentMethod === 'vnpay' ? 'VNPay' : 'VietQR';
        $this->database->prepare(
            'INSERT IGNORE INTO notifications
                (user_id, title, message, link_url, source_type, source_id)
             VALUES (:user_id, :title, :message, :link, \'payment_failure\', :source_id)'
        )->execute([
            'user_id' => $userId,
            'title' => 'Thanh toán chưa hoàn tất',
            'message' => 'Thanh toán đơn ' . $orderNo . ' qua ' . $method
                . ' chưa hoàn tất hoặc đã bị hủy. Bạn có thể thanh toán lại tại trang đơn hàng.',
            'link' => '/tai-khoan/don-hang/' . $orderId,
            'source_id' => $paymentId,
        ]);
    }

    public function notifyAdminNewOrder(int $orderId, string $orderNo, bool $prepaid): void
    {
        $admins = $this->database->query(
            "SELECT id FROM users
             WHERE status = 'active' AND role IN ('admin', 'staff_sales', 'staff_warehouse')"
        )->fetchAll(PDO::FETCH_COLUMN);
        $statement = $this->database->prepare(
            'INSERT IGNORE INTO notifications
                (user_id, title, message, link_url, source_type, source_id)
             VALUES (:user_id, :title, :message, :link, \'admin_new_order\', :source_id)'
        );
        foreach ($admins as $adminId) {
            $statement->execute([
                'user_id' => (int) $adminId,
                'title' => 'Đơn hàng mới',
                'message' => $prepaid
                    ? 'Đơn ' . $orderNo . ' đã thanh toán và đang chờ xử lý.'
                    : 'Đơn ' . $orderNo . ' mới, đang chờ xác nhận.',
                'link' => '/admin/orders/' . $orderId,
                'source_id' => $orderId,
            ]);
        }
    }

    public function notifyOrderStatus(int $userId, int $orderId, int $historyId, string $orderNo, string $status): void
    {
        $labels = [
            'confirmed' => 'đã được xác nhận',
            'processing' => 'đang được chuẩn bị',
            'shipping' => 'đang được giao',
            'completed' => 'đã giao thành công',
            'cancelled' => 'đã bị hủy',
            'refunded' => 'đã được hoàn tiền',
            'delivery_failed' => 'giao không thành công',
            'pending' => 'đang chờ xử lý',
        ];
        $label = $labels[$status] ?? 'đã được cập nhật';
        $message = $status === 'shipping'
            ? 'Đơn hàng đang trên đường giao. Khi nhận được hàng, vui lòng xác nhận đã nhận hàng.'
            : 'Đơn hàng ' . $orderNo . ' ' . $label . '.';
        $this->database->prepare(
            'INSERT IGNORE INTO notifications
                (user_id, title, message, link_url, source_type, source_id)
             VALUES (:user_id, :title, :message, :link, \'order_status\', :source_id)'
        )->execute([
            'user_id' => $userId,
            'title' => 'Cập nhật đơn hàng',
            'message' => $message,
            'link' => '/tai-khoan/don-hang/' . $orderId,
            'source_id' => $historyId,
        ]);
    }

    public function notifyOrderReturnEvent(
        int $userId,
        int $returnId,
        int $orderId,
        string $event,
        string $title,
        string $message
    ): bool {
        $allowedEvents = ['requested', 'approved', 'rejected', 'refunded', 'cancelled'];
        if (!in_array($event, $allowedEvents, true)) {
            throw new InvalidArgumentException('Sự kiện trả hàng không hợp lệ.');
        }
        $statement = $this->database->prepare(
            'INSERT IGNORE INTO notifications
                (user_id, title, message, link_url, source_type, source_id)
             VALUES (:user_id, :title, :message, :link, :source_type, :source_id)'
        );
        $statement->execute([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'link' => '/tai-khoan/yeu-cau-tra-hang/' . $returnId,
            'source_type' => 'order_return_' . $event,
            'source_id' => $returnId,
        ]);
        return $statement->rowCount() > 0;
    }

    public function notifyAdminsNewOrderReturn(int $returnId, int $orderId, string $orderNo): void
    {
        $admins = $this->database->prepare(
            "SELECT id FROM users
             WHERE status = 'active' AND role IN ('admin', 'staff_sales')"
        );
        $admins->execute();
        $statement = $this->database->prepare(
            'INSERT IGNORE INTO notifications
                (user_id, title, message, link_url, source_type, source_id)
             VALUES (:user_id, :title, :message, :link, \'admin_order_return\', :source_id)'
        );
        foreach ($admins->fetchAll(PDO::FETCH_COLUMN) as $adminId) {
            $statement->execute([
                'user_id' => (int) $adminId,
                'title' => 'Có yêu cầu trả hàng mới',
                'message' => 'Khách hàng vừa gửi yêu cầu trả hàng/hoàn tiền cho đơn ' . $orderNo . '.',
                'link' => '/admin/returns/' . $returnId,
                'source_id' => $returnId,
            ]);
        }
    }

    public function sendOrderReturnEmail(
        int $userId,
        int $returnId,
        string $orderNo,
        string $event,
        string $title,
        string $message
    ): void {
        try {
            $query = $this->database->prepare('SELECT name, email FROM users WHERE id = :id LIMIT 1');
            $query->execute(['id' => $userId]);
            $user = $query->fetch();
            if (!$user) {
                return;
            }
            if (!(new MailRateLimiter($this->database))->allow(
                'order_return_' . $event,
                (string) $user['email'],
                (string) ($_SERVER['REMOTE_ADDR'] ?? '')
            )) {
                error_log('Order return email rate limit reached (return=' . $returnId . ').');
                return;
            }
            $result = (new MailService($this->database))->sendTemplate(
                (string) $user['email'],
                $title . ' - đơn ' . $orderNo,
                $title,
                ['Xin chào ' . $user['name'] . '.', $message],
                'Xem yêu cầu',
                absolute_url('/tai-khoan/yeu-cau-tra-hang/' . $returnId),
                ['type' => 'order_return_' . $event, 'return_id' => $returnId]
            );
            if (!$result['accepted']) {
                error_log('Order return email could not be queued (return=' . $returnId . ').');
            }
        } catch (Throwable) {
            error_log('Order return email could not be queued (return=' . $returnId . ').');
        }
    }

    private function insertOrderNotification(
        int $userId,
        int $orderId,
        int $historyId,
        string $title,
        string $message
    ): void {
        $this->database->prepare(
            'INSERT IGNORE INTO notifications
                (user_id, title, message, link_url, source_type, source_id)
             VALUES (:user_id, :title, :message, :link, \'order_status\', :source_id)'
        )->execute([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'link' => '/tai-khoan/don-hang/' . $orderId,
            'source_id' => $historyId,
        ]);
    }

    public function notifySupportTicketReply(int $userId, int $ticketId): void
    {
        $this->database->prepare(
            'INSERT INTO notifications (user_id, title, message, link_url)
             VALUES (:user_id, :title, :message, :link)'
        )->execute([
            'user_id' => $userId,
            'title' => 'Phiếu hỗ trợ đã có phản hồi',
            'message' => 'Phiếu hỗ trợ #' . $ticketId . ' đã có phản hồi từ Nông Sản Sạch.',
            'link' => '/ho-tro/' . $ticketId,
        ]);
    }

    public function notifyCodRiskOnce(int $userId): void
    {
        $title = 'Các đơn tiếp theo cần thanh toán trước';
        $message = 'Do các đơn trước không nhận hàng, các đơn tiếp theo cần thanh toán trước (VietQR/VNPay). Cần hỗ trợ, vui lòng tạo phiếu hỗ trợ.';
        $existing = $this->database->prepare(
            'SELECT id FROM notifications
             WHERE user_id = :user_id AND title = :title AND message = :message LIMIT 1'
        );
        $existing->execute(['user_id' => $userId, 'title' => $title, 'message' => $message]);
        if ($existing->fetchColumn() !== false) {
            return;
        }
        $this->database->prepare(
            'INSERT INTO notifications (user_id, title, message, link_url)
             VALUES (:user_id, :title, :message, \'/ho-tro\')'
        )->execute(['user_id' => $userId, 'title' => $title, 'message' => $message]);
    }

    public function notifyVoucherGranted(int $userId, array $voucher): void
    {
        $discount = $voucher['discount_type'] === 'percent'
            ? 'giảm ' . number_format((float) $voucher['discount_value'], 0, ',', '.') . '%'
                . ($voucher['maximum_discount'] === null
                    ? ''
                    : ' tối đa ' . number_format((float) $voucher['maximum_discount'], 0, ',', '.') . ' đ')
            : 'giảm ' . number_format((float) $voucher['discount_value'], 0, ',', '.') . ' đ';
        $expiry = $voucher['expires_at'] ?? $voucher['ends_at'];
        $expiryDate = date('d/m/Y', strtotime((string) $expiry));
        $message = 'Bạn nhận được voucher ' . $voucher['code'] . ': ' . $discount
            . ', hạn dùng đến ' . $expiryDate . '.';
        $this->database->prepare(
            'INSERT INTO notifications (user_id, title, message, link_url)
            VALUES (:user_id, :title, :message, \'/voucher-cua-toi\')'
        )->execute([
            'user_id' => $userId,
            'title' => 'Bạn nhận được voucher',
            'message' => $message,
        ]);
    }

    public function sendSupportTicketReplyEmail(int $userId, int $ticketId): void
    {
        try {
            $query = $this->database->prepare('SELECT name, email FROM users WHERE id = :id LIMIT 1');
            $query->execute(['id' => $userId]);
            $user = $query->fetch();
            if (!$user) {
                return;
            }
            if (!(new MailRateLimiter($this->database))->allow(
                'support_ticket_reply',
                (string) $user['email'],
                (string) ($_SERVER['REMOTE_ADDR'] ?? '')
            )) {
                error_log('Support ticket reply email rate limit reached (ticket=' . $ticketId . ').');
                return;
            }

            $result = (new MailService($this->database))->sendTemplate(
                (string) $user['email'],
                'Phiếu hỗ trợ #' . $ticketId . ' đã có phản hồi',
                'Phiếu hỗ trợ đã có phản hồi',
                [
                    'Xin chào ' . $user['name'] . '.',
                    'Phiếu hỗ trợ #' . $ticketId . ' đã có phản hồi từ Nông Sản Sạch.',
                ],
                'Xem phiếu hỗ trợ',
                absolute_url('/ho-tro/' . $ticketId),
                ['type' => 'support_ticket_reply', 'ticket_id' => $ticketId]
            );
            if (!$result['accepted']) {
                error_log('Support ticket reply email could not be queued (ticket=' . $ticketId . ').');
            }
        } catch (Throwable) {
            error_log('Support ticket reply email could not be queued (ticket=' . $ticketId . ').');
        }
    }

    public function sendOrderStatusEmail(int $userId, int $orderId, string $orderNo, string $status): void
    {
        $labels = [
            'confirmed' => 'đã được xác nhận',
            'processing' => 'đang được chuẩn bị',
            'shipping' => 'đang được giao',
            'completed' => 'đã giao thành công',
            'cancelled' => 'đã bị hủy',
            'refunded' => 'đã được hoàn tiền',
            'delivery_failed' => 'giao không thành công',
            'payment_expired' => 'đã hết hạn giữ hàng và bị hủy do chưa thanh toán',
        ];
        try {
            $query = $this->database->prepare('SELECT name, email FROM users WHERE id = :id LIMIT 1');
            $query->execute(['id' => $userId]);
            $user = $query->fetch();
            if (!$user) {
                return;
            }
            $result = (new MailService($this->database))->sendTemplate(
                (string) $user['email'],
                'Cập nhật đơn hàng ' . $orderNo . ' - Nông Sản Sạch',
                'Đơn hàng được cập nhật',
                [
                    'Xin chào ' . $user['name'] . '.',
                    'Đơn hàng ' . $orderNo . ' ' . ($labels[$status] ?? 'đã được cập nhật') . '.',
                ],
                'Theo dõi đơn hàng',
                absolute_url('/tai-khoan/don-hang/' . $orderId),
                ['type' => 'order_status', 'order_id' => $orderId, 'status' => $status]
            );
            if (!$result['accepted']) {
                error_log('Order status email could not be queued (order=' . $orderId . ').');
            }
        } catch (Throwable) {
            error_log('Order status email could not be queued (order=' . $orderId . ').');
        }
    }

    public function sendPaidOrderEmail(int $userId, int $orderId, string $orderNo): void
    {
        try {
            $query = $this->database->prepare('SELECT name, email FROM users WHERE id = :id LIMIT 1');
            $query->execute(['id' => $userId]);
            $user = $query->fetch();
            if (!$user) {
                return;
            }
            $result = (new MailService($this->database))->sendTemplate(
                (string) $user['email'],
                'Đặt hàng thành công - ' . $orderNo,
                'Đặt hàng thành công',
                [
                    'Xin chào ' . $user['name'] . '.',
                    'Đơn hàng ' . $orderNo . ' đã được thanh toán và xác nhận.',
                ],
                'Theo dõi đơn hàng',
                absolute_url('/tai-khoan/don-hang/' . $orderId),
                ['type' => 'paid_order_confirmation', 'order_id' => $orderId]
            );
            if (!$result['accepted']) {
                error_log('Paid order confirmation email could not be queued (order=' . $orderId . ').');
            }
        } catch (Throwable) {
            error_log('Paid order confirmation email could not be queued (order=' . $orderId . ').');
        }
    }

    private function announceFlashSales(int $userId): void
    {
        $this->database->prepare(
            "INSERT IGNORE INTO notifications
                (user_id, title, message, link_url, source_type, source_id)
             SELECT DISTINCT w.user_id, 'Flash Sale đang diễn ra',
                    CONCAT('Flash Sale ', fs.name, ' đang có sản phẩm bạn yêu thích.'),
                    '/flash-sale', 'flash_sale', fs.id
             FROM wishlists w
             JOIN flash_sale_items fsi ON fsi.product_id = w.product_id
             JOIN flash_sales fs ON fs.id = fsi.flash_sale_id
             WHERE w.user_id = :user_id AND fs.status NOT IN ('draft','cancelled')
               AND fsi.sold_quantity < fsi.quota
               AND fs.starts_at <= DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 HOUR)
               AND fs.ends_at > DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 HOUR)"
        )->execute(['user_id' => $userId]);
    }
}
