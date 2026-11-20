<?php

declare(strict_types=1);

final class SupportTicketService
{
    private PDO $database;

    private const CATEGORIES = [
        'don_hang' => 'Đơn hàng',
        'san_pham' => 'Sản phẩm',
        'thanh_toan' => 'Thanh toán',
        'giao_hang' => 'Giao hàng',
        'khac' => 'Khác',
    ];

    public function __construct(?PDO $database = null)
    {
        $this->database = $database ?? Database::connection();
    }

    public static function categories(): array
    {
        return self::CATEGORIES;
    }

    public static function statuses(): array
    {
        return [
            'open' => 'Đang mở',
            'answered' => 'Đã phản hồi',
            'closed' => 'Đã đóng',
        ];
    }

    public function ticketsForUser(int $userId): array
    {
        $query = $this->database->prepare(
            'SELECT t.*, o.order_no
             FROM support_tickets t
             LEFT JOIN orders o ON o.id = t.order_id
             WHERE t.user_id = :user_id
             ORDER BY t.updated_at DESC, t.id DESC'
        );
        $query->execute(['user_id' => $userId]);
        return $query->fetchAll();
    }

    public function ordersForUser(int $userId): array
    {
        $query = $this->database->prepare(
            'SELECT id, order_no, placed_at FROM orders
             WHERE user_id = :user_id ORDER BY placed_at DESC, id DESC'
        );
        $query->execute(['user_id' => $userId]);
        return $query->fetchAll();
    }

    public function openTicketCount(): int
    {
        return (int) $this->database->query(
            "SELECT COUNT(*) FROM support_tickets WHERE status = 'open'"
        )->fetchColumn();
    }

    public function adminTickets(array $filters, int $requestedPage, int $perPage = 20): array
    {
        $conditions = [];
        $parameters = [];
        $status = (string) ($filters['status'] ?? '');
        if (isset(self::statuses()[$status])) {
            $conditions[] = 't.status = :status';
            $parameters['status'] = $status;
        } else {
            $status = '';
        }

        $category = (string) ($filters['category'] ?? '');
        if (isset(self::CATEGORIES[$category])) {
            $conditions[] = 't.category = :category';
            $parameters['category'] = $category;
        } else {
            $category = '';
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $ticketNumber = preg_match('/\A#?(\d+)\z/u', $search, $matches) === 1
                ? (int) $matches[1]
                : 0;
            $conditions[] = '(t.id = :ticket_id OR u.name LIKE :customer_name OR u.email LIKE :customer_email)';
            $parameters['ticket_id'] = $ticketNumber;
            $parameters['customer_name'] = '%' . $search . '%';
            $parameters['customer_email'] = '%' . $search . '%';
        }

        $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
        $count = $this->database->prepare(
            'SELECT COUNT(*) FROM support_tickets t JOIN users u ON u.id = t.user_id' . $where
        );
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $perPage = min(max($perPage, 1), 100);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max($requestedPage, 1), $pages);

