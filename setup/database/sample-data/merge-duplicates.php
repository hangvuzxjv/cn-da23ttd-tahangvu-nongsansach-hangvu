<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Chỉ chạy bằng PHP CLI.\n");
}

$root = dirname(__DIR__, 3);
$config = require $root . '/scr/backend/config/config.php';
$db = $config['database'];
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']),
    $db['username'],
    $db['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$pdo->beginTransaction();
$changes = [];
$move = static function (PDO $pdo, string $table, string $column, int $from, int $to) use (&$changes): void {
    $statement = $pdo->prepare("UPDATE {$table} SET {$column} = :to WHERE {$column} = :from");
    $statement->execute(['to' => $to, 'from' => $from]);
    if ($statement->rowCount() > 0) {
        $changes[] = "{$table}.{$column}: {$from} -> {$to} ({$statement->rowCount()} bản ghi)";
    }
};
$deleteIfUnused = static function (PDO $pdo, string $table, int $id) use (&$changes): void {
    $pdo->prepare("DELETE FROM {$table} WHERE id = :id")->execute(['id' => $id]);
    $changes[] = "{$table}: xóa bản ghi {$id} sau khi chuyển tham chiếu";
};

try {
    // Consolidate exact region names first, then their parent references.
    $regionMap = [25 => 10, 24 => 11, 29 => 12, 15 => 1, 16 => 2, 17 => 3];
    foreach ([18 => 1, 19 => 1, 20 => 2, 21 => 2, 22 => 2, 23 => 2, 24 => 3, 25 => 3, 26 => 3, 27 => 3, 28 => 3, 29 => 3, 30 => 3, 31 => 3] as $id => $parent) {
        $pdo->prepare('UPDATE regions SET parent_id = :parent WHERE id = :id')->execute(['parent' => $parent, 'id' => $id]);
    }
    foreach ($regionMap as $from => $to) {
        $move($pdo, 'addresses', 'region_id', $from, $to);
        $move($pdo, 'orders', 'region_id', $from, $to);
        $move($pdo, 'products', 'region_id', $from, $to);
        $move($pdo, 'farms', 'region_id', $from, $to);
        $move($pdo, 'shipping_fees', 'region_id', $from, $to);
    }
    foreach ($regionMap as $from => $to) {
        $deleteIfUnused($pdo, 'regions', $from);
    }

    // The six storefront groups are the canonical categories.
    $categoryMap = [1 => 10, 3 => 13, 4 => 13, 5 => 14, 6 => 14, 12 => 2];
    foreach ($categoryMap as $from => $to) {
        $move($pdo, 'products', 'category_id', $from, $to);
        $move($pdo, 'voucher_categories', 'category_id', $from, $to);
        $deleteIfUnused($pdo, 'categories', $from);
    }
    $pdo->prepare("UPDATE categories SET name = 'Rau củ quả', slug = 'rau-cu-qua', description = 'Rau củ quả tươi theo mùa, phù hợp cho bữa ăn hằng ngày.' WHERE id = 10")->execute();
    $pdo->prepare("UPDATE categories SET name = 'Gạo ngũ cốc và đậu hạt', slug = 'gao-ngu-coc-va-dau-hat', description = 'Gạo, ngũ cốc và đậu hạt được tuyển chọn từ vùng nguyên liệu.' WHERE id = 13")->execute();
    $pdo->prepare("UPDATE categories SET name = 'Nấm và hạt cây công nghiệp', slug = 'nam-hat-cong-nghiep', description = 'Nấm tươi và các loại hạt, nông sản vùng cao.' WHERE id = 14")->execute();
    $pdo->prepare("UPDATE categories SET description = 'Rau lá tươi, thu hoạch theo mùa và sơ chế trong ngày.' WHERE id = 9")->execute();
    $pdo->prepare("UPDATE categories SET description = 'Trái cây đặc sản theo mùa từ các vùng trồng trong nước.' WHERE id = 2")->execute();
    $pdo->prepare("UPDATE categories SET description = 'Củ và thân củ tươi, tiện dùng cho bữa ăn gia đình.' WHERE id = 11")->execute();

    // Merge products with the same normalized name. The older seeded row keeps its history.
    $productMap = [36 => 5, 41 => 8, 33 => 11];
    foreach ($productMap as $from => $to) {
        $move($pdo, 'product_batches', 'product_id', $from, $to);
        $move($pdo, 'product_images', 'product_id', $from, $to);
        $move($pdo, 'order_items', 'product_id', $from, $to);
        $move($pdo, 'reviews', 'product_id', $from, $to);
        $move($pdo, 'wishlists', 'product_id', $from, $to);
        $move($pdo, 'voucher_products', 'product_id', $from, $to);
        $move($pdo, 'flash_sale_items', 'product_id', $from, $to);
        $move($pdo, 'cart_items', 'product_id', $from, $to);
        $pdo->prepare('UPDATE products SET total_stock = (SELECT COALESCE(SUM(quantity_remaining), 0) FROM product_batches WHERE product_id = :id) WHERE id = :id')
            ->execute(['id' => $to]);
        $deleteIfUnused($pdo, 'products', $from);
    }

    // Replace customer-visible placeholder wording without touching operational notes.
    $replacements = [
        ['regions', 'description', 'Miền minh họa trong bộ dữ liệu mẫu.', 'Vùng sản xuất với nông sản đặc trưng theo mùa.'],
        ['regions', 'description', 'Vùng sản xuất minh họa cho dữ liệu mẫu.', 'Vùng trồng cung cấp nông sản theo mùa.'],
        ['categories', 'description', 'Nhóm nông sản minh họa.', 'Nhóm nông sản được tuyển chọn theo mùa.'],
        ['farms', 'description', 'Nông trại minh họa phục vụ kiểm thử trang sản phẩm.', 'Nông trại đối tác cung cấp nông sản theo mùa.'],
        ['products', 'description', 'nông trại minh họa', 'nông trại đối tác'],
        ['products', 'description', 'Thông tin dùng để kiểm thử đầy đủ các màn hình cửa hàng.', 'Thông tin sản phẩm được cập nhật theo từng lô hàng.'],
        ['products', 'tags', 'dữ liệu mẫu', 'nông sản theo mùa'],
        ['products', 'certifications', 'dữ liệu minh họa', 'thông tin chứng nhận theo hồ sơ nông trại'],
    ];
    foreach ($replacements as [$table, $column, $from, $to]) {
        $pdo->prepare("UPDATE {$table} SET {$column} = REPLACE({$column}, :from, :to) WHERE {$column} LIKE :needle")
            ->execute(['from' => $from, 'to' => $to, 'needle' => '%' . $from . '%']);
    }
    $pdo->prepare("UPDATE regions SET description = CASE id
        WHEN 1 THEN 'Vùng chè, cam và trái cây miền núi phía Bắc.'
        WHEN 2 THEN 'Vùng rau, cà phê và nông sản cao nguyên miền Trung.'
        WHEN 3 THEN 'Vùng trái cây, gạo và nông sản nhiệt đới phía Nam.'
        ELSE description END WHERE id IN (1, 2, 3)")->execute();

    foreach ($changes as $change) {
        echo $change . PHP_EOL;
    }
    $pdo->commit();
    echo "Đã gộp dữ liệu trong một transaction.\n";
} catch (Throwable $exception) {
    $pdo->rollBack();
    fwrite(STDERR, "Đã hoàn tác vì lỗi: {$exception->getMessage()}\n");
    exit(1);
}
