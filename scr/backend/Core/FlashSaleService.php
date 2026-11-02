<?php

declare(strict_types=1);

final class FlashSaleService
{
    private const LOCAL_NOW_SQL = 'DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 HOUR)';

    public function __construct(private ?PDO $database = null)
    {
        $this->database ??= Database::connection();
    }

    public function activeDeal(int $productId, ?int $userId = null, bool $lock = false): ?array
    {
        $sql = "SELECT fsi.id AS flash_sale_item_id, fsi.flash_sale_id, fsi.sale_price,
                       fsi.quota, fsi.sold_quantity, fsi.per_user_limit, fsi.sort_order,
                       fs.name AS campaign_name, fs.starts_at, fs.ends_at,
                       GREATEST(0, fsi.quota - fsi.sold_quantity) AS remaining
                FROM flash_sale_items fsi
                JOIN flash_sales fs ON fs.id = fsi.flash_sale_id
                WHERE fsi.product_id = :product_id
                  AND fs.status NOT IN ('draft','cancelled')
                  AND fs.starts_at <= " . self::LOCAL_NOW_SQL . "
                  AND fs.ends_at > " . self::LOCAL_NOW_SQL . "
                ORDER BY fs.ends_at, fsi.id LIMIT 1";
        if ($lock) {
            $sql .= ' FOR UPDATE';
        }
        $query = $this->database->prepare($sql);
        $query->execute(['product_id' => $productId]);
        $deal = $query->fetch();
        if (!$deal) {
            return null;
        }
        $deal['user_purchased'] = 0;
        if ($userId !== null) {
            $purchases = $this->database->prepare(
                "SELECT COALESCE(SUM(quantity), 0) FROM flash_sale_purchases
                 WHERE flash_sale_item_id = :item_id AND user_id = :user_id
                   AND status IN ('reserved','confirmed')"
            );
            $purchases->execute(['item_id' => $deal['flash_sale_item_id'], 'user_id' => $userId]);
            $deal['user_purchased'] = (int) $purchases->fetchColumn();
        }
        return $deal;
    }

    public function assertCanAddToCart(int $productId, int $quantity, ?int $userId, string $ip): void
    {
        if ($productId < 1 || $quantity < 1) {
            throw new InvalidArgumentException('Sản phẩm hoặc số lượng Flash Sale không hợp lệ.');
        }
        $deal = $this->activeDeal($productId, $userId);
        if (!$deal) {
            return;
        }
        if ((int) $deal['remaining'] < 1) {
            throw new InventoryException('Flash Sale đã hết suất. Vui lòng tải lại trang hoặc mua theo giá bán hiện hành.');
        }
        if ((int) $deal['remaining'] < $quantity) {
            throw new InventoryException('Flash Sale chỉ còn ' . (int) $deal['remaining'] . ' suất. Hãy giảm số lượng và thử lại.');
        }
        if ($userId === null) {
            throw new InvalidArgumentException('Bạn cần đăng nhập để mua sản phẩm Flash Sale.');
        }
        $this->consumeRateLimit($userId, $ip);
        if ((int) $deal['user_purchased'] + $quantity > (int) $deal['per_user_limit']) {
            throw new InvalidArgumentException('Bạn chỉ được mua tối đa ' . (int) $deal['per_user_limit'] . ' sản phẩm Flash Sale này.');
        }
    }

    public function hasLiveDealInCart(int $userId): bool
    {
        $query = $this->database->prepare(
            "SELECT 1 FROM carts c JOIN cart_items ci ON ci.cart_id = c.id
             JOIN flash_sale_items fsi ON fsi.product_id = ci.product_id
             JOIN flash_sales fs ON fs.id = fsi.flash_sale_id
             WHERE c.user_id = :user_id AND c.status = 'active'
               AND fs.status NOT IN ('draft','cancelled')
               AND fs.starts_at <= " . self::LOCAL_NOW_SQL . "
               AND fs.ends_at > " . self::LOCAL_NOW_SQL . "
             LIMIT 1"
        );
        $query->execute(['user_id' => $userId]);
        return $query->fetchColumn() !== false;
    }

    public function consumeRateLimit(int $userId, string $ip): void
    {
        $window = intdiv(time(), 60) * 60;
        $windowAt = gmdate('Y-m-d H:i:s', $window);
        $keys = [
            ['key' => 'user:' . $userId . ':' . $window, 'limit' => 50],
            ['key' => 'ip:' . hash('sha256', $ip) . ':' . $window, 'limit' => 600],
        ];
        $this->database->prepare(
            'DELETE FROM flash_sale_rate_limits WHERE window_started_at < UTC_TIMESTAMP() - INTERVAL 2 MINUTE'
        )->execute();
        $upsert = $this->database->prepare(
            'INSERT INTO flash_sale_rate_limits (bucket_key, window_started_at, request_count)
             VALUES (:bucket_key, :window_started_at, 1)
             ON DUPLICATE KEY UPDATE request_count = request_count + 1'
        );
        $read = $this->database->prepare(
            'SELECT request_count FROM flash_sale_rate_limits WHERE bucket_key = :bucket_key'
        );
        foreach ($keys as $bucket) {
            $upsert->execute(['bucket_key' => $bucket['key'], 'window_started_at' => $windowAt]);
            $read->execute(['bucket_key' => $bucket['key']]);
            if ((int) $read->fetchColumn() > $bucket['limit']) {
                throw new HttpException(429, 'Bạn thao tác Flash Sale quá nhanh. Vui lòng thử lại sau một phút.');
            }
        }
    }