        $query = $this->database->prepare(
            'SELECT t.id, t.subject, t.category, t.status, t.order_id, t.created_at, t.updated_at,
                    u.name AS customer_name, u.email AS customer_email, o.order_no
             FROM support_tickets t
             JOIN users u ON u.id = t.user_id
             LEFT JOIN orders o ON o.id = t.order_id'
            . $where
            . ' ORDER BY (t.status = \'open\') DESC, t.updated_at DESC, t.id DESC
               LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $name => $value) {
            $query->bindValue(':' . $name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $query->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $query->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $query->execute();

        return [
            'tickets' => $query->fetchAll(),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'filters' => ['status' => $status, 'category' => $category, 'q' => $search],
        ];
    }

    public function adminConversation(int $ticketId): array
    {
        $ticketQuery = $this->database->prepare(
            'SELECT t.*, u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone,
                    o.order_no
             FROM support_tickets t
             JOIN users u ON u.id = t.user_id
             LEFT JOIN orders o ON o.id = t.order_id
             WHERE t.id = :ticket_id LIMIT 1'
        );
        $ticketQuery->execute(['ticket_id' => $ticketId]);
        $ticket = $ticketQuery->fetch();
        if (!$ticket) {
            throw new HttpException(404, 'Không tìm thấy phiếu hỗ trợ.');
        }

        $messagesQuery = $this->database->prepare(
            "SELECT m.sender_type, m.body, m.created_at,
                    CASE WHEN m.sender_type = 'customer' THEN u.name ELSE 'Nông Sản Sạch' END AS sender_name
             FROM support_messages m
             LEFT JOIN users u ON m.sender_type = 'customer' AND u.id = m.sender_id
             WHERE m.ticket_id = :ticket_id
             ORDER BY m.created_at ASC, m.id ASC"
        );
        $messagesQuery->execute(['ticket_id' => $ticketId]);
        return ['ticket' => $ticket, 'messages' => $messagesQuery->fetchAll()];
    }

    public function adminReply(int $ticketId, int $adminId, mixed $body): int
    {
        if (!is_string($body)) {
            throw new InvalidArgumentException('Nội dung trả lời không hợp lệ.');
        }
        $body = trim($body);
        $length = mb_strlen($body, 'UTF-8');
        if ($length < 2 || $length > 3000) {
            throw new InvalidArgumentException('Nội dung trả lời phải có từ 2 đến 3000 ký tự.');
        }
        if ($adminId < 1) {
            throw new InvalidArgumentException('Tài khoản quản trị không hợp lệ.');
        }

        $this->database->beginTransaction();
        try {
            $ticket = $this->lockAdminTicket($ticketId);
            if ($ticket['status'] === 'closed') {
                throw new InvalidArgumentException('Phiếu đã đóng. Hãy mở lại phiếu trước khi trả lời.');
            }

            $this->database->prepare(
                "INSERT INTO support_messages (ticket_id, sender_type, sender_id, body)
                 VALUES (:ticket_id, 'admin', :sender_id, :body)"
            )->execute([
                'ticket_id' => $ticketId,
                'sender_id' => $adminId,
                'body' => $body,
            ]);
            $messageId = (int) $this->database->lastInsertId();
            $this->database->prepare(
                "UPDATE support_tickets SET status = 'answered', closed_at = NULL,
                    updated_at = CURRENT_TIMESTAMP WHERE id = :ticket_id"
            )->execute(['ticket_id' => $ticketId]);

            $notifications = new NotificationService($this->database);
            $notifications->notifySupportTicketReply((int) $ticket['user_id']);
            AuditLog::record(
                $adminId,
                'support_ticket_replied',
                'support_ticket',
                $ticketId,
                'message_id=' . $messageId . '; status=answered',
                $this->database
            );
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }

        $notifications->sendSupportTicketReplyEmail((int) $ticket['user_id'], $ticketId);
        return $messageId;
    }

    public function adminSetStatus(int $ticketId, int $adminId, string $status): bool
    {
        if (!isset(self::statuses()[$status])) {
            throw new InvalidArgumentException('Trạng thái phiếu hỗ trợ không hợp lệ.');
        }
        if ($adminId < 1) {
            throw new InvalidArgumentException('Tài khoản quản trị không hợp lệ.');
        }

        $this->database->beginTransaction();
        try {
            $ticket = $this->lockAdminTicket($ticketId);
            if ($ticket['status'] === $status) {
                $this->database->commit();
                return false;
            }

            $this->database->prepare(
                'UPDATE support_tickets
                 SET status = :status,
                     closed_at = CASE WHEN :is_closed = 1
                         THEN COALESCE(closed_at, CURRENT_TIMESTAMP) ELSE NULL END,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :ticket_id'
            )->execute([
                'status' => $status,
                'is_closed' => $status === 'closed' ? 1 : 0,
                'ticket_id' => $ticketId,
            ]);
            AuditLog::record(
                $adminId,
                'support_ticket_status_changed',
                'support_ticket',
                $ticketId,
                $ticket['status'] . ' -> ' . $status,
                $this->database
            );
            $this->database->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function conversationForUser(int $ticketId, int $userId): array
    {
        $ticketQuery = $this->database->prepare(
            'SELECT t.*, o.order_no
             FROM support_tickets t
             LEFT JOIN orders o ON o.id = t.order_id
             WHERE t.id = :ticket_id AND t.user_id = :user_id
             LIMIT 1'
        );
        $ticketQuery->execute(['ticket_id' => $ticketId, 'user_id' => $userId]);
        $ticket = $ticketQuery->fetch();
        if (!$ticket) {
            throw new HttpException(404, 'Không tìm thấy phiếu hỗ trợ.');
        }

        $messagesQuery = $this->database->prepare(
            'SELECT sender_type, body, created_at
             FROM support_messages WHERE ticket_id = :ticket_id
             ORDER BY created_at ASC, id ASC'
        );
        $messagesQuery->execute(['ticket_id' => $ticketId]);
        return ['ticket' => $ticket, 'messages' => $messagesQuery->fetchAll()];
    }

    public function create(int $userId, array $input): int
    {
        if ($userId < 1) {
            throw new InvalidArgumentException('Tài khoản không hợp lệ.');
        }

        $subject = $this->stringInput($input, 'subject', 'Tiêu đề');
        $category = $this->stringInput($input, 'category', 'Loại hỗ trợ');
        $body = $this->stringInput($input, 'body', 'Nội dung');
        $subject = trim($subject);
        $body = trim($body);
        $this->validateSubject($subject);
        $this->validateBody($body);
        if (!isset(self::CATEGORIES[$category])) {
            throw new InvalidArgumentException('Vui lòng chọn loại hỗ trợ hợp lệ.');
        }

        $rawOrderId = $input['order_id'] ?? '';
        if (!is_scalar($rawOrderId)) {
            throw new InvalidArgumentException('Đơn hàng liên quan không hợp lệ.');
        }
        $rawOrderId = trim((string) $rawOrderId);
        $orderId = null;
        if ($rawOrderId !== '') {
            $validatedOrderId = filter_var($rawOrderId, FILTER_VALIDATE_INT);
            if ($validatedOrderId === false || $validatedOrderId < 1) {
                throw new InvalidArgumentException('Đơn hàng liên quan không hợp lệ.');
            }
            $orderId = (int) $validatedOrderId;
        }

        $ownsTransaction = !$this->database->inTransaction();
        if ($ownsTransaction) {
            $this->database->beginTransaction();
        }
        try {
            $userQuery = $this->database->prepare('SELECT id FROM users WHERE id = :user_id FOR UPDATE');
            $userQuery->execute(['user_id' => $userId]);
            if (!$userQuery->fetchColumn()) {
                throw new InvalidArgumentException('Tài khoản không hợp lệ.');
            }

            if ($orderId !== null) {
                $orderQuery = $this->database->prepare(
                    'SELECT id FROM orders WHERE id = :order_id AND user_id = :user_id LIMIT 1'
                );
                $orderQuery->execute(['order_id' => $orderId, 'user_id' => $userId]);
                if (!$orderQuery->fetchColumn()) {
                    throw new InvalidArgumentException('Đơn hàng không tồn tại hoặc không thuộc tài khoản của bạn.');
                }
            }

            $recentCount = $this->database->prepare(
                'SELECT COUNT(*) FROM support_tickets
                 WHERE user_id = :user_id AND created_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 HOUR)'
            );
            $recentCount->execute(['user_id' => $userId]);
            if ((int) $recentCount->fetchColumn() >= 5) {
                throw new InvalidArgumentException('Bạn đã tạo tối đa 5 phiếu trong 1 giờ. Vui lòng thử lại sau.');
            }

            $openCount = $this->database->prepare(
                "SELECT COUNT(*) FROM support_tickets WHERE user_id = :user_id AND status = 'open'"
            );
            $openCount->execute(['user_id' => $userId]);
            if ((int) $openCount->fetchColumn() >= 3) {
                throw new InvalidArgumentException('Bạn đang có tối đa 3 phiếu mở. Hãy chờ phản hồi hoặc đóng phiếu hiện có.');
            }

            $this->database->prepare(
                'INSERT INTO support_tickets (user_id, order_id, subject, category)
                 VALUES (:user_id, :order_id, :subject, :category)'
            )->execute([
                'user_id' => $userId,
                'order_id' => $orderId,
                'subject' => $subject,
                'category' => $category,
            ]);
            $ticketId = (int) $this->database->lastInsertId();
            $this->database->prepare(
                "INSERT INTO support_messages (ticket_id, sender_type, sender_id, body)
                 VALUES (:ticket_id, 'customer', :sender_id, :body)"
            )->execute(['ticket_id' => $ticketId, 'sender_id' => $userId, 'body' => $body]);

            if ($ownsTransaction) {
                $this->database->commit();
            }
            return $ticketId;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function reply(int $ticketId, int $userId, mixed $body): void
    {
        $this->database->beginTransaction();
        try {
            $ticket = $this->lockOwnedTicket($ticketId, $userId);
            if ($ticket['status'] === 'closed') {
                throw new InvalidArgumentException('Phiếu đã đóng nên không thể gửi trả lời.');
            }
            if (!is_string($body)) {
                throw new InvalidArgumentException('Nội dung không hợp lệ.');
            }
            $body = trim($body);
            $this->validateBody($body);

            $this->database->prepare(
                "INSERT INTO support_messages (ticket_id, sender_type, sender_id, body)
                 VALUES (:ticket_id, 'customer', :sender_id, :body)"
            )->execute(['ticket_id' => $ticketId, 'sender_id' => $userId, 'body' => $body]);
            $status = $ticket['status'] === 'answered' ? 'open' : $ticket['status'];
            $this->database->prepare(
                'UPDATE support_tickets SET status = :status, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :ticket_id'
            )->execute(['status' => $status, 'ticket_id' => $ticketId]);
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function close(int $ticketId, int $userId): void
    {
        $this->database->beginTransaction();
        try {
            $this->lockOwnedTicket($ticketId, $userId);
            $this->database->prepare(
                "UPDATE support_tickets
                 SET status = 'closed', closed_at = COALESCE(closed_at, CURRENT_TIMESTAMP),
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :ticket_id"
            )->execute(['ticket_id' => $ticketId]);
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    private function lockOwnedTicket(int $ticketId, int $userId): array
    {
        $query = $this->database->prepare(
            'SELECT id, status FROM support_tickets
             WHERE id = :ticket_id AND user_id = :user_id LIMIT 1 FOR UPDATE'
        );
        $query->execute(['ticket_id' => $ticketId, 'user_id' => $userId]);
        $ticket = $query->fetch();
        if (!$ticket) {
            throw new HttpException(404, 'Không tìm thấy phiếu hỗ trợ.');
        }
        return $ticket;
    }

    private function lockAdminTicket(int $ticketId): array
    {
        $query = $this->database->prepare(
            'SELECT id, user_id, status FROM support_tickets
             WHERE id = :ticket_id LIMIT 1 FOR UPDATE'
        );
        $query->execute(['ticket_id' => $ticketId]);
        $ticket = $query->fetch();
        if (!$ticket) {
            throw new HttpException(404, 'Không tìm thấy phiếu hỗ trợ.');
        }
        return $ticket;
    }

    private function stringInput(array $input, string $key, string $label): string
    {
        if (!isset($input[$key]) || !is_string($input[$key])) {
            throw new InvalidArgumentException($label . ' không hợp lệ.');
        }
        return $input[$key];
    }

    private function validateSubject(string $subject): void
    {
        $length = mb_strlen($subject, 'UTF-8');
        if ($length < 5 || $length > 150) {
            throw new InvalidArgumentException('Tiêu đề phải có từ 5 đến 150 ký tự.');
        }
    }

    private function validateBody(string $body): void
    {
        $length = mb_strlen($body, 'UTF-8');
        if ($length < 10 || $length > 3000) {
            throw new InvalidArgumentException('Nội dung phải có từ 10 đến 3000 ký tự.');
        }
    }
}
