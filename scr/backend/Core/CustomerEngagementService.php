<?php

declare(strict_types=1);

final class CustomerEngagementService
{
    public function __construct(private ?PDO $database = null)
    {
        $this->database ??= Database::connection();
    }

    public function reviewSummary(int $productId): array
    {
        $statement = $this->database->prepare(
            "SELECT COUNT(*) AS review_count, COALESCE(AVG(rating), 0) AS average_rating
             FROM reviews WHERE product_id = :product_id AND status = 'published'"
        );
        $statement->execute(['product_id' => $productId]);
        $summary = $statement->fetch();
        $distribution = array_fill(1, 5, 0);
        $counts = $this->database->prepare(
            "SELECT rating, COUNT(*) AS review_count FROM reviews
             WHERE product_id = :product_id AND status = 'published'
             GROUP BY rating"
        );
        $counts->execute(['product_id' => $productId]);
        foreach ($counts->fetchAll() as $row) {
            $distribution[(int) $row['rating']] = (int) $row['review_count'];
        }
        return [
            'review_count' => (int) $summary['review_count'],
            'average_rating' => round((float) $summary['average_rating'], 2),
            'distribution' => $distribution,
        ];
    }

    public function publishedReviews(int $productId, int $page = 1, int $perPage = 10): array
    {
        $count = $this->database->prepare(
            "SELECT COUNT(*) FROM reviews WHERE product_id = :product_id AND status = 'published'"
        );
        $count->execute(['product_id' => $productId]);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $statement = $this->database->prepare(
            "SELECT r.id, r.rating, r.title, r.content, r.created_at, u.name AS customer_name
             FROM reviews r JOIN users u ON u.id = r.user_id
             WHERE r.product_id = :product_id AND r.status = 'published'
             ORDER BY r.created_at DESC, r.id DESC
             LIMIT :limit OFFSET :offset"
        );
        $statement->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();
        return [
            'reviews' => $statement->fetchAll(),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ];
    }

    public function canReview(int $userId, int $productId): bool
    {
        return $this->completedOrderId($userId, $productId) !== null;
    }

    public function reviewForUser(int $userId, int $productId): ?array
    {
        $statement = $this->database->prepare(
            'SELECT id, rating, title, content, status
             FROM reviews WHERE user_id = :user_id AND product_id = :product_id
             ORDER BY id DESC LIMIT 1'
        );
        $statement->execute(['user_id' => $userId, 'product_id' => $productId]);
        $review = $statement->fetch();
        return $review ?: null;
    }

    public function saveReview(int $userId, int $productId, int $rating, string $content): array
    {
        if ($userId < 1 || $productId < 1) {
            throw new InvalidArgumentException('Tài khoản hoặc sản phẩm không hợp lệ.');
        }
        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('Số sao đánh giá phải từ 1 đến 5.');
        }
        $normalized = preg_replace('/\s+/u', ' ', trim($content));
        if ($normalized === null) {
            throw new InvalidArgumentException('Nội dung đánh giá có ký tự không hợp lệ.');
        }
        if ($normalized === '' || mb_strlen($normalized, 'UTF-8') > 2000) {
            throw new InvalidArgumentException('Nội dung đánh giá là bắt buộc và tối đa 2000 ký tự.');
        }