    public function recordOrderPurchases(int $orderId, int $userId, array $items, bool $reserved): void
    {
        if ($orderId < 1 || $userId < 1) {
            throw new InvalidArgumentException('Đơn hàng hoặc người mua Flash Sale không hợp lệ.');
        }
        if (!$this->database->inTransaction()) {
            throw new LogicException('Flash Sale purchases must be recorded in the order transaction.');
        }
        $insert = $this->database->prepare(
            'INSERT INTO flash_sale_purchases
                (flash_sale_item_id, user_id, order_id, quantity, status)
             VALUES (:item_id, :user_id, :order_id, :quantity, :status)'
        );
        foreach ($items as $item) {
            if (empty($item['flash_sale_item_id'])) {
                continue;
            }
            $quantity = (int) $item['quantity'];
            $itemId = (int) $item['flash_sale_item_id'];
            if ($quantity < 1 || $itemId < 1) {
                throw new InvalidArgumentException('Sản phẩm hoặc số lượng Flash Sale không hợp lệ.');
            }
            $claim = $this->database->prepare(
                "UPDATE flash_sale_items fsi
                 JOIN flash_sales fs ON fs.id = fsi.flash_sale_id
                 SET fsi.sold_quantity = fsi.sold_quantity + :quantity
                 WHERE fsi.id = :item_id
                   AND fsi.sold_quantity + :quantity_check <= fsi.quota
                   AND fs.status NOT IN ('draft','cancelled')
                   AND fs.starts_at <= " . self::LOCAL_NOW_SQL . "
                   AND fs.ends_at > " . self::LOCAL_NOW_SQL
            );
            $claim->execute([
                'quantity' => $quantity,
                'item_id' => $itemId,
                'quantity_check' => $quantity,
            ]);
            if ($claim->rowCount() !== 1) {
                throw new InventoryException('Flash Sale vừa hết suất hoặc đã kết thúc. Giá ưu đãi không còn áp dụng; vui lòng tải lại giỏ hàng.');
            }
            $limitQuery = $this->database->prepare(
                'SELECT per_user_limit FROM flash_sale_items WHERE id = :item_id FOR UPDATE'
            );
            $limitQuery->execute(['item_id' => $itemId]);
            $perUserLimit = $limitQuery->fetchColumn();
            $purchasedQuery = $this->database->prepare(
                "SELECT quantity FROM flash_sale_purchases
                 WHERE flash_sale_item_id = :item_id AND user_id = :user_id
                   AND status IN ('reserved','confirmed') FOR UPDATE"
            );
            $purchasedQuery->execute(['item_id' => $itemId, 'user_id' => $userId]);
            $userPurchased = array_sum(array_map('intval', $purchasedQuery->fetchAll(PDO::FETCH_COLUMN)));
            if ($perUserLimit === false
                || $userPurchased + $quantity > (int) $perUserLimit) {
                throw new InvalidArgumentException('Bạn đã vượt giới hạn mua Flash Sale cho sản phẩm này.');
            }
            $insert->execute([
                'item_id' => $itemId,
                'user_id' => $userId,
                'order_id' => $orderId,
                'quantity' => $quantity,
                'status' => $reserved ? 'reserved' : 'confirmed',
            ]);
        }
    }

    public function confirmForOrder(int $orderId): void
    {
        $this->database->prepare(
            "UPDATE flash_sale_purchases SET status = 'confirmed'
             WHERE order_id = :order_id AND status = 'reserved'"
        )->execute(['order_id' => $orderId]);
    }

    public function releaseForOrder(int $orderId): void
    {
        if (!$this->database->inTransaction()) {
            throw new LogicException('Flash Sale purchases must be released in the order transaction.');
        }
        $itemQuery = $this->database->prepare(
            "SELECT DISTINCT flash_sale_item_id FROM flash_sale_purchases
             WHERE order_id = :order_id AND status IN ('reserved','confirmed')"
        );
        $itemQuery->execute(['order_id' => $orderId]);
        $itemIds = array_map('intval', $itemQuery->fetchAll(PDO::FETCH_COLUMN));
        sort($itemIds);
        $lockItem = $this->database->prepare('SELECT id FROM flash_sale_items WHERE id = :id FOR UPDATE');
        foreach ($itemIds as $itemId) {
            $lockItem->execute(['id' => $itemId]);
            $lockItem->fetchColumn();
        }
        $query = $this->database->prepare(
            "SELECT id, flash_sale_item_id, quantity
             FROM flash_sale_purchases
             WHERE order_id = :order_id AND status IN ('reserved','confirmed')
             FOR UPDATE"
        );
        $query->execute(['order_id' => $orderId]);
        $purchases = [];
        foreach ($query->fetchAll() as $purchase) {
            $itemId = (int) $purchase['flash_sale_item_id'];
            $purchases[$itemId] = ($purchases[$itemId] ?? 0) + (int) $purchase['quantity'];
        }
        foreach ($purchases as $itemId => $quantity) {
            $release = $this->database->prepare(
                'UPDATE flash_sale_items SET sold_quantity = sold_quantity - :quantity
                 WHERE id = :id AND sold_quantity >= :minimum'
            );
            $release->execute([
                'quantity' => $quantity,
                'id' => $itemId,
                'minimum' => $quantity,
            ]);
            if ($release->rowCount() !== 1) {
                throw new RuntimeException('Flash Sale sold count is inconsistent with its purchase records.');
            }
        }
        $this->database->prepare(
            "UPDATE flash_sale_purchases SET status = 'released'
             WHERE order_id = :order_id AND status IN ('reserved','confirmed')"
        )->execute(['order_id' => $orderId]);
    }

