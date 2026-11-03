<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$config = require $root . '/scr/backend/config/config.php';
$db = $config['database'];
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']),
    $db['username'],
    $db['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL sample commerce: {$message}\n");
    exit(1);
};
$orderCount = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE order_no LIKE 'DEMO-ORDER-%'")->fetchColumn();
if ($orderCount !== 36) {
    $fail("expected 36 demo orders, got {$orderCount}");
}
$amounts = $pdo->query(
    "SELECT o.id, o.subtotal, o.shipping_fee, o.discount_amount, o.total_amount,
            COALESCE(SUM(oi.line_total), 0) AS line_sum
     FROM orders o LEFT JOIN order_items oi ON oi.order_id = o.id
     WHERE o.order_no LIKE 'DEMO-ORDER-%'
     GROUP BY o.id"
)->fetchAll();
foreach ($amounts as $row) {
    if ((int) $row['line_sum'] !== (int) $row['subtotal']
        || (int) $row['total_amount'] !== (int) $row['subtotal'] + (int) $row['shipping_fee'] - (int) $row['discount_amount']) {
        $fail('order total mismatch at order #' . $row['id']);
    }
}
$stockMismatch = (int) $pdo->query(
    "SELECT COUNT(*) FROM products p
     JOIN (SELECT product_id, SUM(quantity_remaining) AS stock FROM product_batches GROUP BY product_id) b ON b.product_id = p.id
     WHERE p.sku LIKE 'NS-DEMO-%' AND p.total_stock <> b.stock"
)->fetchColumn();
if ($stockMismatch !== 0) {
    $fail("stock mismatch count {$stockMismatch}");
}
$flashMismatch = (int) $pdo->query(
    "SELECT COUNT(*) FROM flash_sale_items fsi
     JOIN flash_sales fs ON fs.id = fsi.flash_sale_id
     LEFT JOIN (
       SELECT flash_sale_item_id, SUM(quantity) AS sold
       FROM flash_sale_purchases WHERE status = 'confirmed' GROUP BY flash_sale_item_id
     ) p ON p.flash_sale_item_id = fsi.id
     WHERE fs.name LIKE 'DEMO-%' AND fsi.sold_quantity <> COALESCE(p.sold, 0)"
)->fetchColumn();
if ($flashMismatch !== 0) {
    $fail("flash sale sold mismatch count {$flashMismatch}");
}
$duplicateOrders = (int) $pdo->query("SELECT COUNT(*) - COUNT(DISTINCT order_no) FROM orders WHERE order_no LIKE 'DEMO-ORDER-%'")->fetchColumn();
if ($duplicateOrders !== 0) {
    $fail("duplicate demo orders {$duplicateOrders}");
}
$reviewCount = (int) $pdo->query("SELECT COUNT(*) FROM reviews r JOIN users u ON u.id = r.user_id WHERE u.email LIKE 'demo.customer%@example.test'")->fetchColumn();
if ($reviewCount !== 72) {
    $fail("expected 72 reviews, got {$reviewCount}");
}
$roles = $pdo->query("SELECT role, COUNT(*) AS count FROM users WHERE email IN ('admin@example.com', 'sales@example.com', 'warehouse@example.com') GROUP BY role")->fetchAll();
if (count($roles) < 3) {
    $fail('missing one of the required staff roles');
}
echo "PASS sample commerce: 36 orders, totals, stock, Flash Sale sold counts, 72 reviews and roles are consistent.\n";
