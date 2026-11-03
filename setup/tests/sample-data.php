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

$count = static function (PDO $pdo, string $sql): int {
    return (int) $pdo->query($sql)->fetchColumn();
};
$normalize = static function (string $value): string {
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower(trim($value), 'UTF-8'));
    return preg_replace('/[^a-z0-9]+/', '', strtolower($ascii ?: $value)) ?? '';
};
$duplicateNames = static function (PDO $pdo, string $table) use ($normalize): int {
    $groups = [];
    foreach ($pdo->query("SELECT name FROM {$table}")->fetchAll(PDO::FETCH_COLUMN) as $name) {
        $key = $normalize((string) $name);
        $groups[$key] = ($groups[$key] ?? 0) + 1;
    }
    return count(array_filter($groups, static fn (int $count): bool => $count > 1));
};
$products = $count($pdo, "SELECT COUNT(DISTINCT b.product_id) FROM product_batches b JOIN products p ON p.id = b.product_id WHERE b.batch_code LIKE 'DEMO-BATCH-%' AND p.status = 'active'");
$batches = $count($pdo, "SELECT COUNT(*) FROM product_batches WHERE batch_code LIKE 'DEMO-BATCH-%'");
$events = $count($pdo, "SELECT COUNT(*) FROM trace_events e JOIN product_batches b ON b.id = e.batch_id WHERE b.batch_code LIKE 'DEMO-BATCH-%'");
$mismatches = $count($pdo, "SELECT COUNT(*) FROM products p JOIN (SELECT product_id, SUM(quantity_remaining) AS stock FROM product_batches GROUP BY product_id) b ON b.product_id = p.id WHERE p.sku LIKE 'NS-DEMO-%' AND p.total_stock <> b.stock");
$missingMetadata = $count($pdo, "SELECT COUNT(*) FROM products WHERE sku LIKE 'NS-DEMO-%' AND (season IS NULL OR nutrition IS NULL OR preparation IS NULL OR seo_title IS NULL OR seo_description IS NULL)");
$regions = $count($pdo, "SELECT COUNT(DISTINCT r.parent_id) FROM products p JOIN regions r ON r.id = p.region_id WHERE p.sku LIKE 'NS-DEMO-%'");
$duplicateRegions = $duplicateNames($pdo, 'regions');
$duplicateCategories = $duplicateNames($pdo, 'categories');
$duplicateFarms = $duplicateNames($pdo, 'farms');
$duplicateProducts = $duplicateNames($pdo, 'products');
$placeholders = $count($pdo, "SELECT COUNT(*) FROM products WHERE description LIKE '%minh họa%' OR description LIKE '%dữ liệu mẫu%' OR tags LIKE '%dữ liệu mẫu%' OR certifications LIKE '%dữ liệu minh họa%'")
    + $count($pdo, "SELECT COUNT(*) FROM regions WHERE description LIKE '%minh họa%' OR description LIKE '%dữ liệu mẫu%'")
    + $count($pdo, "SELECT COUNT(*) FROM categories WHERE description LIKE '%minh họa%' OR description LIKE '%dữ liệu mẫu%'")
    + $count($pdo, "SELECT COUNT(*) FROM farms WHERE description LIKE '%minh họa%' OR description LIKE '%dữ liệu mẫu%' OR contact_name LIKE '%minh họa%'");
$repeatedFlashSale = $count($pdo, "SELECT COUNT(*) FROM flash_sales WHERE name REGEXP 'Flash Sale[[:space:][:punct:]]+Flash Sale'");

if ($products !== 36 || $batches !== 36 || $events < 108 || $mismatches !== 0 || $missingMetadata !== 0 || $regions < 3
    || $duplicateRegions !== 0 || $duplicateCategories !== 0 || $duplicateFarms !== 0 || $duplicateProducts !== 0
    || $placeholders !== 0 || $repeatedFlashSale !== 0) {
    fwrite(STDERR, "Sample data validation failed: products={$products}, batches={$batches}, events={$events}, mismatches={$mismatches}, metadata={$missingMetadata}, regions={$regions}, duplicates={$duplicateRegions}/{$duplicateCategories}/{$duplicateFarms}/{$duplicateProducts}, placeholders={$placeholders}, repeated_flash_sale={$repeatedFlashSale}\n");
    exit(1);
}
echo "PASS sample data: 36 products, 36 batches, {$events} trace events, stock and metadata consistent across {$regions} regions; no normalized duplicates or placeholders.\n";