    public function customerCampaigns(?int $userId = null): array
    {
        $statement = $this->database->prepare(
            "SELECT fs.id AS campaign_id, fs.name AS campaign_name, fs.description,
                    fs.banner_url, fs.starts_at, fs.ends_at,
                    CASE
                        WHEN fs.status IN ('draft','cancelled') THEN fs.status
                        WHEN fs.ends_at <= " . self::LOCAL_NOW_SQL . " THEN 'ended'
                        WHEN fs.starts_at > " . self::LOCAL_NOW_SQL . " THEN 'scheduled'
                        ELSE 'active'
                    END AS campaign_status,
                    fsi.id AS flash_sale_item_id, fsi.product_id, fsi.sale_price, fsi.quota,
                    fsi.sold_quantity, fsi.per_user_limit, fsi.sort_order,
                    p.name AS product_name, p.slug AS product_slug, p.price AS regular_price,
                    region.name AS region_name,
                    p.unit,
                    COALESCE(NULLIF(p.sale_price, 0), p.price) AS normal_price,
                    COALESCE(stock.available, 0) AS available_stock,
                    COALESCE(user_purchases.quantity, 0) AS user_purchased,
                    COALESCE(pi.image_url, '/img/product-placeholder.svg') AS image_url
             FROM flash_sales fs
             JOIN flash_sale_items fsi ON fsi.flash_sale_id = fs.id
             JOIN products p ON p.id = fsi.product_id AND p.status = 'active' AND p.deleted_at IS NULL
             LEFT JOIN regions region ON region.id = p.region_id
             LEFT JOIN (
                 SELECT product_id, SUBSTRING_INDEX(GROUP_CONCAT(image_url ORDER BY is_primary DESC, sort_order, id), ',', 1) AS image_url
                 FROM product_images GROUP BY product_id
             ) pi ON pi.product_id = p.id
             LEFT JOIN (
                 SELECT b.product_id,
                        SUM(GREATEST(0, b.quantity_remaining - COALESCE(r.reserved, 0))) AS available
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
                 SELECT flash_sale_item_id, SUM(quantity) AS quantity
                 FROM flash_sale_purchases
                 WHERE user_id = :user_id AND status IN ('reserved','confirmed')
                 GROUP BY flash_sale_item_id
             ) user_purchases ON user_purchases.flash_sale_item_id = fsi.id
             WHERE fs.status NOT IN ('draft','cancelled')
               AND fs.ends_at > " . self::LOCAL_NOW_SQL . "
             ORDER BY
                CASE WHEN fs.starts_at <= " . self::LOCAL_NOW_SQL . " THEN 0 ELSE 1 END,
                fs.starts_at, fs.ends_at, fs.id, fsi.sort_order, fsi.id"
        );
        $statement->execute(['user_id' => $userId ?? 0]);
        $offers = $statement->fetchAll();
        foreach ($offers as &$offer) {
            $offer['remaining'] = max(0, (int) $offer['quota'] - (int) $offer['sold_quantity']);
            $offer['user_remaining'] = max(0, (int) $offer['per_user_limit'] - (int) $offer['user_purchased']);
            $offer['max_buy_quantity'] = min(
                $offer['remaining'],
                $offer['user_remaining'],
                (int) $offer['available_stock']
            );
        }
        unset($offer);
        return $offers;
    }

    public function serverTime(): string
    {
        return (string) $this->database->query(
            "SELECT DATE_FORMAT(" . self::LOCAL_NOW_SQL . ", '%Y-%m-%dT%H:%i:%s+07:00')"
        )->fetchColumn();
    }

