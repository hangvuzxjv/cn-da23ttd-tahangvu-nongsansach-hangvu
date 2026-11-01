<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

function flashSaleAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function flashSaleCreateUser(PDO $database): int
{
    $email = 'flash-sale-' . bin2hex(random_bytes(8)) . '@example.test';
    $database->prepare(
        "INSERT INTO users (name, email, phone, password, role, status)
         VALUES ('Flash Sale Test', :email, '0900000000', :password, 'customer', 'active')"
    )->execute([
        'email' => $email,
        'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
    ]);
    return (int) $database->lastInsertId();
}

function flashSaleCreateCampaign(PDO $database, array &$campaignIds, string $window, int $productId, int $quota, int $perUserLimit): int
{
    $name = 'Flash Sale Test ' . strtoupper(bin2hex(random_bytes(6)));
    $times = $window === 'future'
        ? "DATE_ADD(DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 HOUR), INTERVAL 1 HOUR),
           DATE_ADD(DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 HOUR), INTERVAL 2 HOUR), 'scheduled'"
        : "DATE_SUB(DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 HOUR), INTERVAL 1 HOUR),
           DATE_ADD(DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 HOUR), INTERVAL 1 HOUR), 'active'";
    $database->prepare(
        "INSERT INTO flash_sales (name, starts_at, ends_at, status) VALUES (:name, {$times})"
    )->execute(['name' => $name]);
    $campaignId = (int) $database->lastInsertId();
    $campaignIds[] = $campaignId;
    $database->prepare(
        'INSERT INTO flash_sale_items
            (flash_sale_id, product_id, sale_price, quota, sold_quantity, per_user_limit)
         VALUES (:campaign_id, :product_id, 1000, :quota, 0, :per_user_limit)'
    )->execute([
        'campaign_id' => $campaignId,
        'product_id' => $productId,
        'quota' => $quota,
        'per_user_limit' => $perUserLimit,
    ]);
    return (int) $database->lastInsertId();
}

function flashSaleCreateOrder(PDO $database, int $userId, array &$orderIds): int
{
    $orderNo = 'FS-TEST-' . strtoupper(bin2hex(random_bytes(8)));
    $database->prepare(
        "INSERT INTO orders
            (user_id, order_no, status, payment_status, recipient_name, recipient_phone,
             shipping_address, subtotal, total_amount)
         VALUES (:user_id, :order_no, 'pending', 'unpaid', 'Flash Sale Test',
                 '0900000000', 'Test address', 0, 0)"
    )->execute(['user_id' => $userId, 'order_no' => $orderNo]);
    $orderId = (int) $database->lastInsertId();
    $orderIds[] = $orderId;
    return $orderId;
}

function flashSaleAttempt(PDO $database, int $itemId, int $userId, int $orderId, int $quantity): ?Throwable
{
    $database->beginTransaction();
    try {
        (new FlashSaleService($database))->recordOrderPurchases($orderId, $userId, [[
            'flash_sale_item_id' => $itemId,
            'quantity' => $quantity,
        ]], false);
        $database->commit();
        return null;
    } catch (Throwable $exception) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        return $exception;
    }
}

function flashSaleWorker(int $itemId, int $userId, array $orderIds, float $startAt): never
{
    $delay = $startAt - microtime(true);
    if ($delay > 0) {
        usleep((int) ($delay * 1000000));
    }
    $database = inventoryTestDatabase();
    $success = 0;
    $rejected = 0;
    foreach ($orderIds as $orderId) {
        $exception = flashSaleAttempt($database, $itemId, $userId, (int) $orderId, 1);
        if ($exception === null) {
            $success++;
        } elseif ($exception instanceof InventoryException) {
            $rejected++;
        } else {
            fwrite(STDERR, $exception->getMessage() . PHP_EOL);
            exit(1);
        }
    }
    echo json_encode(['success' => $success, 'rejected' => $rejected], JSON_THROW_ON_ERROR);
    exit(0);
}

function flashSaleRunConcurrentWorkers(int $itemId, int $userId, array $orderIds): array
{
    $groups = array_chunk($orderIds, 10);
    $startAt = microtime(true) + 5;
    $running = [];
    foreach ($groups as $group) {
        $process = proc_open(
            [
                PHP_BINARY,
                __FILE__,
                '--worker',
                (string) $itemId,
                (string) $userId,
                implode(',', $group),
                (string) $startAt,
            ],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start a Flash Sale concurrency worker.');
        }
        fclose($pipes[0]);
        $running[] = [$process, $pipes];
    }
    $result = ['success' => 0, 'rejected' => 0];
    foreach ($running as [$process, $pipes]) {
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        if ($code !== 0) {
            throw new RuntimeException(trim($stderr . $stdout) ?: 'Flash Sale worker exited with code ' . $code);
        }
        $workerResult = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        $result['success'] += (int) $workerResult['success'];
        $result['rejected'] += (int) $workerResult['rejected'];
    }
    return $result;
}

