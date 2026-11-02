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

    public function notifyOrderStatus(int $userId, int $orderId, int $historyId, string $orderNo, string $status): void
    {
        $labels = [
            'confirmed' => 'đã được xác nhận',
            'processing' => 'đang được chuẩn bị',
            'shipping' => 'đang được giao',
            'completed' => 'đã giao thành công',
            'cancelled' => 'đã bị hủy',
            'refunded' => 'đã được hoàn tiền',
            'pending' => 'đang chờ xử lý',
        ];
        $label = $labels[$status] ?? 'đã được cập nhật';
        $this->database->prepare(
            'INSERT IGNORE INTO notifications
                (user_id, title, message, link_url, source_type, source_id)
             VALUES (:user_id, :title, :message, :link, \'order_status\', :source_id)'
        )->execute([
            'user_id' => $userId,
            'title' => 'Cập nhật đơn hàng',
            'message' => 'Đơn hàng ' . $orderNo . ' ' . $label . '.',
            'link' => '/tai-khoan/don-hang/' . $orderId,
            'source_id' => $historyId,
        ]);
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
