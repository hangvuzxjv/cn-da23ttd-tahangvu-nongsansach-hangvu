<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

function createTestCustomer(PDO $database, int $regionId, int $productId, int $quantity, ?string $voucherCode): array
{
    $email = 'order-race-' . bin2hex(random_bytes(6)) . '@example.test';
    $database->prepare(
        "INSERT INTO users (name, email, phone, password, role, status)
         VALUES ('Order Race Test', :email, '0900000000', :password, 'customer', 'active')"
    )->execute(['email' => $email, 'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT)]);
    $userId = (int) $database->lastInsertId();
    $database->prepare(
        "INSERT INTO addresses (user_id, recipient_name, phone, region_id, address_line, ward, is_default)
         VALUES (:user_id, 'Order Race Test', '0900000000', :region_id, 'Race test address', 'Race test ward', 1)"
    )->execute(['user_id' => $userId, 'region_id' => $regionId]);
    $addressId = (int) $database->lastInsertId();
    $database->prepare("INSERT INTO carts (user_id, status) VALUES (:user_id, 'active')")->execute(['user_id' => $userId]);
    $cartId = (int) $database->lastInsertId();
    $product = $database->prepare('SELECT COALESCE(NULLIF(sale_price, 0), price) FROM products WHERE id = :id');
    $product->execute(['id' => $productId]);
    $price = $product->fetchColumn();
    $database->prepare(
        'INSERT INTO cart_items (cart_id, product_id, quantity, price_snapshot) VALUES (:cart_id, :product_id, :quantity, :price)'
    )->execute(['cart_id' => $cartId, 'product_id' => $productId, 'quantity' => $quantity, 'price' => $price]);
    return [
        'user_id' => $userId,
        'address_id' => $addressId,
        'token' => bin2hex(random_bytes(32)),
        'voucher' => $voucherCode,
    ];
}

function runWorkers(array $workers, string $mode): array
{
    $running = [];
    foreach ($workers as $worker) {
        $process = proc_open(
            [PHP_BINARY, __FILE__, '--worker', $mode, (string) $worker['user_id'], (string) $worker['address_id'], (string) $worker['token'], (string) ($worker['voucher'] ?? '')],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start a checkout test worker.');
        }
        fclose($pipes[0]);
        $running[] = [$process, $pipes];
    }
    $results = ['success' => 0, 'unavailable' => 0, 'errors' => []];
    foreach ($running as [$process, $pipes]) {
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        if ($code === 0) {
            $results['success']++;
        } elseif ($code === 3) {
            $results['unavailable']++;
        } else {
            $results['errors'][] = trim($stderr . $stdout) ?: 'Worker exit code ' . $code;
        }
    }
    return $results;
}

if (($argv[1] ?? '') === '--worker') {
    $mode = (string) ($argv[2] ?? '');
    $userId = (int) ($argv[3] ?? 0);
    $addressId = (int) ($argv[4] ?? 0);
    $token = (string) ($argv[5] ?? '');
    $voucher = (string) ($argv[6] ?? '');
    try {
        (new OrderService(inventoryTestDatabase()))->place($userId, [
            'idempotency_token' => $token,
            'address_id' => $addressId,
            'voucher_code' => $mode === 'voucher' ? $voucher : '',
            'payment_method' => 'cod',
        ]);
        exit(0);
    } catch (InventoryException | PricingException $exception) {
        exit(3);
    } catch (Throwable $exception) {
        fwrite(fopen('php://stderr', 'w'), $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}

$database = inventoryTestDatabase();
$regionId = (int) $database->query(
    'SELECT sf.region_id FROM shipping_fees sf JOIN regions r ON r.id = sf.region_id
     WHERE sf.is_active = 1 AND r.level = 2 ORDER BY sf.region_id LIMIT 1'
)->fetchColumn();
$product = $database->query(
    "SELECT id, category_id, region_id, farm_id, unit, price
     FROM products WHERE id = 20 AND status = 'active' AND deleted_at IS NULL"
)->fetch();
if (!$product || !$product['farm_id'] || $regionId < 1) {
    throw new RuntimeException('Checkout race test requires seeded product 20, farm, and shipping fee.');
}

$database->beginTransaction();
try {
    $suffix = strtoupper(bin2hex(random_bytes(5)));
    $database->prepare(
        'INSERT INTO products (category_id, region_id, farm_id, name, slug, sku, unit, price, status, total_stock, min_stock)
         VALUES (:category_id, :region_id, :farm_id, :name, :slug, :sku, :unit, :price, \'active\', 0, 0)'
    )->execute([
        'category_id' => $product['category_id'],
        'region_id' => $product['region_id'],
        'farm_id' => $product['farm_id'],
        'name' => 'Concurrent order test ' . $suffix,
        'slug' => 'concurrent-order-test-' . strtolower($suffix),
        'sku' => 'NS-ORDER-TEST-' . $suffix,
        'unit' => $product['unit'],
        'price' => $product['price'],
    ]);
    $testProductId = (int) $database->lastInsertId();
    $batchCode = 'NS-TEST-ORDER-' . strtoupper(bin2hex(random_bytes(5)));
    $database->prepare(
        "INSERT INTO product_batches
            (product_id, farm_id, batch_code, qr_token, harvested_at, packed_at, expires_at,
             quantity_received, quantity_remaining, inspection_result, status)
         VALUES (:product_id, :farm_id, :code, :token, CURDATE(), CURDATE(),
             DATE_ADD(CURDATE(), INTERVAL 90 DAY), 0, 0, 'passed', 'selling')"
    )->execute([
        'product_id' => $testProductId,
        'farm_id' => $product['farm_id'],
        'code' => $batchCode,
        'token' => hash('sha256', random_bytes(32)),
    ]);
    $batchId = (int) $database->lastInsertId();
    (new InventoryService($database))->receive($batchId, 1, 'One-unit simultaneous checkout test');
    $stockUsers = [
        createTestCustomer($database, $regionId, $testProductId, 1, null),
        createTestCustomer($database, $regionId, $testProductId, 1, null),
    ];
    $database->commit();
} catch (Throwable $exception) {
    if ($database->inTransaction()) {
        $database->rollBack();
    }
    throw $exception;
}

$stockRace = runWorkers($stockUsers, 'stock');
$batchQuery = $database->prepare('SELECT quantity_remaining FROM product_batches WHERE id = :id');
$batchQuery->execute(['id' => $batchId]);
$stockRemaining = (int) $batchQuery->fetchColumn();
if ($stockRace['success'] !== 1 || $stockRace['unavailable'] !== 1 || $stockRemaining !== 0 || $stockRace['errors']) {
    throw new RuntimeException('One-unit order race failed: ' . json_encode($stockRace) . '; stock=' . $stockRemaining);
}
$winnerQuery = $database->prepare(
    "SELECT o.id, o.user_id FROM orders o
     JOIN inventory_movements m ON m.reference_type = 'order' AND m.reference_id = o.id
     WHERE m.batch_id = :batch_id AND m.movement_type = 'out' LIMIT 1"
);
$winnerQuery->execute(['batch_id' => $batchId]);
$winner = $winnerQuery->fetch();
(new OrderService($database))->cancel((int) $winner['id'], (int) $winner['user_id'], 'Race test cleanup cancellation');

$voucherCode = 'RACE-' . strtoupper(bin2hex(random_bytes(4)));
$database->prepare(
    "INSERT INTO vouchers
        (code, description, discount_type, discount_value, minimum_order, usage_limit, per_user_limit, starts_at, ends_at, status)
     VALUES (:code, 'Twenty-user one-use race test', 'fixed', 1000, 0, 1, 1,
        NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY), 'active')"
)->execute(['code' => $voucherCode]);
$voucherId = (int) $database->lastInsertId();
$voucherProductId = 1;
$voucherUsers = [];
$database->beginTransaction();
try {
    for ($index = 0; $index < 20; $index++) {
        $voucherUsers[] = createTestCustomer($database, $regionId, $voucherProductId, 1, $voucherCode);
    }
    $database->commit();
} catch (Throwable $exception) {
    if ($database->inTransaction()) {
        $database->rollBack();
    }
    throw $exception;
}
$voucherRace = runWorkers($voucherUsers, 'voucher');
$counter = $database->prepare('SELECT used_count FROM vouchers WHERE id = :id');
$counter->execute(['id' => $voucherId]);
$usedCount = (int) $counter->fetchColumn();
$usages = $database->prepare('SELECT COUNT(*) FROM voucher_usages WHERE voucher_id = :id AND status = \'used\'');
$usages->execute(['id' => $voucherId]);
$usageCount = (int) $usages->fetchColumn();
if ($voucherRace['success'] !== 1 || $voucherRace['unavailable'] !== 19
    || $usedCount !== 1 || $usageCount !== 1 || $voucherRace['errors']) {
    throw new RuntimeException('One-use voucher race failed: ' . json_encode($voucherRace) . '; counter=' . $usedCount . '; usage rows=' . $usageCount);
}
$winnerOrder = $database->prepare('SELECT order_id, user_id FROM voucher_usages WHERE voucher_id = :id AND status = \'used\'');
$winnerOrder->execute(['id' => $voucherId]);
$usage = $winnerOrder->fetch();
(new OrderService($database))->cancel((int) $usage['order_id'], (int) $usage['user_id'], 'Voucher race test cleanup cancellation');

printf(
    "PASS: two users buying one remaining unit => %d success, %d rejected, no stock; 20 users sharing one voucher => %d success, %d rejected, used_count=%d.\n",
    $stockRace['success'],
    $stockRace['unavailable'],
    $voucherRace['success'],
    $voucherRace['unavailable'],
    $usedCount
);
