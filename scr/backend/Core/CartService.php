<?php

declare(strict_types=1);

final class CartService
{
    private PDO $database;

    public function __construct(?PDO $database = null)
    {
        $this->database = $database ?? Database::connection();
    }

    public function summary(?int $userId = null): array
    {
        $cartId = $this->cartId($userId, false);
        if ($cartId === 0) {
            return ['items' => [], 'subtotal' => 0, 'max_quantity' => $this->maxQuantity(), 'cart_id' => 0];
        }
        $query = $this->database->prepare(
            'SELECT ci.id AS cart_item_id, ci.product_id, ci.quantity, ci.price_snapshot,
                    p.name, p.slug, p.sku, p.unit, p.price, p.sale_price, p.category_id,
                    p.status AS product_status, p.deleted_at,
                    COALESCE(NULLIF(p.sale_price, 0), p.price) AS current_price,
                    COALESCE(pi.image_url, \'/img/product-placeholder.svg\') AS image_url
             FROM cart_items ci
             JOIN products p ON p.id = ci.product_id
             LEFT JOIN (
                 SELECT product_id, SUBSTRING_INDEX(GROUP_CONCAT(image_url ORDER BY is_primary DESC, sort_order, id), \',\', 1) AS image_url
                 FROM product_images GROUP BY product_id
             ) pi ON pi.product_id = p.id
             WHERE ci.cart_id = :cart_id ORDER BY ci.id'
        );
        $query->execute(['cart_id' => $cartId]);
        $items = $query->fetchAll();
        $stock = (new InventoryService($this->database))->availableStockByProductIds(array_column($items, 'product_id'));
        $maxQuantity = $this->maxQuantity();
        $subtotal = 0;
        foreach ($items as &$item) {
            $item['current_price'] = PricingService::moneyToVnd((string) $item['current_price']);
            $item['price_changed'] = $item['price_snapshot'] !== null
                && PricingService::moneyToVnd((string) $item['price_snapshot']) !== $item['current_price'];
            $item['available_stock'] = (int) ($stock[(int) $item['product_id']] ?? 0);
            $item['valid'] = $item['product_status'] === 'active' && $item['deleted_at'] === null && $item['available_stock'] > 0;
            $item['message'] = null;
            if ($item['product_status'] !== 'active' || $item['deleted_at'] !== null) {
                $item['valid'] = false;
                $item['message'] = 'Sản phẩm hiện không còn được bán.';
            } elseif ($item['available_stock'] < 1) {
                $item['valid'] = false;
                $item['message'] = 'Sản phẩm hiện đã hết tồn khả dụng.';
            } else {
                $clamped = min((int) $item['quantity'], $item['available_stock'], $maxQuantity);
                if ($clamped !== (int) $item['quantity']) {
                    $this->database->prepare(
                        'UPDATE cart_items SET quantity = :quantity, price_snapshot = :price WHERE id = :id'
                    )->execute(['quantity' => $clamped, 'price' => $item['current_price'], 'id' => $item['cart_item_id']]);
                    $item['message'] = 'Số lượng đã được điều chỉnh theo tồn kho/giới hạn mỗi dòng.';
                    $item['quantity'] = $clamped;
                }
                if ($item['price_changed']) {
                    $item['message'] = trim(($item['message'] ? $item['message'] . ' ' : '') . 'Giá đã thay đổi; đang hiển thị giá hiện hành.');
                }
                $subtotal += PricingService::lineTotal((string) $item['current_price'], (int) $item['quantity']);
            }
        }
        unset($item);
        return [
            'items' => $items,
            'subtotal' => $subtotal,
            'max_quantity' => $maxQuantity,
            'cart_id' => $cartId,
        ];
    }

    public function add(int $productId, int $quantity, ?int $userId = null): array
    {
        if ($productId < 1 || $quantity < 1) {
            throw new InvalidArgumentException('Chọn sản phẩm và số lượng hợp lệ.');
        }
        $this->database->beginTransaction();
        try {
            $productQuery = $this->database->prepare(
                'SELECT id, price, sale_price, status FROM products WHERE id = :id AND deleted_at IS NULL FOR UPDATE'
            );
            $productQuery->execute(['id' => $productId]);
            $product = $productQuery->fetch();
            if (!$product || $product['status'] !== 'active') {
                throw new InventoryException('Sản phẩm hiện không còn được bán.');
            }
            $available = (new InventoryService($this->database))->availableStock($productId);
            $cartId = $this->cartId($userId, true);
            $existingQuery = $this->database->prepare('SELECT id, quantity FROM cart_items WHERE cart_id = :cart_id AND product_id = :product_id FOR UPDATE');
            $existingQuery->execute(['cart_id' => $cartId, 'product_id' => $productId]);
            $existing = $existingQuery->fetch();
            $newQuantity = min($this->maxQuantity(), $available, $quantity + (int) ($existing['quantity'] ?? 0));
            if ($available < 1 || $newQuantity < 1) {
                throw new InventoryException('Sản phẩm hiện đã hết tồn khả dụng.');
            }
            $price = $product['sale_price'] !== null && PricingService::moneyToVnd((string) $product['sale_price']) > 0
                ? $product['sale_price']
                : $product['price'];
            if ($existing) {
                $this->database->prepare('UPDATE cart_items SET quantity = :quantity, price_snapshot = :price WHERE id = :id')
                    ->execute(['quantity' => $newQuantity, 'price' => $price, 'id' => $existing['id']]);
            } else {
                $this->database->prepare(
                    'INSERT INTO cart_items (cart_id, product_id, quantity, price_snapshot)
                     VALUES (:cart_id, :product_id, :quantity, :price)'
                )->execute(['cart_id' => $cartId, 'product_id' => $productId, 'quantity' => $newQuantity, 'price' => $price]);
            }
            $this->database->commit();
            return $this->summary($userId);
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function setQuantity(int $productId, int $quantity, ?int $userId = null): array
    {
        if ($productId < 1 || $quantity < 1) {
            throw new InvalidArgumentException('Số lượng phải từ 1 trở lên.');
        }
        $cartId = $this->cartId($userId);
        $productQuery = $this->database->prepare(
            'SELECT p.price, p.sale_price FROM cart_items ci JOIN products p ON p.id = ci.product_id
             WHERE ci.cart_id = :cart_id AND ci.product_id = :product_id AND p.status = \'active\' AND p.deleted_at IS NULL'
        );
        $productQuery->execute(['cart_id' => $cartId, 'product_id' => $productId]);
        $product = $productQuery->fetch();
        if (!$product) {
            throw new InventoryException('Không tìm thấy sản phẩm đang bán trong giỏ.');
        }
        $available = (new InventoryService($this->database))->availableStock($productId);
        if ($available < 1) {
            throw new InventoryException('Sản phẩm hiện đã hết tồn khả dụng.');
        }
        $quantity = min($quantity, $available, $this->maxQuantity());
        $price = $product['sale_price'] !== null && PricingService::moneyToVnd((string) $product['sale_price']) > 0
            ? $product['sale_price']
            : $product['price'];
        $this->database->prepare(
            'UPDATE cart_items SET quantity = :quantity, price_snapshot = :price WHERE cart_id = :cart_id AND product_id = :product_id'
        )->execute(['quantity' => $quantity, 'price' => $price, 'cart_id' => $cartId, 'product_id' => $productId]);
        return $this->summary($userId);
    }

    public function remove(int $productId, ?int $userId = null): array
    {
        $this->database->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id AND product_id = :product_id')
            ->execute(['cart_id' => $this->cartId($userId), 'product_id' => $productId]);
        return $this->summary($userId);
    }

    public function mergeSessionCart(int $userId): void
    {
        Session::start();
        $sessionKey = $_SESSION['_cart_key'] ?? null;
        if (!$sessionKey) {
            return;
        }
        $this->database->beginTransaction();
        try {
            $guestQuery = $this->database->prepare('SELECT id FROM carts WHERE session_key = :key AND status = \'active\' FOR UPDATE');
            $guestQuery->execute(['key' => $sessionKey]);
            $guestId = $guestQuery->fetchColumn();
            if ($guestId === false) {
                $this->database->commit();
                return;
            }
            $userCartQuery = $this->database->prepare('SELECT id FROM carts WHERE user_id = :user_id AND status = \'active\' FOR UPDATE');
            $userCartQuery->execute(['user_id' => $userId]);
            $userCartId = $userCartQuery->fetchColumn();
            if ($userCartId === false) {
                $this->database->prepare('UPDATE carts SET user_id = :user_id, session_key = NULL WHERE id = :id')
                    ->execute(['user_id' => $userId, 'id' => $guestId]);
                $this->database->commit();
                return;
            }
            $items = $this->database->prepare('SELECT product_id, quantity FROM cart_items WHERE cart_id = :id FOR UPDATE');
            $items->execute(['id' => $guestId]);
            foreach ($items->fetchAll() as $item) {
                $productId = (int) $item['product_id'];
                $stock = (new InventoryService($this->database))->availableStock($productId);
                $target = $this->database->prepare('SELECT quantity FROM cart_items WHERE cart_id = :cart_id AND product_id = :product_id FOR UPDATE');
                $target->execute(['cart_id' => $userCartId, 'product_id' => $productId]);
                $current = $target->fetchColumn();
                $quantity = min($stock, $this->maxQuantity(), (int) $item['quantity'] + (int) ($current ?: 0));
                if ($quantity < 1) {
                    continue;
                }
                $priceQuery = $this->database->prepare('SELECT COALESCE(NULLIF(sale_price, 0), price) FROM products WHERE id = :id AND status = \'active\' AND deleted_at IS NULL');
                $priceQuery->execute(['id' => $productId]);
                $price = $priceQuery->fetchColumn();
                if ($price === false) {
                    continue;
                }
                $this->database->prepare(
                    'INSERT INTO cart_items (cart_id, product_id, quantity, price_snapshot)
                     VALUES (:cart_id, :product_id, :quantity, :price)
                     ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), price_snapshot = VALUES(price_snapshot)'
                )->execute(['cart_id' => $userCartId, 'product_id' => $productId, 'quantity' => $quantity, 'price' => $price]);
            }
            $this->database->prepare('DELETE FROM carts WHERE id = :id')->execute(['id' => $guestId]);
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function activeCartForUser(int $userId): int
    {
        return $this->cartId($userId, true);
    }

    private function cartId(?int $userId, bool $create = true): int
    {
        if ($userId !== null) {
            $query = $this->database->prepare('SELECT id FROM carts WHERE user_id = :user_id AND status = \'active\' ORDER BY id LIMIT 1');
            $query->execute(['user_id' => $userId]);
            $cartId = $query->fetchColumn();
            if ($cartId !== false) {
                return (int) $cartId;
            }
        } else {
            Session::start();
            if (!isset($_SESSION['_cart_key'])) {
                if (!$create) {
                    return 0;
                }
                $_SESSION['_cart_key'] = bin2hex(random_bytes(32));
            }
            $query = $this->database->prepare('SELECT id FROM carts WHERE session_key = :key AND status = \'active\'');
            $query->execute(['key' => $_SESSION['_cart_key']]);
            $cartId = $query->fetchColumn();
            if ($cartId !== false) {
                return (int) $cartId;
            }
        }
        if (!$create) {
            return 0;
        }
        Session::start();
        if ($userId === null && !isset($_SESSION['_cart_key'])) {
            $_SESSION['_cart_key'] = bin2hex(random_bytes(32));
        }
        $this->database->prepare('INSERT INTO carts (user_id, session_key, status) VALUES (:user_id, :session_key, \'active\')')
            ->execute(['user_id' => $userId, 'session_key' => $userId === null ? $_SESSION['_cart_key'] : null]);
        return (int) $this->database->lastInsertId();
    }

    private function maxQuantity(): int
    {
        $value = $this->database->query("SELECT setting_value FROM settings WHERE setting_key = 'cart_max_quantity'")->fetchColumn();
        $limit = filter_var($value, FILTER_VALIDATE_INT);
        return $limit !== false && $limit >= 1 && $limit <= 100 ? $limit : 20;
    }
}
