<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$database = inventoryTestDatabase();
$regionId = (int) $database->query(
    'SELECT sf.region_id FROM shipping_fees sf JOIN regions r ON r.id = sf.region_id
     WHERE sf.is_active = 1 AND r.level = 2 ORDER BY sf.region_id LIMIT 1'
)->fetchColumn();
$product = $database->query(
    "SELECT p.id, p.category_id, COALESCE(NULLIF(p.sale_price, 0), p.price) AS price
     FROM products p WHERE p.status = 'active' AND p.deleted_at IS NULL
     ORDER BY p.id LIMIT 1"
)->fetch();
if (!$product || $regionId < 1) {
    throw new RuntimeException('Checkout test requires an active product and a configured shipping region.');
}

$database->beginTransaction();
try {
    $email = 'checkout-test-' . bin2hex(random_bytes(6)) . '@example.test';
    $database->prepare(
        "INSERT INTO users (name, email, phone, password, role, status)
         VALUES ('Checkout Test', :email, '0900000000', :password, 'customer', 'active')"
    )->execute(['email' => $email, 'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT)]);
    $userId = (int) $database->lastInsertId();
    $database->prepare(
        "INSERT INTO addresses (user_id, recipient_name, phone, region_id, address_line, ward, is_default)
         VALUES (:user_id, 'Checkout Test', '0900000000', :region_id, 'Test address', 'Test ward', 1)"
    )->execute(['user_id' => $userId, 'region_id' => $regionId]);
    $addressId = (int) $database->lastInsertId();
    $database->prepare("INSERT INTO carts (user_id, status) VALUES (:user_id, 'active')")->execute(['user_id' => $userId]);
    $cartId = (int) $database->lastInsertId();
    $database->prepare(
        'INSERT INTO cart_items (cart_id, product_id, quantity, price_snapshot)
         VALUES (:cart_id, :product_id, 2, :price)'
    )->execute(['cart_id' => $cartId, 'product_id' => $product['id'], 'price' => $product['price']]);
    $voucherCode = 'CHK-' . strtoupper(bin2hex(random_bytes(4)));
    $database->prepare(
        "INSERT INTO vouchers
            (code, description, discount_type, discount_value, minimum_order, usage_limit, per_user_limit, starts_at, ends_at, status)
         VALUES (:code, 'One-use checkout test', 'fixed', 1000, 0, 1, 1, NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY), 'active')"
    )->execute(['code' => $voucherCode]);
    $voucherId = (int) $database->lastInsertId();
    $database->commit();
} catch (Throwable $exception) {
    if ($database->inTransaction()) {
        $database->rollBack();
    }
    throw $exception;
}

$inventory = new InventoryService($database);
$availableBefore = $inventory->availableStock((int) $product['id']);
$token = bin2hex(random_bytes(32));
$input = [
    'idempotency_token' => $token,
    'address_id' => $addressId,
    'voucher_code' => $voucherCode,
    'payment_method' => 'cod',
];
$orders = new OrderService($database);
$first = $orders->place($userId, $input);
$duplicate = $orders->place($userId, $input);
if ($first['id'] !== $duplicate['id'] || empty($duplicate['existing'])) {
    throw new RuntimeException('A repeated submission created a second order.');
}

$amountQuery = $database->prepare('SELECT subtotal, discount_amount, shipping_fee, total_amount FROM orders WHERE id = :id');
$amountQuery->execute(['id' => $first['id']]);
$amounts = $amountQuery->fetch();
$itemsQuery = $database->prepare('SELECT COALESCE(SUM(line_total), 0) FROM order_items WHERE order_id = :id');
$itemsQuery->execute(['id' => $first['id']]);
$itemsTotal = (int) $itemsQuery->fetchColumn();
$expectedTotal = PricingService::moneyToVnd((string) $amounts['subtotal'])
    - PricingService::moneyToVnd((string) $amounts['discount_amount'])
    + PricingService::moneyToVnd((string) $amounts['shipping_fee']);
if ($itemsTotal !== PricingService::moneyToVnd((string) $amounts['subtotal'])
    || $expectedTotal !== PricingService::moneyToVnd((string) $amounts['total_amount'])) {
    throw new RuntimeException('Order totals differ from PricingService quote.');
}
$usedCount = $database->prepare('SELECT used_count FROM vouchers WHERE id = :id');
$usedCount->execute(['id' => $voucherId]);
if ((int) $usedCount->fetchColumn() !== 1) {
    throw new RuntimeException('Voucher counter was not incremented atomically.');
}

$orders->cancel((int) $first['id'], $userId, 'Automated checkout cancellation test');
$availableAfter = $inventory->availableStock((int) $product['id']);
$usedCount->execute(['id' => $voucherId]);
$usageStatus = $database->prepare('SELECT status FROM voucher_usages WHERE order_id = :id');
$usageStatus->execute(['id' => $first['id']]);
if ($availableBefore !== $availableAfter || (int) $usedCount->fetchColumn() !== 0
    || $usageStatus->fetchColumn() !== 'reversed') {
    throw new RuntimeException('Cancellation did not restore inventory and voucher use.');
}

$cartQuery = $database->prepare('SELECT id FROM carts WHERE user_id = :user_id AND status = \'active\'');
$cartQuery->execute(['user_id' => $userId]);
$database->prepare(
    'INSERT INTO cart_items (cart_id, product_id, quantity, price_snapshot)
     VALUES (:cart_id, :product_id, 1, :price)'
)->execute(['cart_id' => $cartQuery->fetchColumn(), 'product_id' => $product['id'], 'price' => $product['price']]);
$delivery = $orders->place($userId, [
    'idempotency_token' => bin2hex(random_bytes(32)),
    'address_id' => $addressId,
    'payment_method' => 'cod',
]);
foreach (['confirmed', 'processing', 'shipping', 'completed'] as $status) {
    $orders->transition((int) $delivery['id'], $userId, $status, 'Automated COD transition test', true);
}
$paymentQuery = $database->prepare(
    'SELECT o.payment_status, p.status FROM orders o JOIN payments p ON p.order_id = o.id WHERE o.id = :id'
);
$paymentQuery->execute(['id' => $delivery['id']]);
$paidStatus = $paymentQuery->fetch();
if ($paidStatus['payment_status'] !== 'paid' || $paidStatus['status'] !== 'paid') {
    throw new RuntimeException('COD payment was not marked paid when the order was delivered.');
}
$orders->transition((int) $delivery['id'], $userId, 'refunded', 'Automated refund transition test', true);
$paymentQuery->execute(['id' => $delivery['id']]);
$refundedStatus = $paymentQuery->fetch();
if ($refundedStatus['payment_status'] !== 'refunded' || $refundedStatus['status'] !== 'refunded') {
    throw new RuntimeException('COD payment status was not updated when the delivered order was refunded.');
}
$availableAfterRefund = $inventory->availableStock((int) $product['id']);
if ($availableAfterRefund !== $availableBefore) {
    throw new RuntimeException('Refund did not restore the delivered order inventory.');
}

printf(
    "PASS: order %s idempotent; line subtotal=%d total=%d; cancellation restored stock=%d and reversed voucher; delivery/refund updated COD payment and restored stock.\n",
    $first['order_no'],
    $itemsTotal,
    PricingService::moneyToVnd((string) $amounts['total_amount']),
    $availableAfter
);
