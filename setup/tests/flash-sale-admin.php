<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

function flashSaleAdminAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function flashSaleAdminRejects(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException($message);
}

function flashSaleAdminCampaignInput(string $name, string $starts, string $ends, int $productId, int $price, int $quota = 5): array
{
    return [
        'name' => $name,
        'starts_at' => $starts,
        'ends_at' => $ends,
        'status' => 'scheduled',
        'product_id' => [(string) $productId],
        'sale_price' => [(string) $price],
        'quota' => [(string) $quota],
        'per_user_limit' => ['2'],
        'sort_order' => ['0'],
    ];
}

$database = inventoryTestDatabase();
$campaignIds = [];
$productIds = [];
$orderIds = [];
$userId = 0;
$passed = [];

try {
    $template = $database->query(
        "SELECT category_id, region_id, farm_id, unit, price
         FROM products WHERE id = 20 AND status = 'active'"
    )->fetch();
    if (!$template) {
        throw new RuntimeException('Flash Sale admin tests require active seeded product #20.');
    }
    $userEmail = 'flash-sale-admin-' . bin2hex(random_bytes(8)) . '@example.test';
    $database->prepare(
        "INSERT INTO users (name, email, phone, password, role, status)
         VALUES ('Flash Sale Admin Test', :email, '0900000000', :password, 'customer', 'active')"
    )->execute([
        'email' => $userEmail,
        'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
    ]);
    $userId = (int) $database->lastInsertId();

    for ($index = 0; $index < 3; $index++) {
        $suffix = strtoupper(bin2hex(random_bytes(6)));
        $database->prepare(
            "INSERT INTO products
                (category_id, region_id, farm_id, name, slug, sku, unit, price, status, total_stock, min_stock)
             VALUES (:category_id, :region_id, :farm_id, :name, :slug, :sku, :unit, :price, 'active', 0, 0)"
        )->execute([
            'category_id' => $template['category_id'],
            'region_id' => $template['region_id'],
            'farm_id' => $template['farm_id'],
            'name' => 'Flash Sale Admin Test ' . $suffix,
            'slug' => 'flash-sale-admin-test-' . strtolower($suffix),
            'sku' => 'NS-FS-ADMIN-' . $suffix,
            'unit' => $template['unit'],
            'price' => $template['price'],
        ]);
        $productIds[] = (int) $database->lastInsertId();
    }

    $normalPrice = PricingService::moneyToVnd((string) $template['price']);
    if ($normalPrice < 3) {
        throw new RuntimeException('Seed product price must be at least 3 VND for admin validation tests.');
    }
    $start = (new DateTimeImmutable('+2 days', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d\TH:i');
    $end = (new DateTimeImmutable('+2 days +1 hour', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d\TH:i');
    $service = new FlashSaleService($database);

    flashSaleAdminRejects(
        static fn () => $service->saveCampaign(
            flashSaleAdminCampaignInput('Thời gian sai', $end, $start, $productIds[0], $normalPrice - 1),
            null,
            $userId
        ),
        'A campaign with end time before start time must be rejected.'
    );
    $passed[] = 'invalid campaign time rejected';

    flashSaleAdminRejects(
        static fn () => $service->saveCampaign(
            flashSaleAdminCampaignInput('Giá flash cao', $start, $end, $productIds[0], $normalPrice),
            null,
            $userId
        ),
        'A Flash Sale price equal to the regular price must be rejected.'
    );
    $passed[] = 'Flash Sale price at or above regular price rejected';

    $firstCampaign = $service->saveCampaign(
        flashSaleAdminCampaignInput('Chiến dịch chồng lấn A', $start, $end, $productIds[0], $normalPrice - 1),
        null,
        $userId
    );
    $campaignIds[] = $firstCampaign;
    $summaries = $service->adminCampaigns();
    $summary = array_values(array_filter(
        $summaries,
        static fn (array $row): bool => (int) $row['id'] === $firstCampaign
    ))[0] ?? null;
    $detail = $service->campaignForAdmin($firstCampaign);
    flashSaleAdminAssert(
        $summary !== null && (int) $summary['quota'] === 5 && (int) $summary['sold'] === 0
            && $summary['campaign_status'] === 'scheduled'
            && $detail !== null && count($detail['items']) === 1
            && array_key_exists('remaining', $detail['items'][0])
            && array_key_exists('revenue', $detail['items'][0]),
        'Admin list and campaign detail should provide correct inventory and sales aggregates.'
    );
    $searchResults = $service->searchProducts('Flash Sale Admin Test');
    flashSaleAdminAssert(
        in_array($productIds[0], array_map('intval', array_column($searchResults, 'id')), true),
        'Admin product search should return the matching active product.'
    );
    $passed[] = 'admin campaign/product queries and aggregates';
    flashSaleAdminRejects(
        static fn () => $service->saveCampaign(
            flashSaleAdminCampaignInput('Chiến dịch chồng lấn B', $start, $end, $productIds[0], $normalPrice - 1),
            null,
            $userId
        ),
        'The same product cannot be added to overlapping campaigns.'
    );
    $passed[] = 'overlapping product campaigns rejected';

    $quotaCampaign = $service->saveCampaign(
        flashSaleAdminCampaignInput('Quota đã bán', $start, $end, $productIds[1], $normalPrice - 1, 3),
        null,
        $userId
    );
    $campaignIds[] = $quotaCampaign;
    $quotaItem = $database->prepare('SELECT id FROM flash_sale_items WHERE flash_sale_id = :id');
    $quotaItem->execute(['id' => $quotaCampaign]);
    $quotaItemId = (int) $quotaItem->fetchColumn();
    $database->prepare('UPDATE flash_sale_items SET sold_quantity = 2 WHERE id = :id')
        ->execute(['id' => $quotaItemId]);
    $reducedQuota = flashSaleAdminCampaignInput('Quota đã bán', $start, $end, $productIds[1], $normalPrice - 1, 1);
    flashSaleAdminRejects(
        static fn () => $service->saveCampaign($reducedQuota, $quotaCampaign, $userId),
        'Quota cannot be reduced below sold quantity.'
    );
    $passed[] = 'quota below sold quantity rejected';

    $deleteCampaign = $service->saveCampaign(
        flashSaleAdminCampaignInput('Chương trình có đơn', $start, $end, $productIds[2], $normalPrice - 1),
        null,
        $userId
    );
    $campaignIds[] = $deleteCampaign;
    $deleteItem = $database->prepare('SELECT id FROM flash_sale_items WHERE flash_sale_id = :id');
    $deleteItem->execute(['id' => $deleteCampaign]);
    $deleteItemId = (int) $deleteItem->fetchColumn();
    $orderNo = 'FS-ADMIN-TEST-' . strtoupper(bin2hex(random_bytes(6)));
    $database->prepare(
        "INSERT INTO orders
            (user_id, order_no, status, payment_status, recipient_name, recipient_phone,
             shipping_address, subtotal, total_amount)
         VALUES (:user_id, :order_no, 'pending', 'unpaid', 'Flash Sale Admin Test',
                 '0900000000', 'Test address', 0, 0)"
    )->execute(['user_id' => $userId, 'order_no' => $orderNo]);
    $orderId = (int) $database->lastInsertId();
    $orderIds[] = $orderId;
    $database->prepare(
        'INSERT INTO flash_sale_purchases
            (flash_sale_item_id, user_id, order_id, quantity, status)
         VALUES (:item_id, :user_id, :order_id, 1, \'confirmed\')'
    )->execute(['item_id' => $deleteItemId, 'user_id' => $userId, 'order_id' => $orderId]);
    $database->prepare('UPDATE flash_sale_items SET sold_quantity = 1 WHERE id = :id')
        ->execute(['id' => $deleteItemId]);
    $laterStart = (new DateTimeImmutable($start, new DateTimeZone('Asia/Ho_Chi_Minh')))
        ->modify('+1 hour')->format('Y-m-d\TH:i');
    $laterEnd = (new DateTimeImmutable($end, new DateTimeZone('Asia/Ho_Chi_Minh')))
        ->modify('+1 hour')->format('Y-m-d\TH:i');
    flashSaleAdminRejects(
        static fn () => $service->saveCampaign(
            flashSaleAdminCampaignInput('Chương trình có đơn', $laterStart, $laterEnd, $productIds[2], $normalPrice - 1),
            $deleteCampaign,
            $userId
        ),
        'Campaign time cannot be changed after an order has been placed.'
    );
    $passed[] = 'campaign time cannot change after an order';
    flashSaleAdminRejects(
        static fn () => $service->deleteCampaign($deleteCampaign, $userId),
        'A campaign with an order must not be deleted.'
    );
    $passed[] = 'campaign with order cannot be deleted';

    echo 'Passed Flash Sale admin validation tests:' . PHP_EOL;
    foreach ($passed as $test) {
        echo '- ' . $test . PHP_EOL;
    }
} finally {
    foreach ($campaignIds as $campaignId) {
        $database->prepare('DELETE FROM flash_sales WHERE id = :id')->execute(['id' => $campaignId]);
    }
    foreach ($orderIds as $orderId) {
        $database->prepare('DELETE FROM flash_sale_purchases WHERE order_id = :id')->execute(['id' => $orderId]);
        $database->prepare('DELETE FROM orders WHERE id = :id')->execute(['id' => $orderId]);
    }
    if ($userId > 0) {
        $database->prepare('DELETE FROM flash_sale_purchases WHERE user_id = :id')->execute(['id' => $userId]);
        $database->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);
    }
    foreach ($productIds as $productId) {
        $database->prepare('DELETE FROM products WHERE id = :id')->execute(['id' => $productId]);
    }
}