if (($argv[1] ?? '') === '--worker') {
    flashSaleWorker(
        (int) ($argv[2] ?? 0),
        (int) ($argv[3] ?? 0),
        array_map('intval', explode(',', (string) ($argv[4] ?? ''))),
        (float) ($argv[5] ?? 0)
    );
}

$database = inventoryTestDatabase();
$campaignIds = [];
$orderIds = [];
$userIds = [];
$addressIds = [];
$cartIds = [];
$createdProductIds = [];
$createdBatchIds = [];
$passed = [];

try {
    $regionId = (int) $database->query(
        "SELECT sf.region_id FROM shipping_fees sf
         JOIN regions r ON r.id = sf.region_id
         WHERE sf.is_active = 1 AND r.level = 2 ORDER BY sf.region_id LIMIT 1"
    )->fetchColumn();
    $productIds = array_map('intval', $database->query(
        'SELECT id FROM products WHERE id IN (4,5,6,7,20) AND status = \'active\' ORDER BY id'
    )->fetchAll(PDO::FETCH_COLUMN));
    if ($regionId < 1 || count($productIds) !== 5) {
        throw new RuntimeException('Flash Sale tests require seeded products 4-7, 20 and an active shipping region.');
    }

    $userId = flashSaleCreateUser($database);
    $userIds[] = $userId;

    $quotaItem = flashSaleCreateCampaign($database, $campaignIds, 'active', 4, 2, 2);
    $exactOrder = flashSaleCreateOrder($database, $userId, $orderIds);
    $error = flashSaleAttempt($database, $quotaItem, $userId, $exactOrder, 2);
    flashSaleAssert($error === null, 'Buying exactly the quota should succeed.');
    $overQuotaOrder = flashSaleCreateOrder($database, $userId, $orderIds);
    $error = flashSaleAttempt($database, $quotaItem, $userId, $overQuotaOrder, 1);
    flashSaleAssert($error instanceof InventoryException, 'Buying above the quota should be rejected.');
    $soldQuery = $database->prepare('SELECT sold_quantity FROM flash_sale_items WHERE id = :id');
    $soldQuery->execute(['id' => $quotaItem]);
    flashSaleAssert((int) $soldQuery->fetchColumn() === 2, 'Quota sold count should remain exactly 2.');
    $passed[] = 'exact quota and over-quota rejection';

    $limitedItem = flashSaleCreateCampaign($database, $campaignIds, 'active', 5, 10, 1);
    $firstLimitedOrder = flashSaleCreateOrder($database, $userId, $orderIds);
    flashSaleAssert(
        flashSaleAttempt($database, $limitedItem, $userId, $firstLimitedOrder, 1) === null,
        'First purchase within the per-user limit should succeed.'
    );
    $secondLimitedOrder = flashSaleCreateOrder($database, $userId, $orderIds);
    $error = flashSaleAttempt($database, $limitedItem, $userId, $secondLimitedOrder, 1);
    flashSaleAssert($error instanceof InvalidArgumentException, 'Purchases over the per-user limit should be rejected.');
    $soldQuery->execute(['id' => $limitedItem]);
    flashSaleAssert((int) $soldQuery->fetchColumn() === 1, 'Rejected per-user purchase must not increase sold count.');
    $passed[] = 'per-user limit';

    $futureItem = flashSaleCreateCampaign($database, $campaignIds, 'future', 7, 5, 2);
    $futureOrder = flashSaleCreateOrder($database, $userId, $orderIds);
    $error = flashSaleAttempt($database, $futureItem, $userId, $futureOrder, 1);
    flashSaleAssert($error instanceof InventoryException, 'Purchases outside the live campaign window should be rejected.');
    $soldQuery->execute(['id' => $futureItem]);
    flashSaleAssert((int) $soldQuery->fetchColumn() === 0, 'Out-of-window purchase must not change sold count.');
    $passed[] = 'out-of-window rejection';

    $inventoryTemplate = $database->query(
        'SELECT category_id, region_id, farm_id, unit, price
         FROM products WHERE id = 20 AND status = \'active\''
    )->fetch();
    $productSuffix = strtoupper(bin2hex(random_bytes(6)));
    $database->prepare(
        'INSERT INTO products
            (category_id, region_id, farm_id, name, slug, sku, unit, price, status, total_stock, min_stock)
         VALUES (:category_id, :region_id, :farm_id, :name, :slug, :sku, :unit, :price, \'active\', 0, 0)'
    )->execute([
        'category_id' => $inventoryTemplate['category_id'],
        'region_id' => $inventoryTemplate['region_id'],
        'farm_id' => $inventoryTemplate['farm_id'],
        'name' => 'Flash Sale inventory test ' . $productSuffix,
        'slug' => 'flash-sale-inventory-test-' . strtolower($productSuffix),
        'sku' => 'NS-FS-TEST-' . $productSuffix,
        'unit' => $inventoryTemplate['unit'],
        'price' => $inventoryTemplate['price'],
    ]);
    $testProductId = (int) $database->lastInsertId();
    $createdProductIds[] = $testProductId;
    $batchCode = 'FS-TEST-BATCH-' . $productSuffix;
    $database->prepare(
        "INSERT INTO product_batches
            (product_id, farm_id, batch_code, qr_token, harvested_at, packed_at, expires_at,
             quantity_received, quantity_remaining, inspection_result, status)
         VALUES (:product_id, :farm_id, :batch_code, :qr_token, CURDATE(), CURDATE(),
                 DATE_ADD(CURDATE(), INTERVAL 90 DAY), 0, 0, 'passed', 'selling')"
    )->execute([
        'product_id' => $testProductId,
        'farm_id' => $inventoryTemplate['farm_id'],
        'batch_code' => $batchCode,
        'qr_token' => hash('sha256', random_bytes(32)),
    ]);
    $batchId = (int) $database->lastInsertId();
    $createdBatchIds[] = $batchId;
    (new InventoryService($database))->receive($batchId, 3, 'Flash Sale test inventory');

    $cancelItem = flashSaleCreateCampaign($database, $campaignIds, 'active', $testProductId, 5, 1);
    $address = $database->prepare(
        "INSERT INTO addresses (user_id, recipient_name, phone, region_id, address_line, ward, is_default)
         VALUES (:user_id, 'Flash Sale Test', '0900000000', :region_id, 'Test address', 'Test ward', 1)"
    );
    $address->execute(['user_id' => $userId, 'region_id' => $regionId]);
    $addressId = (int) $database->lastInsertId();
    $addressIds[] = $addressId;
    $database->prepare("INSERT INTO carts (user_id, status) VALUES (:user_id, 'active')")
        ->execute(['user_id' => $userId]);
    $cartId = (int) $database->lastInsertId();
    $cartIds[] = $cartId;

    $cartService = new CartService($database);
    $cartService->add($testProductId, 1, $userId);
    $cart = $cartService->summary($userId);
    $cartItem = $cart['items'][0] ?? null;
    flashSaleAssert(
        $cartItem !== null
            && (int) $cartItem['flash_sale_item_id'] === $cancelItem
            && (int) $cartItem['current_price'] === 1000,
        'Cart should apply the active Flash Sale price.'
    );
    $placed = (new OrderService($database))->place($userId, [
        'idempotency_token' => bin2hex(random_bytes(32)),
        'address_id' => $addressId,
        'payment_method' => 'cod',
    ]);
    $orderIds[] = (int) $placed['id'];
    $orderItemQuery = $database->prepare(
        'SELECT flash_sale_item_id, unit_price FROM order_items WHERE order_id = :order_id'
    );
    $orderItemQuery->execute(['order_id' => $placed['id']]);
    $orderItem = $orderItemQuery->fetch();
    flashSaleAssert(
        $orderItem
            && (int) $orderItem['flash_sale_item_id'] === $cancelItem
            && PricingService::moneyToVnd((string) $orderItem['unit_price']) === 1000,
        'Order should retain the same Flash Sale item and price as the cart.'
    );
    $soldQuery->execute(['id' => $cancelItem]);
    flashSaleAssert((int) $soldQuery->fetchColumn() === 1, 'Order should reserve one Flash Sale unit.');
    (new OrderService($database))->cancel((int) $placed['id'], $userId, 'Flash Sale test cancellation');
    $soldQuery->execute(['id' => $cancelItem]);
    flashSaleAssert((int) $soldQuery->fetchColumn() === 0, 'Cancelling an order should restore its Flash Sale quota.');
    $purchaseQuery = $database->prepare(
        'SELECT status FROM flash_sale_purchases WHERE order_id = :order_id'
    );
    $purchaseQuery->execute(['order_id' => $placed['id']]);
    flashSaleAssert($purchaseQuery->fetchColumn() === 'released', 'Cancelled order purchase should be marked released.');
    $passed[] = 'cart/order flash price and cancellation rollback';

    $expiredOrder = flashSaleCreateOrder($database, $userId, $orderIds);
    $database->prepare("UPDATE orders SET payment_status = 'pending' WHERE id = :id")
        ->execute(['id' => $expiredOrder]);
    $database->prepare(
        "INSERT INTO payments (order_id, method, amount, status)
         VALUES (:order_id, 'bank_transfer', 1000, 'pending')"
    )->execute(['order_id' => $expiredOrder]);
    (new InventoryService($database))->reserveForOrder(
        $expiredOrder,
        [['product_id' => $testProductId, 'quantity' => 1]],
        (new DateTimeImmutable())->modify('+10 minutes')
    );
    $database->beginTransaction();
    try {
        (new FlashSaleService($database))->recordOrderPurchases($expiredOrder, $userId, [[
            'flash_sale_item_id' => $cancelItem,
            'quantity' => 1,
        ]], true);
        $database->commit();
    } catch (Throwable $exception) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        throw $exception;
    }
    $database->prepare(
        "UPDATE stock_reservations SET expires_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE)
         WHERE order_id = :order_id AND status = 'reserved'"
    )->execute(['order_id' => $expiredOrder]);
    (new InventoryService($database))->expireReservations();
    $soldQuery->execute(['id' => $cancelItem]);
    flashSaleAssert((int) $soldQuery->fetchColumn() === 0, 'Expired stock hold should restore Flash Sale quota.');
    $purchaseQuery->execute(['order_id' => $expiredOrder]);
    flashSaleAssert($purchaseQuery->fetchColumn() === 'released', 'Expired hold purchase should be marked released.');
    $passed[] = 'expired stock-hold rollback';

    $raceItem = flashSaleCreateCampaign($database, $campaignIds, 'active', 6, 20, 200);
    $raceOrderIds = [];
    for ($index = 0; $index < 200; $index++) {
        flashSaleCreateOrder($database, $userId, $raceOrderIds);
    }
    array_push($orderIds, ...$raceOrderIds);
    $race = flashSaleRunConcurrentWorkers($raceItem, $userId, $raceOrderIds);
    $soldQuery->execute(['id' => $raceItem]);
    $raceSold = (int) $soldQuery->fetchColumn();
    flashSaleAssert(
        $race['success'] === 20 && $race['rejected'] === 180 && $raceSold === 20,
        '200 competing purchases must produce exactly 20 successes and sold=20; got '
            . json_encode($race) . ', sold=' . $raceSold
    );
    $passed[] = '200 competing requests (20 workers), exactly 20 quota successes';
} finally {
    if ($database->inTransaction()) {
        $database->rollBack();
    }
    $database->beginTransaction();
    try {
        if ($orderIds) {
            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            $database->prepare(
                "DELETE FROM inventory_movements
                 WHERE reference_type IN ('order','order_restore') AND reference_id IN ({$placeholders})"
            )->execute($orderIds);
            $database->prepare(
                "DELETE FROM audit_logs WHERE entity_type = 'order' AND entity_id IN ({$placeholders})"
            )->execute($orderIds);
        }
        if ($campaignIds) {
            $placeholders = implode(',', array_fill(0, count($campaignIds), '?'));
            $database->prepare("DELETE FROM flash_sales WHERE id IN ({$placeholders})")->execute($campaignIds);
        }
        if ($orderIds) {
            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            $database->prepare("DELETE FROM orders WHERE id IN ({$placeholders})")->execute($orderIds);
        }
        if ($createdBatchIds) {
            $placeholders = implode(',', array_fill(0, count($createdBatchIds), '?'));
            $database->prepare("DELETE FROM inventory_movements WHERE batch_id IN ({$placeholders})")
                ->execute($createdBatchIds);
            $database->prepare("DELETE FROM product_batches WHERE id IN ({$placeholders})")
                ->execute($createdBatchIds);
        }
        if ($createdProductIds) {
            $placeholders = implode(',', array_fill(0, count($createdProductIds), '?'));
            $database->prepare("DELETE FROM products WHERE id IN ({$placeholders})")
                ->execute($createdProductIds);
        }
        foreach ($userIds as $userId) {
            $database->prepare(
                'DELETE FROM flash_sale_rate_limits WHERE bucket_key LIKE :user_key OR bucket_key LIKE :ip_key'
            )->execute([
                'user_key' => 'user:' . $userId . ':%',
                'ip_key' => 'ip:' . hash('sha256', 'unknown') . ':%',
            ]);
        }
        if ($cartIds) {
            $placeholders = implode(',', array_fill(0, count($cartIds), '?'));
            $database->prepare("DELETE FROM carts WHERE id IN ({$placeholders})")->execute($cartIds);
        }
        if ($addressIds) {
            $placeholders = implode(',', array_fill(0, count($addressIds), '?'));
            $database->prepare("DELETE FROM addresses WHERE id IN ({$placeholders})")->execute($addressIds);
        }
        if ($userIds) {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $database->prepare("DELETE FROM users WHERE id IN ({$placeholders})")->execute($userIds);
        }
        $database->commit();
    } catch (Throwable $cleanupException) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        throw new RuntimeException('Flash Sale test cleanup failed: ' . $cleanupException->getMessage(), 0, $cleanupException);
    }
}

foreach ($passed as $case) {
    echo '[PASS] ' . $case . PHP_EOL;
}
echo 'All Flash Sale tests passed.' . PHP_EOL;
