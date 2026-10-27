<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$productId = filter_var($argv[1] ?? null, FILTER_VALIDATE_INT);
$quantity = filter_var($argv[2] ?? 1, FILTER_VALIDATE_INT);
if (!$productId || $quantity === false || $quantity < 1) {
    fwrite(STDERR, "Usage: php setup/tests/fefo.php <product_id> [quantity]\n");
    exit(2);
}

$database = inventoryTestDatabase();
$allocations = (new InventoryService($database))->allocateFefo($productId, $quantity);
$batchIds = array_column($allocations, 'batch_id');
$placeholders = implode(',', array_fill(0, count($batchIds), '?'));
$query = $database->prepare(
    'SELECT id, batch_code, expires_at FROM product_batches
     WHERE id IN (' . $placeholders . ')
     ORDER BY expires_at IS NULL, expires_at, harvested_at, id'
);
$query->execute($batchIds);
$sortedIds = array_map('intval', array_column($query->fetchAll(), 'id'));
if ($sortedIds !== array_map('intval', $batchIds)) {
    fwrite(STDERR, "FAIL: allocations are not ordered by earliest expiry.\n");
    exit(1);
}

foreach ($allocations as $allocation) {
    printf("%s batch_id=%d quantity=%d\n", $allocation['batch_code'], $allocation['batch_id'], $allocation['quantity']);
}
echo "PASS: allocation order follows FEFO.\n";
