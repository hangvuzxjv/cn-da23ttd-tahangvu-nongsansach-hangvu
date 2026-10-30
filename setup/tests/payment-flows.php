<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$testPaymentConfig = [
    'app' => ['url' => 'http://localhost'],
    'vnpay' => [
        'tmn_code' => 'TESTTMN',
        'hash_secret' => 'test-only-secret',
        'url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
    ],
];

function paymentTestCustomer(PDO $database, int $regionId, int $productId, int $quantity): array
{
    $email = 'payment-test-' . bin2hex(random_bytes(6)) . '@example.test';
    $database->prepare(
        "INSERT INTO users (name, email, phone, password, role, status)
         VALUES ('Payment Test', :email, '0900000000', :password, 'customer', 'active')"
    )->execute(['email' => $email, 'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT)]);
    $userId = (int) $database->lastInsertId();
    $database->prepare(
        "INSERT INTO addresses (user_id, recipient_name, phone, region_id, address_line, ward, is_default)
         VALUES (:user_id, 'Payment Test', '0900000000', :region_id, 'Payment test address', 'Test ward', 1)"
    )->execute(['user_id' => $userId, 'region_id' => $regionId]);
    $addressId = (int) $database->lastInsertId();
    $database->prepare("INSERT INTO carts (user_id, status) VALUES (:user_id, 'active')")->execute(['user_id' => $userId]);
    $cartId = (int) $database->lastInsertId();
    $priceQuery = $database->prepare('SELECT COALESCE(NULLIF(sale_price, 0), price) FROM products WHERE id = :id');
    $priceQuery->execute(['id' => $productId]);
    $database->prepare(
        'INSERT INTO cart_items (cart_id, product_id, quantity, price_snapshot)
         VALUES (:cart_id, :product_id, :quantity, :price)'
    )->execute([
        'cart_id' => $cartId,
        'product_id' => $productId,
        'quantity' => $quantity,
        'price' => $priceQuery->fetchColumn(),
    ]);
    return ['user_id' => $userId, 'address_id' => $addressId, 'token' => bin2hex(random_bytes(32))];
}

function paymentTestProduct(PDO $database, int $quantity): array
{
    $template = $database->query(
        "SELECT category_id, region_id, farm_id, unit, price
         FROM products WHERE status = 'active' AND deleted_at IS NULL AND farm_id IS NOT NULL
         ORDER BY id LIMIT 1"
    )->fetch();
    if (!$template) {
        throw new RuntimeException('Payment test needs one active product and farm in the test seed.');
    }
    $suffix = strtoupper(bin2hex(random_bytes(5)));
    $database->prepare(
        "INSERT INTO products (category_id, region_id, farm_id, name, slug, sku, unit, price, status, total_stock, min_stock)
         VALUES (:category_id, :region_id, :farm_id, :name, :slug, :sku, :unit, :price, 'active', 0, 0)"
    )->execute([
        'category_id' => $template['category_id'],
        'region_id' => $template['region_id'],
        'farm_id' => $template['farm_id'],
        'name' => 'Payment test product ' . $suffix,
        'slug' => 'payment-test-' . strtolower($suffix),
        'sku' => 'NS-PAYMENT-TEST-' . $suffix,
        'unit' => $template['unit'],
        'price' => $template['price'],
    ]);
    $productId = (int) $database->lastInsertId();
    $database->prepare(
        "INSERT INTO product_batches
            (product_id, farm_id, batch_code, qr_token, harvested_at, packed_at, expires_at,
             quantity_received, quantity_remaining, inspection_result, status)
         VALUES (:product_id, :farm_id, :batch_code, :qr_token, CURDATE(), CURDATE(),
                 DATE_ADD(CURDATE(), INTERVAL 90 DAY), 0, 0, 'passed', 'selling')"
    )->execute([
        'product_id' => $productId,
        'farm_id' => $template['farm_id'],
        'batch_code' => 'NS-TEST-PAY-' . $suffix,
        'qr_token' => hash('sha256', random_bytes(32)),
    ]);
    $batchId = (int) $database->lastInsertId();
    (new InventoryService($database))->receive($batchId, $quantity, 'Payment flow test stock');
    return [
        'product_id' => $productId,
        'batch_id' => $batchId,
        'category_id' => (int) $template['category_id'],
        'price' => (string) $template['price'],
    ];
}

function signPaymentTestCallback(array $parameters, string $secret): array
{
    $parameters['vnp_SecureHash'] = VnpaySignature::sign($parameters, $secret);
    return $parameters;
}

function startPaymentRaceWorker(int $userId, int $addressId, string $token): array
{
    $process = proc_open(
        [PHP_BINARY, __FILE__, '--race-worker', (string) $userId, (string) $addressId, $token],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start payment stock-race worker.');
    }
    fclose($pipes[0]);
    return [$process, $pipes];
}

function finishPaymentRaceWorker(array $worker): int
{
    [$process, $pipes] = $worker;
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    if ($code === 1) {
        throw new RuntimeException(trim($stderr . $stdout));
    }
    return $code;
}

if (($argv[1] ?? '') === '--race-worker') {
    try {
        $database = inventoryTestDatabase();
        $result = (new OrderService($database, $testPaymentConfig))->place((int) $argv[2], [
            'idempotency_token' => (string) $argv[4],
            'address_id' => (int) $argv[3],
            'payment_method' => 'vnpay',
        ]);
        exit(empty($result['existing']) ? 0 : 1);
    } catch (InventoryException | PricingException $exception) {
        exit(3);
    } catch (Throwable $exception) {
        fwrite(STDERR, $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}

$database = inventoryTestDatabase();
$regionId = (int) $database->query(
    'SELECT region_id FROM shipping_fees WHERE is_active = 1 ORDER BY region_id LIMIT 1'
)->fetchColumn();
if ($regionId < 1) {
    throw new RuntimeException('Payment tests need an active shipping region.');
}
$database->prepare(
    "INSERT INTO settings (setting_key, setting_value, description) VALUES ('payment_vnpay_enabled', '1', 'test')
     ON DUPLICATE KEY UPDATE setting_value = '1'"
)->execute();
$database->prepare(
    "INSERT INTO settings (setting_key, setting_value, description) VALUES ('online_payment_hold_minutes', '30', 'test')
     ON DUPLICATE KEY UPDATE setting_value = '30'"
)->execute();

$product = paymentTestProduct($database, 8);
$customer = paymentTestCustomer($database, $regionId, $product['product_id'], 1);
$quote = (new PricingService($database))->quote([[
    'product_id' => $product['product_id'],
    'category_id' => $product['category_id'],
    'quantity' => 1,
    'unit_price' => $product['price'],
]], $regionId, null, $customer['user_id']);
$order = (new OrderService($database, $testPaymentConfig))->place($customer['user_id'], [
    'idempotency_token' => $customer['token'],
    'address_id' => $customer['address_id'],
    'payment_method' => 'vnpay',
]);
$orderQuery = $database->prepare('SELECT * FROM orders WHERE id = :id');
$orderQuery->execute(['id' => $order['id']]);
$orderRow = $orderQuery->fetch();
$paymentQuery = $database->prepare("SELECT * FROM payments WHERE order_id = :id AND method = 'vnpay' ORDER BY id DESC LIMIT 1");
$paymentQuery->execute(['id' => $order['id']]);
$payment = $paymentQuery->fetch();
$quotedTotal = PricingService::moneyToVnd((string) $quote['total']);
if (PricingService::moneyToVnd((string) $orderRow['total_amount']) !== $quotedTotal) {
    throw new RuntimeException('Online order total does not match the PricingService quote.');
}

$service = new PaymentService($database, $testPaymentConfig);
$originalTimezone = date_default_timezone_get();
date_default_timezone_set('UTC');
try {
    $firstGatewayUrl = $service->createVnpayAttempt((int) $order['id'], (int) $customer['user_id']);
    $firstAttemptParts = parse_url($firstGatewayUrl);
    if ($firstAttemptParts === false) {
        throw new RuntimeException('VNPay generated an invalid first-attempt URL.');
    }
    parse_str((string) ($firstAttemptParts['query'] ?? ''), $firstAttemptParameters);
    $previousAttemptReference = (string) ($firstAttemptParameters['vnp_TxnRef'] ?? '');
    $gatewayUrl = $service->createVnpayAttempt((int) $order['id'], (int) $customer['user_id']);
} finally {
    date_default_timezone_set($originalTimezone);
}
$gatewayParts = parse_url($gatewayUrl);
if ($gatewayParts === false) {
    throw new RuntimeException('VNPay generated an invalid URL.');
}
parse_str((string) ($gatewayParts['query'] ?? ''), $gatewayParameters);
$gatewaySignature = (string) ($gatewayParameters['vnp_SecureHash'] ?? '');
$createDate = DateTimeImmutable::createFromFormat(
    '!YmdHis',
    (string) ($gatewayParameters['vnp_CreateDate'] ?? ''),
    new DateTimeZone('Asia/Ho_Chi_Minh')
);
$expireDate = DateTimeImmutable::createFromFormat(
    '!YmdHis',
    (string) ($gatewayParameters['vnp_ExpireDate'] ?? ''),
    new DateTimeZone('Asia/Ho_Chi_Minh')
);
$nowInVietnam = new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
$paymentQuery->execute(['id' => $order['id']]);
$paymentAfterRetry = $paymentQuery->fetch();
if (
    ($gatewayParts['scheme'] ?? '') !== 'https'
    || ($gatewayParts['host'] ?? '') !== 'sandbox.vnpayment.vn'
    || ($gatewayParts['path'] ?? '') !== '/paymentv2/vpcpay.html'
    || redirect_target($gatewayUrl, $testPaymentConfig) !== $gatewayUrl
    || !VnpaySignature::verify($gatewayParameters, $testPaymentConfig['vnpay']['hash_secret'], $gatewaySignature)
    || (int) ($gatewayParameters['vnp_Amount'] ?? 0) !== $quotedTotal * 100
    || $createDate === false
    || $expireDate === false
    || abs($createDate->getTimestamp() - $nowInVietnam->getTimestamp()) > 60
    || $expireDate <= $createDate
    || filter_var($gatewayParameters['vnp_IpAddr'] ?? '', FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false
    || preg_match('/\A[A-Za-z0-9 ]+\z/', (string) ($gatewayParameters['vnp_OrderInfo'] ?? '')) !== 1
    || VnpaySignature::amountVnd($gatewayParameters['vnp_Amount'] ?? null) !== $quotedTotal
    || $firstAttemptParts === false
    || $previousAttemptReference === ''
    || (string) ($gatewayParameters['vnp_TxnRef'] ?? '') === $previousAttemptReference
    || (string) ($paymentAfterRetry['transaction_ref'] ?? '') !== (string) ($gatewayParameters['vnp_TxnRef'] ?? '')
) {
    throw new RuntimeException('VNPay redirect URL or signature is invalid.');
}
$payment = $paymentAfterRetry;
$urlCallback = [
    'vnp_Amount' => (string) $firstAttemptParameters['vnp_Amount'],
    'vnp_BankCode' => 'NCB',
    'vnp_BankTranNo' => 'VNP' . random_int(10000000, 99999999),
    'vnp_OrderInfo' => (string) $firstAttemptParameters['vnp_OrderInfo'],
    'vnp_PayDate' => date('YmdHis'),
    'vnp_ResponseCode' => '00',
    'vnp_TmnCode' => (string) $firstAttemptParameters['vnp_TmnCode'],
    'vnp_TransactionNo' => (string) random_int(10000000, 99999999),
    'vnp_TransactionStatus' => '00',
    'vnp_TxnRef' => (string) $firstAttemptParameters['vnp_TxnRef'],
];
$invalidSignature = $urlCallback;
$invalidSignature['vnp_SecureHash'] = str_repeat('0', 128);
if ($service->handleVnpay($invalidSignature)['code'] !== '97') {
    throw new RuntimeException('VNPay callback with an invalid signature was accepted.');
}
$signedWrongAmount = $urlCallback;
$signedWrongAmount['vnp_Amount'] = (string) ((int) $signedWrongAmount['vnp_Amount'] + 100);
$signedWrongAmount = signPaymentTestCallback($signedWrongAmount, 'test-only-secret');
if ($service->handleVnpay($signedWrongAmount)['code'] !== '04') {
    throw new RuntimeException('A correctly signed but database-mismatched amount was accepted.');
}
$wrongReference = $urlCallback;
$wrongReference['vnp_TxnRef'] = 'VP-UNKNOWN-TEST';
$wrongReference = signPaymentTestCallback($wrongReference, 'test-only-secret');
if ($service->handleVnpay($wrongReference)['code'] !== '01') {
    throw new RuntimeException('VNPay callback with an unknown transaction reference was accepted.');
}
$failedAttemptCallback = $urlCallback;
$failedAttemptCallback['vnp_ResponseCode'] = '24';
$failedAttemptCallback['vnp_TransactionStatus'] = '24';
$failedAttemptCallback = signPaymentTestCallback($failedAttemptCallback, 'test-only-secret');
$failedAttemptResult = $service->handleVnpay($failedAttemptCallback);
if (($failedAttemptResult['success'] ?? true) !== false) {
    throw new RuntimeException('VNPay declined attempt was not recorded as failed.');
}
$validCallback = signPaymentTestCallback($urlCallback, 'test-only-secret');
$movementCount = $database->prepare(
    "SELECT COUNT(*) FROM inventory_movements WHERE reference_type = 'order' AND reference_id = :id AND movement_type = 'out'"
);
$movementCount->execute(['id' => $order['id']]);
$movementCountBefore = (int) $movementCount->fetchColumn();
$firstCallback = $service->handleVnpay($validCallback);
$movementCount->execute(['id' => $order['id']]);
$movementCountAfterFirst = (int) $movementCount->fetchColumn();
$secondCallback = $service->handleVnpay($validCallback);
$laterAttemptCallback = array_replace($urlCallback, [
    'vnp_TxnRef' => (string) $gatewayParameters['vnp_TxnRef'],
    'vnp_TransactionNo' => (string) random_int(10000000, 99999999),
    'vnp_BankTranNo' => 'VNP' . random_int(10000000, 99999999),
    'vnp_PayDate' => date('YmdHis'),
]);
$laterAttemptCallback = signPaymentTestCallback($laterAttemptCallback, 'test-only-secret');
$laterAttemptResult = $service->handleVnpay($laterAttemptCallback);
$movementCount->execute(['id' => $order['id']]);
$movementCountAfterSecond = (int) $movementCount->fetchColumn();
$paidPaymentQuery = $database->prepare(
    'SELECT COUNT(*) FROM payments WHERE order_id = :id AND method = \'vnpay\' AND status = \'paid\''
);
$paidPaymentQuery->execute(['id' => $order['id']]);
$paidPaymentCount = (int) $paidPaymentQuery->fetchColumn();
$attemptCountQuery = $database->prepare(
    "SELECT COUNT(*) FROM payments WHERE order_id = :id AND method = 'vnpay' AND transaction_ref IS NOT NULL"
);
$attemptCountQuery->execute(['id' => $order['id']]);
$attemptCount = (int) $attemptCountQuery->fetchColumn();
$orderQuery->execute(['id' => $order['id']]);
$paidOrder = $orderQuery->fetch();
if ($paidPaymentCount !== 1 || $attemptCount !== 2) {
    throw new RuntimeException('A VNPay order did not record exactly one successful payment.');
}
if (($firstCallback['success'] ?? false) !== true || ($secondCallback['success'] ?? false) !== true
    || ($laterAttemptResult['success'] ?? false) !== true
    || $movementCountAfterFirst <= $movementCountBefore || $movementCountAfterSecond !== $movementCountAfterFirst
    || ($paidOrder['payment_status'] ?? null) !== 'paid') {
    throw new RuntimeException('VNPay callback lifecycle did not complete exactly once.');
}

$manualCustomer = paymentTestCustomer($database, $regionId, $product['product_id'], 1);
$manualOrder = (new OrderService($database, $testPaymentConfig))->place($manualCustomer['user_id'], [
    'idempotency_token' => $manualCustomer['token'],
    'address_id' => $manualCustomer['address_id'],
    'payment_method' => 'vnpay',
]);
$service->createVnpayAttempt((int) $manualOrder['id'], (int) $manualCustomer['user_id']);
$manualPaymentQuery = $database->prepare(
    "SELECT id FROM payments WHERE order_id = :id AND method = 'vnpay' ORDER BY id DESC LIMIT 1"
);
$manualPaymentQuery->execute(['id' => $manualOrder['id']]);
$manualPaymentId = (int) $manualPaymentQuery->fetchColumn();
$manualMovementCount = $database->prepare(
    "SELECT COUNT(*) FROM inventory_movements
     WHERE reference_type = 'order' AND reference_id = :id AND movement_type = 'out'"
);
$manualMovementCount->execute(['id' => $manualOrder['id']]);
$manualMovementCountBefore = (int) $manualMovementCount->fetchColumn();
$service->reconcileVnpayManually(
    $manualPaymentId,
    (int) $manualCustomer['user_id'],
    '15720044',
    'Đã xác nhận giao dịch thành công trên VNPay sandbox.'
);
$manualMovementCount->execute(['id' => $manualOrder['id']]);
$manualMovementCountAfter = (int) $manualMovementCount->fetchColumn();
$manualOrderStatus = $database->prepare('SELECT payment_status FROM orders WHERE id = :id');
$manualOrderStatus->execute(['id' => $manualOrder['id']]);
$manualPaymentState = $database->prepare('SELECT status, raw_response FROM payments WHERE id = :id');
$manualPaymentState->execute(['id' => $manualPaymentId]);
$manualPaymentRow = $manualPaymentState->fetch();
$manualAudit = $database->prepare(
    "SELECT COUNT(*) FROM audit_logs
     WHERE entity_type = 'payment' AND entity_id = :id AND action = 'vnpay_manual_reconciled'"
);
$manualAudit->execute(['id' => $manualPaymentId]);
$manualAuditCount = (int) $manualAudit->fetchColumn();
$manualRepeatRejected = false;
try {
    $service->reconcileVnpayManually(
        $manualPaymentId,
        (int) $manualCustomer['user_id'],
        '15720044',
        'Lặp lại kiểm thử'
    );
} catch (InvalidArgumentException) {
    $manualRepeatRejected = true;
}
$manualMovementCount->execute(['id' => $manualOrder['id']]);
if (!$manualRepeatRejected
    || $manualMovementCountAfter !== $manualMovementCountBefore + 1
    || (int) $manualMovementCount->fetchColumn() !== $manualMovementCountAfter
    || $manualOrderStatus->fetchColumn() !== 'paid'
    || $manualPaymentRow['status'] !== 'paid'
    || $manualAuditCount !== 1
    || !str_contains((string) $manualPaymentRow['raw_response'], '15720044')
    || !str_contains((string) $manualPaymentRow['raw_response'], 'manual_vnpay_reconciliation')) {
    throw new RuntimeException('Manual VNPay reconciliation did not audit and deduct stock exactly once.');
}

$voucherCode = 'PAY-' . strtoupper(bin2hex(random_bytes(4)));
$database->prepare(
    "INSERT INTO vouchers
        (code, description, discount_type, discount_value, minimum_order, usage_limit, per_user_limit, starts_at, ends_at, status)
     VALUES (:code, 'Payment expiry test', 'fixed', 1000, 0, 1, 1, NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY), 'active')"
)->execute(['code' => $voucherCode]);
$voucherId = (int) $database->lastInsertId();
$expireCustomer = paymentTestCustomer($database, $regionId, $product['product_id'], 1);
$expireOrder = (new OrderService($database, $testPaymentConfig))->place($expireCustomer['user_id'], [
    'idempotency_token' => $expireCustomer['token'],
    'address_id' => $expireCustomer['address_id'],
    'voucher_code' => $voucherCode,
    'payment_method' => 'vnpay',
]);
$database->prepare(
    'UPDATE stock_reservations SET expires_at = DATE_SUB(NOW(), INTERVAL 1 SECOND) WHERE order_id = :id'
)->execute(['id' => $expireOrder['id']]);
$service->expirePending();
$expiredOrderQuery = $database->prepare('SELECT status, payment_status FROM orders WHERE id = :id');
$expiredOrderQuery->execute(['id' => $expireOrder['id']]);
$expiredStatus = $expiredOrderQuery->fetch();
$reservationQuery = $database->prepare("SELECT COUNT(*) FROM stock_reservations WHERE order_id = :id AND status = 'expired'");
$reservationQuery->execute(['id' => $expireOrder['id']]);
$voucherUsage = $database->prepare('SELECT used_count FROM vouchers WHERE id = :id');
$voucherUsage->execute(['id' => $voucherId]);
$voucherUsageStatus = $database->prepare('SELECT status FROM voucher_usages WHERE order_id = :id');
$voucherUsageStatus->execute(['id' => $expireOrder['id']]);
if ($expiredStatus['status'] !== 'cancelled' || $expiredStatus['payment_status'] !== 'failed'
    || (int) $reservationQuery->fetchColumn() < 1 || (int) $voucherUsage->fetchColumn() !== 0
    || $voucherUsageStatus->fetchColumn() !== 'reversed') {
    throw new RuntimeException('Lazy expiration did not cancel the order and release inventory/voucher.');
}

$lastProduct = paymentTestProduct($database, 1);
$raceCustomers = [
    paymentTestCustomer($database, $regionId, $lastProduct['product_id'], 1),
    paymentTestCustomer($database, $regionId, $lastProduct['product_id'], 1),
];
$raceWorkers = [];
foreach ($raceCustomers as $raceCustomer) {
    $raceWorkers[] = startPaymentRaceWorker($raceCustomer['user_id'], $raceCustomer['address_id'], $raceCustomer['token']);
}
$raceResults = [];
foreach ($raceWorkers as $raceWorker) {
    $raceResults[] = finishPaymentRaceWorker($raceWorker);
}
sort($raceResults);
$batchQuery = $database->prepare('SELECT quantity_remaining FROM product_batches WHERE id = :id');
$batchQuery->execute(['id' => $lastProduct['batch_id']]);
$heldQuery = $database->prepare("SELECT COUNT(*) FROM stock_reservations WHERE batch_id = :id AND status = 'reserved' AND expires_at > NOW()");
$heldQuery->execute(['id' => $lastProduct['batch_id']]);
if ($raceResults !== [0, 3] || (int) $batchQuery->fetchColumn() !== 1 || (int) $heldQuery->fetchColumn() !== 1) {
    throw new RuntimeException('VNPay reservations did not protect the final item for exactly one customer.');
}

$payload = VietQr::payload('970415', '1234567890', 'NGUYEN VAN A', 'DH-TEST-1', 12345);
$crcPayload = substr($payload, 0, -4);
$crc = 0xFFFF;
foreach (unpack('C*', $crcPayload) as $byte) {
    $crc ^= $byte << 8;
    for ($bit = 0; $bit < 8; $bit++) {
        $crc = ($crc & 0x8000) !== 0 ? (($crc << 1) ^ 0x1021) & 0xFFFF : ($crc << 1) & 0xFFFF;
    }
}
if (strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT)) !== substr($payload, -4)
    || !str_contains(VietQr::svg('970415', '1234567890', 'NGUYEN VAN A', 'DH-TEST-1', 12345), '<svg')) {
    throw new RuntimeException('VietQR payload CRC or SVG rendering failed.');
}

printf(
    "PASS: VNPay tamper rejection, duplicate IPN idempotency, lazy expiry/voucher reversal, final-item reservation race, PricingService total, and VietQR CRC/SVG. Order %s total=%d.\n",
    $order['order_no'],
    $quotedTotal
);