    public function liveProgress(): array
    {
        $query = $this->database->query(
            "SELECT fs.id AS campaign_id,
                    CASE
                        WHEN fs.status IN ('draft','cancelled') THEN fs.status
                        WHEN fs.ends_at <= " . self::LOCAL_NOW_SQL . " THEN 'ended'
                        WHEN fs.starts_at > " . self::LOCAL_NOW_SQL . " THEN 'scheduled'
                        ELSE 'active'
                    END AS campaign_status,
                    fs.starts_at, fs.ends_at,
                    fsi.id AS item_id, fsi.quota, fsi.sold_quantity,
                    GREATEST(0, fsi.quota - fsi.sold_quantity) AS remaining
             FROM flash_sales fs JOIN flash_sale_items fsi ON fsi.flash_sale_id = fs.id
             WHERE fs.status NOT IN ('draft','cancelled')
               AND fs.ends_at > " . self::LOCAL_NOW_SQL . "
             ORDER BY fs.id, fsi.sort_order, fsi.id"
        );
        $items = $query->fetchAll();
        return [
            'server_time' => $this->serverTime(),
            'has_active_campaign' => (bool) array_filter(
                $items,
                static fn (array $item): bool => $item['campaign_status'] === 'active'
            ),
            'items' => $items,
        ];
    }

