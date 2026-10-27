<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$database = inventoryTestDatabase();
$mismatches = $database->query(
    'SELECT b.id, b.batch_code, b.quantity_remaining,
            COALESCE(SUM(m.quantity), 0) AS movement_total
     FROM product_batches b
     LEFT JOIN inventory_movements m ON m.batch_id = b.id
     GROUP BY b.id, b.batch_code, b.quantity_remaining
     HAVING b.quantity_remaining <> COALESCE(SUM(m.quantity), 0)
     ORDER BY b.id LIMIT 50'
)->fetchAll();
$productMismatches = $database->query(
    'SELECT p.id, p.name, p.total_stock, COALESCE(SUM(b.quantity_remaining), 0) AS batch_total
     FROM products p
     LEFT JOIN product_batches b ON b.product_id = p.id
     GROUP BY p.id, p.name, p.total_stock
     HAVING p.total_stock <> COALESCE(SUM(b.quantity_remaining), 0)
     ORDER BY p.id LIMIT 50'
)->fetchAll();
$negative = (int) $database->query(
    'SELECT COUNT(*) FROM product_batches WHERE quantity_remaining < 0'
)->fetchColumn();

printf("Lot/ledger mismatches: %d; product total mismatches: %d; negative lots: %d\n", count($mismatches), count($productMismatches), $negative);
foreach ($mismatches as $row) {
    printf("Lot %s: stock=%d ledger=%d\n", $row['batch_code'], $row['quantity_remaining'], $row['movement_total']);
}
foreach ($productMismatches as $row) {
    printf("Product %s: total_stock=%d batches=%d\n", $row['name'], $row['total_stock'], $row['batch_total']);
}
if ($mismatches || $productMismatches || $negative > 0) {
    exit(1);
}
echo "PASS: lot balances, movement sums, and product totals reconcile.\n";