        $this->database->beginTransaction();
        try {
            $product = $this->database->prepare(
                "SELECT id FROM products
                 WHERE id = :product_id AND status = 'active' AND deleted_at IS NULL
                 FOR UPDATE"
            );
            $product->execute(['product_id' => $productId]);
            if (!$product->fetchColumn()) {
                throw new InvalidArgumentException('Không tìm thấy sản phẩm đang được bán.');
            }
            $orderId = $this->completedOrderId($userId, $productId, true);
            if ($orderId === null) {
                throw new InvalidArgumentException('Bạn cần có đơn hàng đã giao sản phẩm này mới được đánh giá.');
            }

            $this->consumeReviewRateLimit($userId);
            $existing = $this->database->prepare(
                'SELECT id FROM reviews WHERE user_id = :user_id AND product_id = :product_id
                 ORDER BY id DESC LIMIT 1 FOR UPDATE'
            );
            $existing->execute(['user_id' => $userId, 'product_id' => $productId]);
            $reviewId = $existing->fetchColumn();
            if ($reviewId !== false) {
                $this->database->prepare(
                    "UPDATE reviews SET order_id = :order_id, rating = :rating, content = :content,
                        status = 'pending', updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id"
                )->execute([
                    'order_id' => $orderId,
                    'rating' => $rating,
                    'content' => $normalized,
                    'id' => $reviewId,
                ]);
                $created = false;
            } else {
                $this->database->prepare(
                    'INSERT INTO reviews (user_id, product_id, order_id, rating, content, status)
                     VALUES (:user_id, :product_id, :order_id, :rating, :content, \'pending\')'
                )->execute([
                    'user_id' => $userId,
                    'product_id' => $productId,
                    'order_id' => $orderId,
                    'rating' => $rating,
                    'content' => $normalized,
                ]);
                $reviewId = (int) $this->database->lastInsertId();
                $created = true;
            }
            $this->database->commit();
            return ['id' => (int) $reviewId, 'created' => $created, 'status' => 'pending'];
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function toggleWishlist(int $userId, int $productId): bool
    {
        if ($userId < 1 || $productId < 1) {
            throw new InvalidArgumentException('Tài khoản hoặc sản phẩm không hợp lệ.');
        }
        $this->database->beginTransaction();
        try {
            $product = $this->database->prepare(
                "SELECT id FROM products
                 WHERE id = :product_id AND status = 'active' AND deleted_at IS NULL
                 FOR UPDATE"
            );
            $product->execute(['product_id' => $productId]);
            if (!$product->fetchColumn()) {
                throw new InvalidArgumentException('Không tìm thấy sản phẩm đang được bán.');
            }
            $existing = $this->database->prepare(
                'SELECT product_id FROM wishlists WHERE user_id = :user_id AND product_id = :product_id FOR UPDATE'
            );
            $existing->execute(['user_id' => $userId, 'product_id' => $productId]);
            if ($existing->fetchColumn() !== false) {
                $this->database->prepare(
                    'DELETE FROM wishlists WHERE user_id = :user_id AND product_id = :product_id'
                )->execute(['user_id' => $userId, 'product_id' => $productId]);
                $wishlisted = false;
            } else {
                $this->database->prepare(
                    'INSERT INTO wishlists (user_id, product_id) VALUES (:user_id, :product_id)'
                )->execute(['user_id' => $userId, 'product_id' => $productId]);
                $wishlisted = true;
            }
            $this->database->commit();
            return $wishlisted;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function wishlistProductIds(int $userId): array
    {
        $statement = $this->database->prepare('SELECT product_id FROM wishlists WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);
        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function wishlistProducts(int $userId): array
    {
        $statement = $this->database->prepare(
            "SELECT p.id, p.name, p.slug, p.price, p.sale_price, p.unit, p.min_stock,
                    p.short_description,
                    COALESCE(NULLIF(p.sale_price, 0), p.price) AS current_price,
                    COALESCE(stock.quantity, 0) AS actual_stock,
                    COALESCE(images.image_url, '/img/product-placeholder.svg') AS image_url,
                    COALESCE(reviews.average_rating, 0) AS average_rating,
                    COALESCE(reviews.review_count, 0) AS review_count
             FROM wishlists w
             JOIN products p ON p.id = w.product_id AND p.status = 'active' AND p.deleted_at IS NULL
             LEFT JOIN (
                 SELECT b.product_id,
                        SUM(GREATEST(0, b.quantity_remaining - COALESCE(r.reserved, 0))) AS quantity
                 FROM product_batches b
                 LEFT JOIN (
                     SELECT batch_id, SUM(quantity) AS reserved
                     FROM stock_reservations
                     WHERE status = 'reserved' AND expires_at > NOW()
                     GROUP BY batch_id
                 ) r ON r.batch_id = b.id
                 WHERE b.status = 'selling' AND b.inspection_result <> 'failed'
                   AND (b.expires_at IS NULL OR b.expires_at >= CURDATE())
                 GROUP BY b.product_id
             ) stock ON stock.product_id = p.id
             LEFT JOIN (
                 SELECT product_id, SUBSTRING_INDEX(GROUP_CONCAT(image_url ORDER BY is_primary DESC, sort_order, id), ',', 1) AS image_url
                 FROM product_images GROUP BY product_id
             ) images ON images.product_id = p.id
             LEFT JOIN (
                 SELECT product_id, AVG(rating) AS average_rating, COUNT(*) AS review_count
                 FROM reviews WHERE status = 'published' GROUP BY product_id
             ) reviews ON reviews.product_id = p.id
             WHERE w.user_id = :user_id
             ORDER BY w.created_at DESC, p.id DESC"
        );
        $statement->execute(['user_id' => $userId]);
        $products = $statement->fetchAll();
        foreach ($products as &$product) {
            $product['quantity'] = 1;
        }
        unset($product);
        return (new PricingService($this->database))->applyFlashSalePricing($products, $userId);
    }

    public function adminReviewList(array $filters, int $page = 1, int $perPage = 20): array
    {
        $conditions = [];
        $parameters = [];
        if ($filters['q'] !== '') {
            $conditions[] = '(p.name LIKE :product_name OR u.name LIKE :customer_name
                OR u.email LIKE :customer_email OR r.content LIKE :content)';
            $search = '%' . $filters['q'] . '%';
            $parameters += [
                'product_name' => $search,
                'customer_name' => $search,
                'customer_email' => $search,
                'content' => $search,
            ];
        }
        if ($filters['product_id'] > 0) {
            $conditions[] = 'r.product_id = :product_id';
            $parameters['product_id'] = $filters['product_id'];
        }
        if ($filters['status'] !== '') {
            $conditions[] = 'r.status = :status';
            $parameters['status'] = $filters['status'];
        }
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $from = ' FROM reviews r JOIN users u ON u.id = r.user_id
                  JOIN products p ON p.id = r.product_id ';
        $count = $this->database->prepare('SELECT COUNT(*)' . $from . $where);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $query = $this->database->prepare(
            'SELECT r.id, r.user_id, r.product_id, r.order_id, r.rating, r.title, r.content,
                    r.status, r.created_at, r.updated_at, u.name AS customer_name,
                    u.email AS customer_email, p.name AS product_name, p.slug AS product_slug
             ' . $from . $where . '
             ORDER BY r.created_at DESC, r.id DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $key => $value) {
            $query->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $query->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $query->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $query->execute();
        return ['reviews' => $query->fetchAll(), 'page' => $page, 'pages' => $pages, 'total' => $total];
    }

    public function setReviewStatus(int $reviewId, int $actorId, string $status): void
    {
        if (!in_array($status, ['pending', 'published', 'hidden'], true)) {
            throw new InvalidArgumentException('Trạng thái đánh giá không hợp lệ.');
        }
        $this->database->beginTransaction();
        try {
            $find = $this->database->prepare('SELECT id, status FROM reviews WHERE id = :id FOR UPDATE');
            $find->execute(['id' => $reviewId]);
            $review = $find->fetch();
            if (!$review) {
                throw new InvalidArgumentException('Không tìm thấy đánh giá.');
            }
            $this->database->prepare(
                'UPDATE reviews SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
            )->execute(['status' => $status, 'id' => $reviewId]);
            AuditLog::record(
                $actorId,
                'review_status_changed',
                'review',
                $reviewId,
                $review['status'] . ' -> ' . $status,
                $this->database
            );
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function deleteReview(int $reviewId, int $actorId): void
    {
        $this->database->beginTransaction();
        try {
            $find = $this->database->prepare('SELECT id FROM reviews WHERE id = :id FOR UPDATE');
            $find->execute(['id' => $reviewId]);
            if (!$find->fetchColumn()) {
                throw new InvalidArgumentException('Không tìm thấy đánh giá.');
            }
            $this->database->prepare('DELETE FROM reviews WHERE id = :id')->execute(['id' => $reviewId]);
            AuditLog::record($actorId, 'review_deleted', 'review', $reviewId, null, $this->database);
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    private function completedOrderId(int $userId, int $productId, bool $lock = false): ?int
    {
        $sql = "SELECT o.id FROM orders o
                JOIN order_items oi ON oi.order_id = o.id
                WHERE o.user_id = :user_id AND oi.product_id = :product_id AND o.status = 'completed'
                ORDER BY o.placed_at DESC, o.id DESC LIMIT 1";
        if ($lock) {
            $sql .= ' FOR UPDATE';
        }
        $statement = $this->database->prepare($sql);
        $statement->execute(['user_id' => $userId, 'product_id' => $productId]);
        $orderId = $statement->fetchColumn();
        return $orderId === false ? null : (int) $orderId;
    }

    private function consumeReviewRateLimit(int $userId): void
    {
        $window = gmdate('Y-m-d H:00:00');
        $statement = $this->database->prepare(
            'INSERT INTO review_rate_limits (user_id, window_started_at, request_count)
             VALUES (:user_id, :window_started_at, 1)
             ON DUPLICATE KEY UPDATE request_count = request_count + 1'
        );
        $statement->execute(['user_id' => $userId, 'window_started_at' => $window]);
        $count = $this->database->prepare(
            'SELECT request_count FROM review_rate_limits
             WHERE user_id = :user_id AND window_started_at = :window_started_at FOR UPDATE'
        );
        $count->execute(['user_id' => $userId, 'window_started_at' => $window]);
        if ((int) $count->fetchColumn() > 5) {
            throw new HttpException(429, 'Bạn gửi đánh giá quá thường xuyên. Vui lòng thử lại sau một giờ.');
        }
        $this->database->exec(
            'DELETE FROM review_rate_limits
             WHERE window_started_at < UTC_TIMESTAMP() - INTERVAL 1 DAY LIMIT 500'
        );
    }
}