    public function saveCampaign(array $input, ?int $campaignId, int $actorId): int
    {
        $name = trim((string) ($input['name'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $banner = trim((string) ($input['banner_url'] ?? ''));
        $startsAt = $this->parseLocalDateTime((string) ($input['starts_at'] ?? ''));
        $endsAt = $this->parseLocalDateTime((string) ($input['ends_at'] ?? ''));
        $status = (string) ($input['status'] ?? 'draft');
        $reason = trim((string) ($input['adjustment_reason'] ?? ''));
        if ($name === '' || mb_strlen($name, 'UTF-8') > 180 || $startsAt >= $endsAt) {
            throw new InvalidArgumentException('Tên chiến dịch và khoảng thời gian chưa hợp lệ.');
        }
        if (!in_array($status, ['draft','scheduled'], true)) {
            throw new InvalidArgumentException('Trạng thái lưu chỉ có thể là nháp hoặc sắp diễn ra.');
        }
        if ($banner !== '' && (str_starts_with($banner, '//') || !str_starts_with($banner, '/'))) {
            throw new InvalidArgumentException('Banner phải là đường dẫn nội bộ bắt đầu bằng dấu /.');
        }
        if (mb_strlen($description, 'UTF-8') > 5000) {
            throw new InvalidArgumentException('Mô tả tối đa 5000 ký tự.');
        }

        $lines = $this->normalizeItemLines($input);
        $this->database->beginTransaction();
        try {
            $campaign = null;
            if ($campaignId !== null) {
                $query = $this->database->prepare('SELECT * FROM flash_sales WHERE id = :id FOR UPDATE');
                $query->execute(['id' => $campaignId]);
                $campaign = $query->fetch();
                if (!$campaign || $campaign['status'] === 'cancelled') {
                    throw new InvalidArgumentException('Không tìm thấy chiến dịch có thể chỉnh sửa.');
                }
                $activeNow = $campaign['status'] === 'active'
                    && $campaign['starts_at'] <= $this->localNowString()
                    && $campaign['ends_at'] > $this->localNowString();
                $timesChanged = $campaign['starts_at'] !== $startsAt || $campaign['ends_at'] !== $endsAt;
                if ($timesChanged) {
                    $history = $this->database->prepare(
                        'SELECT 1 FROM flash_sale_items fsi
                         WHERE fsi.flash_sale_id = :campaign_id
                           AND (
                               EXISTS (
                                   SELECT 1 FROM flash_sale_purchases fsp
                                   WHERE fsp.flash_sale_item_id = fsi.id
                               )
                               OR EXISTS (
                                   SELECT 1 FROM order_items oi
                                   WHERE oi.flash_sale_item_id = fsi.id
                               )
                           )
                         LIMIT 1'
                    );
                    $history->execute(['campaign_id' => $campaignId]);
                    if ($history->fetchColumn()) {
                        throw new InvalidArgumentException('Không thể đổi thời gian chương trình sau khi sản phẩm đã có đơn.');
                    }
                }
                $itemsChanged = $this->existingItemsChanged($campaignId, $lines);
                $changedTerms = $timesChanged || $itemsChanged;
                if ($activeNow && $changedTerms && $reason === '') {
                    throw new InvalidArgumentException('Điều chỉnh chiến dịch đang chạy cần nhập lý do.');
                }
                if ($activeNow && $changedTerms && mb_strlen($reason, 'UTF-8') > 500) {
                    throw new InvalidArgumentException('Lý do điều chỉnh tối đa 500 ký tự.');
                }
            }

            $this->validateProductLines($lines, $startsAt, $endsAt, $campaignId);
            if ($campaignId === null) {
                $insert = $this->database->prepare(
                    'INSERT INTO flash_sales (name, description, banner_url, starts_at, ends_at, status)
                     VALUES (:name, :description, :banner, :starts_at, :ends_at, :status)'
                );
                $insert->execute([
                    'name' => $name, 'description' => $description ?: null, 'banner' => $banner ?: null,
                    'starts_at' => $startsAt, 'ends_at' => $endsAt, 'status' => $status,
                ]);
                $campaignId = (int) $this->database->lastInsertId();
            } else {
                $this->database->prepare(
                    'UPDATE flash_sales SET name = :name, description = :description, banner_url = :banner,
                        starts_at = :starts_at, ends_at = :ends_at,
                        status = CASE WHEN status = \'draft\' THEN :status ELSE status END
                     WHERE id = :id'
                )->execute([
                    'name' => $name, 'description' => $description ?: null, 'banner' => $banner ?: null,
                    'starts_at' => $startsAt, 'ends_at' => $endsAt, 'status' => $status, 'id' => $campaignId,
                ]);
            }
            $this->persistItems($campaignId, $lines);
            if ($campaign !== null && $campaign['status'] === 'active'
                && ($campaign['starts_at'] !== $startsAt || $campaign['ends_at'] !== $endsAt
                    || $itemsChanged)) {
                AuditLog::record(
                    $actorId,
                    'flash_sale_adjusted',
                    'flash_sale',
                    $campaignId,
                    mb_substr($reason, 0, 500, 'UTF-8'),
                    $this->database
                );
            } elseif ($campaignId !== null && $campaign === null) {
                AuditLog::record($actorId, 'flash_sale_updated', 'flash_sale', $campaignId, $name, $this->database);
            } else {
                AuditLog::record($actorId, 'flash_sale_created', 'flash_sale', $campaignId, $name, $this->database);
            }
            $this->database->commit();
            return $campaignId;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function cancelCampaign(int $campaignId, int $actorId, string $reason): void
    {
        if (trim($reason) === '' || mb_strlen(trim($reason), 'UTF-8') > 500) {
            throw new InvalidArgumentException('Nhập lý do hủy chiến dịch (tối đa 500 ký tự).');
        }
        $update = $this->database->prepare(
            "UPDATE flash_sales SET status = 'cancelled' WHERE id = :id AND status <> 'cancelled'"
        );
        $update->execute(['id' => $campaignId]);
        if ($update->rowCount() !== 1) {
            throw new InvalidArgumentException('Chiến dịch không tồn tại hoặc đã bị hủy.');
        }
        AuditLog::record($actorId, 'flash_sale_cancelled', 'flash_sale', $campaignId, trim($reason), $this->database);
    }

    public function deleteCampaign(int $campaignId, int $actorId): void
    {
        $this->database->beginTransaction();
        try {
            $campaignQuery = $this->database->prepare('SELECT id FROM flash_sales WHERE id = :id FOR UPDATE');
            $campaignQuery->execute(['id' => $campaignId]);
            if (!$campaignQuery->fetchColumn()) {
                throw new InvalidArgumentException('Không tìm thấy chương trình Flash Sale cần xóa.');
            }
            $orders = $this->database->prepare(
                'SELECT 1
                 FROM flash_sale_items fsi
                 WHERE fsi.flash_sale_id = :campaign_id
                   AND (
                       EXISTS (
                           SELECT 1 FROM flash_sale_purchases fsp
                           WHERE fsp.flash_sale_item_id = fsi.id
                       )
                       OR EXISTS (
                           SELECT 1 FROM order_items oi
                           WHERE oi.flash_sale_item_id = fsi.id
                       )
                   )
                 LIMIT 1'
            );
            $orders->execute(['campaign_id' => $campaignId]);
            if ($orders->fetchColumn()) {
                throw new InvalidArgumentException('Chương trình đã có đơn hàng; hãy hủy chương trình thay vì xóa.');
            }
            $this->database->prepare('DELETE FROM flash_sales WHERE id = :id')->execute(['id' => $campaignId]);
            AuditLog::record($actorId, 'flash_sale_deleted', 'flash_sale', $campaignId, null, $this->database);
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function duplicateCampaign(int $campaignId, int $actorId): int
    {
        $this->database->beginTransaction();
        try {
            $query = $this->database->prepare('SELECT * FROM flash_sales WHERE id = :id FOR UPDATE');
            $query->execute(['id' => $campaignId]);
            $campaign = $query->fetch();
            if (!$campaign) {
                throw new InvalidArgumentException('Không tìm thấy chiến dịch cần nhân bản.');
            }
            $start = new DateTimeImmutable($campaign['starts_at'], new DateTimeZone('Asia/Ho_Chi_Minh'));
            $end = new DateTimeImmutable($campaign['ends_at'], new DateTimeZone('Asia/Ho_Chi_Minh'));
            $duration = max(3600, $end->getTimestamp() - $start->getTimestamp());
            $newStart = $end->modify('+1 day');
            $newEnd = $newStart->modify('+' . $duration . ' seconds');
            $this->database->prepare(
                'INSERT INTO flash_sales (name, description, banner_url, starts_at, ends_at, status)
                 VALUES (:name, :description, :banner, :starts_at, :ends_at, \'draft\')'
            )->execute([
                'name' => mb_substr($campaign['name'] . ' (bản sao)', 0, 180, 'UTF-8'),
                'description' => $campaign['description'],
                'banner' => $campaign['banner_url'],
                'starts_at' => $newStart->format('Y-m-d H:i:s'),
                'ends_at' => $newEnd->format('Y-m-d H:i:s'),
            ]);
            $newId = (int) $this->database->lastInsertId();
            $this->database->prepare(
                'INSERT INTO flash_sale_items (flash_sale_id, product_id, sale_price, quota, per_user_limit, sort_order)
                 SELECT :new_id, product_id, sale_price, quota, per_user_limit, sort_order
                 FROM flash_sale_items WHERE flash_sale_id = :source_id'
            )->execute(['new_id' => $newId, 'source_id' => $campaignId]);
            AuditLog::record($actorId, 'flash_sale_duplicated', 'flash_sale', $newId, 'source_campaign=' . $campaignId, $this->database);
            $this->database->commit();
            return $newId;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function adminCampaigns(?int $limit = null, int $offset = 0): array
    {
        $sql = "SELECT fs.*,
                    CASE
                        WHEN fs.status IN ('draft','cancelled') THEN fs.status
                        WHEN fs.ends_at <= " . self::LOCAL_NOW_SQL . " THEN 'ended'
                        WHEN fs.starts_at > " . self::LOCAL_NOW_SQL . " THEN 'scheduled'
                        ELSE 'active'
                    END AS campaign_status,
                    COALESCE(item_stats.item_count, 0) AS item_count,
                    COALESCE(item_stats.sold, 0) AS sold,
                    COALESCE(item_stats.quota, 0) AS quota,
                    COALESCE(purchase_stats.revenue, 0) AS revenue,
                    COALESCE(purchase_stats.buyers, 0) AS buyers
             FROM flash_sales fs
             LEFT JOIN (
                 SELECT flash_sale_id, COUNT(*) AS item_count,
                        SUM(sold_quantity) AS sold, SUM(quota) AS quota
                 FROM flash_sale_items GROUP BY flash_sale_id
             ) item_stats ON item_stats.flash_sale_id = fs.id
             LEFT JOIN (
                 SELECT fsi.flash_sale_id,
                        SUM(oi.line_total) AS revenue,
                        COUNT(DISTINCT fsp.user_id) AS buyers
                 FROM flash_sale_purchases fsp
                 JOIN flash_sale_items fsi ON fsi.id = fsp.flash_sale_item_id
                 LEFT JOIN order_items oi
                   ON oi.order_id = fsp.order_id AND oi.flash_sale_item_id = fsp.flash_sale_item_id
                 WHERE fsp.status IN ('reserved','confirmed')
                 GROUP BY fsi.flash_sale_id
             ) purchase_stats ON purchase_stats.flash_sale_id = fs.id
             ORDER BY fs.starts_at DESC, fs.id DESC";
        if ($limit !== null) {
            $sql .= ' LIMIT :limit OFFSET :offset';
        }
        $query = $this->database->prepare($sql);
        if ($limit !== null) {
            $query->bindValue(':limit', $limit, PDO::PARAM_INT);
            $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        }
        $query->execute();
        return $query->fetchAll();
    }

    public function campaignForAdmin(int $campaignId): ?array
    {
        $query = $this->database->prepare(
            "SELECT fs.*,
                    CASE
                        WHEN fs.status IN ('draft','cancelled') THEN fs.status
                        WHEN fs.ends_at <= " . self::LOCAL_NOW_SQL . " THEN 'ended'
                        WHEN fs.starts_at > " . self::LOCAL_NOW_SQL . " THEN 'scheduled'
                        ELSE 'active'
                    END AS campaign_status
             FROM flash_sales fs WHERE fs.id = :id"
        );
        $query->execute(['id' => $campaignId]);
        $campaign = $query->fetch();
        if (!$campaign) {
            return null;
        }
        $items = $this->database->prepare(
            "SELECT fsi.*, p.name AS product_name, p.sku, p.price AS regular_price,
                    COALESCE(NULLIF(p.sale_price, 0), p.price) AS current_price,
                    COALESCE(stock.available, 0) AS available_stock,
                    COALESCE(purchases.revenue, 0) AS revenue
             FROM flash_sale_items fsi JOIN products p ON p.id = fsi.product_id
             LEFT JOIN (
                 SELECT fsp.flash_sale_item_id, SUM(oi.line_total) AS revenue
                 FROM flash_sale_purchases fsp
                 LEFT JOIN order_items oi
                   ON oi.order_id = fsp.order_id AND oi.flash_sale_item_id = fsp.flash_sale_item_id
                 WHERE fsp.status IN ('reserved','confirmed')
                 GROUP BY fsp.flash_sale_item_id
             ) purchases ON purchases.flash_sale_item_id = fsi.id
             LEFT JOIN (
                 SELECT b.product_id, SUM(GREATEST(0, b.quantity_remaining - COALESCE(r.reserved, 0))) AS available
                 FROM product_batches b
                 LEFT JOIN (
                     SELECT batch_id, SUM(quantity) AS reserved FROM stock_reservations
                     WHERE status = 'reserved' AND expires_at > NOW() GROUP BY batch_id
                 ) r ON r.batch_id = b.id
                 WHERE b.status = 'selling' AND b.inspection_result <> 'failed'
                   AND (b.expires_at IS NULL OR b.expires_at >= CURDATE())
                 GROUP BY b.product_id
             ) stock ON stock.product_id = p.id
             WHERE fsi.flash_sale_id = :id ORDER BY fsi.sort_order, fsi.id"
        );
        $items->execute(['id' => $campaignId]);
        $campaign['items'] = $items->fetchAll();
        foreach ($campaign['items'] as &$item) {
            $item['remaining'] = max(0, (int) $item['quota'] - (int) $item['sold_quantity']);
        }
        unset($item);
        return $campaign;
    }

    public function searchProducts(string $search): array
    {
        $search = trim($search);
        if ($search === '') {
            return [];
        }
        $query = $this->database->prepare(
            "SELECT p.id, p.name, p.sku, p.price AS regular_price,
                    COALESCE(NULLIF(p.sale_price, 0), p.price) AS current_price,
                    COALESCE(stock.available, 0) AS available_stock
             FROM products p
             LEFT JOIN (
                 SELECT b.product_id,
                        SUM(GREATEST(0, b.quantity_remaining - COALESCE(r.reserved, 0))) AS available
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
             WHERE p.status = 'active' AND p.deleted_at IS NULL
               AND (p.name LIKE :name_search OR p.sku LIKE :sku_search)
             ORDER BY p.name, p.id LIMIT 30"
        );
        $term = '%' . mb_substr($search, 0, 100, 'UTF-8') . '%';
        $query->execute(['name_search' => $term, 'sku_search' => $term]);
        return $query->fetchAll();
    }

    public function exportStats(): void
    {
        $rows = $this->adminCampaigns();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="flash-sales.csv"');
        $output = fopen('php://output', 'wb');
        if ($output === false) {
            throw new RuntimeException('Không thể mở luồng xuất CSV Flash Sale.');
        }
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['ID', 'Chiến dịch', 'Trạng thái', 'Bắt đầu', 'Kết thúc', 'Đã bán', 'Quota', 'Doanh thu', 'Tỷ lệ bán hết (%)', 'Số người mua']);
        foreach ($rows as $row) {
            $rate = (int) $row['quota'] > 0 ? round((int) $row['sold'] * 100 / (int) $row['quota'], 2) : 0;
            fputcsv($output, [
                (int) $row['id'], $this->csvText($row['name']), $row['status'], $row['starts_at'], $row['ends_at'],
                (int) $row['sold'], (int) $row['quota'], (string) $row['revenue'], $rate, (int) $row['buyers'],
            ]);
        }
        fclose($output);
        exit;
    }

    private function normalizeItemLines(array $input): array
    {
        $products = $input['product_id'] ?? [];
        $prices = $input['sale_price'] ?? [];
        $quotas = $input['quota'] ?? [];
        $limits = $input['per_user_limit'] ?? [];
        $orders = $input['sort_order'] ?? [];
        if (!is_array($products) || !is_array($prices) || !is_array($quotas) || !is_array($limits) || !is_array($orders)) {
            throw new InvalidArgumentException('Danh sách sản phẩm Flash Sale không hợp lệ.');
        }
        $lines = [];
        foreach ($products as $index => $product) {
            if (trim((string) $product) === '') {
                continue;
            }
            $productId = filter_var($product, FILTER_VALIDATE_INT);
            $price = filter_var($prices[$index] ?? null, FILTER_VALIDATE_INT);
            $quota = filter_var($quotas[$index] ?? null, FILTER_VALIDATE_INT);
            $limit = filter_var($limits[$index] ?? null, FILTER_VALIDATE_INT);
            $sortOrder = filter_var($orders[$index] ?? 0, FILTER_VALIDATE_INT);
            if (!$productId || $price === false || $price < 1 || $quota === false || $quota < 1
                || $limit === false || $limit < 1 || $sortOrder === false) {
                throw new InvalidArgumentException('Sản phẩm, giá, quota, giới hạn mỗi người và thứ tự phải hợp lệ.');
            }
            $lines[] = [
                'product_id' => (int) $productId,
                'sale_price' => (int) $price,
                'quota' => (int) $quota,
                'per_user_limit' => (int) $limit,
                'sort_order' => (int) $sortOrder,
            ];
        }
        if (count($lines) !== count(array_unique(array_column($lines, 'product_id')))) {
            throw new InvalidArgumentException('Không được lặp sản phẩm trong cùng một chiến dịch.');
        }
        return $lines;
    }

    private function validateProductLines(array $lines, string $startsAt, string $endsAt, ?int $campaignId): void
    {
        $productQuery = $this->database->prepare(
            'SELECT id, price, sale_price FROM products WHERE id = :id AND status = \'active\' AND deleted_at IS NULL'
        );
        $overlapQuery = $this->database->prepare(
            "SELECT fs.name FROM flash_sale_items fsi
             JOIN flash_sales fs ON fs.id = fsi.flash_sale_id
             WHERE fsi.product_id = :product_id AND fs.status <> 'cancelled'
               AND fs.starts_at < :ends_at AND fs.ends_at > :starts_at
               AND (:campaign_id_null IS NULL OR fs.id <> :campaign_id_match)
             LIMIT 1"
        );
        foreach ($lines as $line) {
            $productQuery->execute(['id' => $line['product_id']]);
            $product = $productQuery->fetch();
            if (!$product) {
                throw new InvalidArgumentException('Sản phẩm #' . $line['product_id'] . ' không còn được bán.');
            }
            $regular = PricingService::moneyToVnd((string) ($product['sale_price'] ?: $product['price']));
            if ($line['sale_price'] >= $regular) {
                throw new InvalidArgumentException('Giá Flash Sale phải thấp hơn giá bán hiện tại của sản phẩm #' . $line['product_id'] . '.');
            }
            $overlapQuery->execute([
                'product_id' => $line['product_id'],
                'ends_at' => $endsAt,
                'starts_at' => $startsAt,
                'campaign_id_null' => $campaignId,
                'campaign_id_match' => $campaignId,
            ]);
            $overlap = $overlapQuery->fetchColumn();
            if ($overlap !== false) {
                throw new InvalidArgumentException('Sản phẩm #' . $line['product_id'] . ' đã thuộc chiến dịch trùng thời gian "' . $overlap . '".');
            }
        }
    }

    private function persistItems(int $campaignId, array $lines): void
    {
        $existing = $this->database->prepare(
            'SELECT fsi.id, fsi.product_id, fsi.sold_quantity, fsi.sale_price, fsi.per_user_limit,
                    EXISTS (
                        SELECT 1 FROM flash_sale_purchases fsp WHERE fsp.flash_sale_item_id = fsi.id
                    ) AS has_purchase,
                    EXISTS (
                        SELECT 1 FROM order_items oi WHERE oi.flash_sale_item_id = fsi.id
                    ) AS has_order
             FROM flash_sale_items fsi WHERE fsi.flash_sale_id = :id FOR UPDATE'
        );
        $existing->execute(['id' => $campaignId]);
        $old = [];
        foreach ($existing->fetchAll() as $row) {
            $old[(int) $row['product_id']] = $row;
        }
        $upsert = $this->database->prepare(
            'INSERT INTO flash_sale_items
                (flash_sale_id, product_id, sale_price, quota, per_user_limit, sort_order)
             VALUES (:campaign_id, :product_id, :sale_price, :quota, :per_user_limit, :sort_order)
             ON DUPLICATE KEY UPDATE sale_price = VALUES(sale_price), quota = VALUES(quota),
                per_user_limit = VALUES(per_user_limit), sort_order = VALUES(sort_order)'
        );
        $kept = [];
        foreach ($lines as $line) {
            if (isset($old[$line['product_id']]) && (int) $old[$line['product_id']]['sold_quantity'] > $line['quota']) {
                throw new InvalidArgumentException('Quota không thể nhỏ hơn số suất đã bán.');
            }
            if (isset($old[$line['product_id']])
                && ((int) $old[$line['product_id']]['has_purchase'] > 0 || (int) $old[$line['product_id']]['has_order'] > 0)) {
                $original = $old[$line['product_id']];
                if ((int) $original['sale_price'] !== $line['sale_price']
                    || (int) $original['per_user_limit'] !== $line['per_user_limit']) {
                    throw new InvalidArgumentException('Không thể đổi giá flash hoặc giới hạn mua sau khi sản phẩm đã có đơn.');
                }
            }
            $upsert->execute(['campaign_id' => $campaignId] + $line);
            $kept[] = $line['product_id'];
        }
        foreach ($old as $productId => $oldRow) {
            if (!in_array($productId, $kept, true)) {
                if ((int) $oldRow['sold_quantity'] > 0
                    || (int) $oldRow['has_purchase'] > 0
                    || (int) $oldRow['has_order'] > 0) {
                    throw new InvalidArgumentException('Không thể gỡ sản phẩm đã có đơn; hãy giữ sản phẩm hoặc hủy chương trình.');
                }
                $this->database->prepare('DELETE FROM flash_sale_items WHERE id = :id')
                    ->execute(['id' => $oldRow['id']]);
            }
        }
    }

    private function existingItemsChanged(int $campaignId, array $lines): bool
    {
        $query = $this->database->prepare(
            'SELECT product_id, sale_price, quota, per_user_limit, sort_order
             FROM flash_sale_items WHERE flash_sale_id = :id ORDER BY product_id'
        );
        $query->execute(['id' => $campaignId]);
        $existing = $query->fetchAll();
        $expected = $lines;
        usort($expected, static fn (array $a, array $b): int => $a['product_id'] <=> $b['product_id']);
        if (count($existing) !== count($expected)) {
            return true;
        }
        foreach ($existing as $index => $row) {
            foreach (['product_id','sale_price','quota','per_user_limit','sort_order'] as $key) {
                if ((int) $row[$key] !== (int) $expected[$index][$key]) {
                    return true;
                }
            }
        }
        return false;
    }

    private function parseLocalDateTime(string $value): string
    {
        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d\TH:i',
            $value,
            new DateTimeZone('Asia/Ho_Chi_Minh')
        );
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || (is_array($errors) && ($errors['warning_count'] || $errors['error_count']))) {
            throw new InvalidArgumentException('Thời gian chiến dịch không đúng định dạng.');
        }
        return $date->format('Y-m-d H:i:s');
    }

    private function localNowString(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d H:i:s');
    }

    private function csvText(string $value): string
    {
        return preg_match('/\A[=+\-@]/', $value) === 1 ? "'" . $value : $value;
    }
}
