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

$remove = in_array('--remove', $argv, true);
$commerceLoader = __DIR__ . '/load-commerce.php';
if (!is_file($commerceLoader)) {
    throw new RuntimeException('Thiếu bộ nạp dữ liệu thương mại: ' . $commerceLoader);
}
require_once $commerceLoader;
$hasReviewUpdatedAt = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'reviews' AND column_name = 'updated_at'"
)->fetchColumn();
if ((int) $hasReviewUpdatedAt === 0) {
    $pdo->exec('ALTER TABLE reviews ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
}
$pdo->beginTransaction();

try {
    $normalize = static function (string $value): string {
        $value = trim(mb_strtolower($value, 'UTF-8'));
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        return preg_replace('/[^a-z0-9]+/', '', strtolower($ascii ?: $value)) ?? '';
    };
    $findByName = static function (PDO $pdo, string $table, string $name) use ($normalize): ?int {
        $rows = $pdo->query("SELECT id, name FROM {$table}")->fetchAll();
        $needle = $normalize($name);
        foreach ($rows as $row) {
            if ($normalize((string) $row['name']) === $needle) {
                return (int) $row['id'];
            }
        }
        return null;
    };
    $sampleSlugs = [
        'rau-muong', 'cai-ngot', 'cai-xanh', 'xa-lach', 'rau-ma', 'mong-toi',
        'ca-chua', 'dua-leo', 'kho-qua', 'bi-do', 'ca-rot-da-lat-demo', 'ot-hiem',
        'khoai-lang-tim', 'khoai-tay-demo', 'hanh-tim-vinh-chau', 'toi-ly-son', 'gung-tuoi', 'nghe-tuoi',
        'xoai-cat-hoa-loc-demo', 'buoi-da-xanh-demo', 'chom-chom', 'sau-rieng', 'thanh-long', 'cam-sanh',
        'gao-st25', 'gao-lut', 'gao-nep', 'dau-xanh', 'dau-phong', 'me-den',
        'nam-rom', 'nam-bao-ngu', 'tieu-phu-quoc', 'hat-dieu', 'ca-phe-nhan-buon-ma-thuot', 'nhan-tuoi',
    ];
    $sampleBatchPrefix = 'DEMO-BATCH-';

    if ($remove) {
        $placeholders = implode(',', array_fill(0, count($sampleSlugs), '?'));
        $ids = $pdo->prepare("SELECT id FROM products WHERE slug IN ($placeholders)");
        $ids->execute($sampleSlugs);
        $productIds = array_column($ids->fetchAll(), 'id');
        if ($productIds) {
            $marks = implode(',', array_fill(0, count($productIds), '?'));
            $pdo->prepare("DELETE FROM flash_sale_purchases WHERE flash_sale_item_id IN (SELECT id FROM flash_sale_items WHERE product_id IN ($marks))")->execute($productIds);
            $pdo->prepare("DELETE FROM flash_sale_items WHERE product_id IN ($marks)")->execute($productIds);
            $pdo->prepare("DELETE FROM cart_items WHERE product_id IN ($marks)")->execute($productIds);
            $batchIds = $pdo->prepare("SELECT id FROM product_batches WHERE product_id IN ($marks)");
            $batchIds->execute($productIds);
            $batchIds = array_column($batchIds->fetchAll(), 'id');
            if ($batchIds) {
                $marksB = implode(',', array_fill(0, count($batchIds), '?'));
                $pdo->prepare("DELETE FROM trace_events WHERE batch_id IN ($marksB)")->execute($batchIds);
                $pdo->prepare("DELETE FROM warehouse_receipt_items WHERE batch_id IN ($marksB)")->execute($batchIds);
                $pdo->prepare("DELETE FROM inventory_movements WHERE batch_id IN ($marksB)")->execute($batchIds);
                $pdo->prepare("DELETE FROM product_batches WHERE id IN ($marksB)")->execute($batchIds);
            }
            $pdo->prepare("DELETE FROM product_images WHERE product_id IN ($marks)")->execute($productIds);
            $pdo->prepare("DELETE FROM products WHERE id IN ($marks)")->execute($productIds);
        }
        $pdo->prepare("DELETE FROM warehouse_receipts WHERE receipt_no = 'DEMO-RECEIPT-001'")->execute();
        loadCommerceSampleData($pdo, true);
        $pdo->commit();
        echo "Đã gỡ dữ liệu mẫu sản phẩm.\n";
        exit;
    }

    $region = static function (PDO $pdo, string $slug, string $name, int $parentId) use ($normalize): int {
        $find = $pdo->prepare('SELECT id FROM regions WHERE slug = :slug LIMIT 1');
        $find->execute(['slug' => $slug]);
        $id = $find->fetchColumn();
        if ($id === false || $id === null) {
            foreach ($pdo->query('SELECT id, name FROM regions')->fetchAll() as $row) {
                if ($normalize((string) $row['name']) === $normalize($name)) {
                    $id = $row['id'];
                    break;
                }
            }
        }
        if ($id !== false && $id !== null) {
            $pdo->prepare('UPDATE regions SET parent_id = :parent_id, slug = :slug, description = :description, is_active = 1 WHERE id = :id')
                ->execute([
                    'parent_id' => $parentId, 'slug' => $slug, 'description' => 'Vùng trồng cung cấp nông sản theo mùa.',
                    'id' => $id,
                ]);
            return (int) $id;
        }
        $insert = $pdo->prepare(
            'INSERT INTO regions (parent_id, name, level, slug, description, is_active)
             VALUES (:parent_id, :name, 2, :slug, :description, 1)'
        );
        $insert->execute([
            'parent_id' => $parentId, 'name' => $name, 'slug' => $slug,
            'description' => 'Vùng trồng cung cấp nông sản theo mùa.',
        ]);
        return (int) $pdo->lastInsertId();
    };
    $parentRegions = [];
    foreach ([
        ['mien-bac', 'Miền Bắc'],
        ['mien-trung-tay-nguyen', 'Miền Trung - Tây Nguyên'],
        ['mien-nam', 'Miền Nam'],
    ] as [$slug, $name]) {
        $find = $pdo->prepare('SELECT id FROM regions WHERE slug = :slug LIMIT 1');
        $find->execute(['slug' => $slug]);
        $id = $find->fetchColumn();
        if ($id === false || $id === null) {
            $pdo->prepare('INSERT INTO regions (name, level, slug, description, is_active) VALUES (:name, 1, :slug, :description, 1)')
                ->execute(['name' => $name, 'slug' => $slug, 'description' => 'Vùng sản xuất với nông sản đặc trưng theo mùa.']);
            $id = $pdo->lastInsertId();
        }
        $parentRegions[$slug] = (int) $id;
    }
    $regions = $parentRegions;
    foreach ([
        ['ha-noi', 'Hà Nội', 'mien-bac'], ['moc-chau', 'Mộc Châu', 'mien-bac'],
        ['ly-son', 'Lý Sơn', 'mien-trung-tay-nguyen'], ['da-lat', 'Đà Lạt', 'mien-trung-tay-nguyen'],
        ['buon-ma-thuot', 'Buôn Ma Thuột', 'mien-trung-tay-nguyen'], ['gia-lai', 'Gia Lai', 'mien-trung-tay-nguyen'],
        ['tien-giang', 'Tiền Giang', 'mien-nam'], ['ben-tre', 'Bến Tre', 'mien-nam'],
        ['vinh-long', 'Vĩnh Long', 'mien-nam'], ['soc-trang', 'Sóc Trăng', 'mien-nam'],
        ['an-giang', 'An Giang', 'mien-nam'], ['dong-thap', 'Đồng Tháp', 'mien-nam'],
        ['phu-quoc', 'Phú Quốc', 'mien-nam'], ['binh-phuoc', 'Bình Phước', 'mien-nam'],
    ] as [$slug, $name, $parent]) {
        $regions[$slug] = $region($pdo, $slug, $name, $parentRegions[$parent]);
    }
    foreach ([
        'mien-bac-demo' => 'mien-bac', 'mien-trung-demo' => 'mien-trung-tay-nguyen',
        'mien-nam-demo' => 'mien-nam', 'ha-noi-demo' => 'ha-noi', 'moc-chau-demo' => 'moc-chau',
        'ly-son-demo' => 'ly-son', 'da-lat-demo' => 'da-lat', 'buon-ma-thuot-demo' => 'buon-ma-thuot',
        'gia-lai-demo' => 'gia-lai', 'tien-giang-demo' => 'tien-giang', 'ben-tre-demo' => 'ben-tre',
        'vinh-long-demo' => 'vinh-long', 'soc-trang-demo' => 'soc-trang', 'an-giang-demo' => 'an-giang',
        'dong-thap-demo' => 'dong-thap', 'phu-quoc-demo' => 'phu-quoc', 'binh-phuoc-demo' => 'binh-phuoc',
    ] as $legacyKey => $canonicalKey) {
        $regions[$legacyKey] = $regions[$canonicalKey];
    }

    $categories = [];
    foreach ([
        ['rau-an-la', 'Rau ăn lá'], ['rau-cu-qua', 'Rau củ quả'], ['cu-than-cu', 'Củ và thân củ'],
        ['trai-cay', 'Trái cây'], ['gao-ngu-coc-va-dau-hat', 'Gạo ngũ cốc và đậu hạt'],
        ['nam-hat-cong-nghiep', 'Nấm và hạt cây công nghiệp'],
    ] as [$slug, $name]) {
        $find = $pdo->prepare('SELECT id FROM categories WHERE slug = :slug LIMIT 1');
        $find->execute(['slug' => $slug]);
        $id = $find->fetchColumn();
        if ($id === false || $id === null) {
            $id = $findByName($pdo, 'categories', $name);
        }
        if ($id === false || $id === null) {
            $pdo->prepare('INSERT INTO categories (name, slug, description, sort_order, is_active) VALUES (:name, :slug, :description, :sort_order, 1)')
                ->execute(['name' => $name, 'slug' => $slug,                 'description' => 'Nhóm nông sản được tuyển chọn theo mùa.', 'sort_order' => count($categories) + 1]);
            $id = $pdo->lastInsertId();
        }
        $categories[$slug] = (int) $id;
    }
    $categories['trai-cay-demo'] = $categories['trai-cay'];
    $categories['gao-ngu-coc-demo'] = $categories['gao-ngu-coc-va-dau-hat'];

    $farms = [];
    foreach ([
        ['rau-ha-noi', 'Vườn rau ven đô Hà Nội', 'ha-noi-demo'], ['vuon-moc-chau', 'Nông trại cao nguyên Mộc Châu', 'moc-chau-demo'],
        ['vuon-ly-son', 'Vườn tỏi Lý Sơn', 'ly-son-demo'], ['rau-da-lat-demo', 'Nông trại rau Đà Lạt', 'da-lat-demo'],
        ['ca-phe-buon-ma-thuot-demo', 'Vườn cà phê Buôn Ma Thuột', 'buon-ma-thuot-demo'],
        ['vuon-gia-lai', 'Nông trại đất đỏ Gia Lai', 'gia-lai-demo'], ['vuon-tien-giang-demo', 'Vườn cây Tiền Giang', 'tien-giang-demo'],
        ['vuon-ben-tre-demo', 'Vườn bưởi Bến Tre', 'ben-tre-demo'], ['vuon-dong-thap-demo', 'Nông trại sen Đồng Tháp', 'dong-thap-demo'],
        ['tieu-phu-quoc-demo', 'Vườn tiêu Phú Quốc', 'phu-quoc-demo'], ['vuon-vinh-long-demo', 'Vườn cây Vĩnh Long', 'vinh-long-demo'],
        ['vuon-soc-trang-demo', 'Vườn hành tím Sóc Trăng', 'soc-trang-demo'], ['vuon-an-giang-demo', 'Nông trại lúa An Giang', 'an-giang-demo'],
        ['vuon-binh-phuoc-demo', 'Vườn điều Bình Phước', 'binh-phuoc-demo'],
    ] as [$slug, $name, $regionSlug]) {
        $find = $pdo->prepare('SELECT id FROM farms WHERE slug = :slug LIMIT 1');
        $find->execute(['slug' => $slug]);
        $id = $find->fetchColumn();
        if ($id === false || $id === null) {
            $id = $findByName($pdo, 'farms', $name);
        }
        if ($id === false) {
            $pdo->prepare(
                "INSERT INTO farms (name, slug, region_id, address, contact_name, certifications, description, status)
                 VALUES (:name, :slug, :region_id, :address, :contact_name, :certifications, :description, 'active')"
            )->execute([
                'name' => $name, 'slug' => $slug, 'region_id' => $regions[$regionSlug],
                'address' => $name . ', Việt Nam', 'contact_name' => 'Người phụ trách nông trại',
                'certifications' => 'VietGAP theo hồ sơ nông trại', 'description' => 'Nông trại đối tác cung cấp nông sản theo mùa.',
            ]);
            $id = $pdo->lastInsertId();
        } else {
            $pdo->prepare("UPDATE farms SET description = REPLACE(REPLACE(description, 'minh họa', 'đối tác'), 'dữ liệu mẫu', 'theo mùa'), contact_name = CASE WHEN contact_name LIKE '%minh họa%' THEN 'Người phụ trách nông trại' ELSE contact_name END, certifications = REPLACE(certifications, 'dữ liệu minh họa', 'theo hồ sơ nông trại') WHERE id = :id")
                ->execute(['id' => $id]);
        }
        $farms[$slug] = (int) $id;
    }

    $products = [
        ['rau-muong','Rau muống sạch','rau-an-la','ha-noi-demo','rau-ha-noi','bó',18000,'Rau muống non, thu hoạch trong ngày.'],
        ['cai-ngot','Cải ngọt theo mùa','rau-an-la','ha-noi-demo','rau-ha-noi','bó',22000,'Cải ngọt lá xanh, vị dịu.'],
        ['cai-xanh','Cải xanh Mộc Châu','rau-an-la','moc-chau-demo','vuon-moc-chau','bó',24000,'Cải xanh trồng ở vùng cao mát.'],
        ['xa-lach','Xà lách Đà Lạt','rau-an-la','da-lat-demo','rau-da-lat-demo','kg',42000,'Xà lách lá giòn, dùng trong ngày.'],
        ['rau-ma','Rau má tươi','rau-an-la','ha-noi-demo','rau-ha-noi','kg',35000,'Rau má tươi, lá nhỏ và xanh.'],
        ['mong-toi','Mồng tơi non','rau-an-la','ha-noi-demo','rau-ha-noi','bó',18000,'Mồng tơi non cho bữa cơm gia đình.'],
        ['ca-chua','Cà chua chín cây','rau-cu-qua','da-lat-demo','rau-da-lat-demo','kg',38000,'Cà chua chín tự nhiên, ruột chắc.'],
        ['dua-leo','Dưa leo giòn','rau-cu-qua','ha-noi-demo','rau-ha-noi','kg',28000,'Dưa leo tươi, vị thanh mát.'],
        ['kho-qua','Khổ qua tươi','rau-cu-qua','gia-lai-demo','vuon-gia-lai','kg',32000,'Khổ qua tươi, quả vừa lứa.'],
        ['bi-do','Bí đỏ hồ lô','rau-cu-qua','da-lat-demo','rau-da-lat-demo','kg',30000,'Bí đỏ ruột vàng, dẻo bùi.'],
        ['ca-rot-da-lat-demo','Cà rốt Đà Lạt','rau-cu-qua','da-lat-demo','rau-da-lat-demo','kg',36000,'Cà rốt củ nhỏ, vị ngọt tự nhiên.'],
        ['ot-hiem','Ớt hiểm tươi','rau-cu-qua','gia-lai-demo','vuon-gia-lai','kg',65000,'Ớt hiểm cay thơm, thu hoạch chọn lọc.'],
        ['khoai-lang-tim','Khoai lang tím','cu-than-cu','vinh-long-demo','vuon-vinh-long-demo','kg',38000,'Khoai lang tím bùi, củ đều.'],
        ['khoai-tay-demo','Khoai tây Đà Lạt','cu-than-cu','da-lat-demo','rau-da-lat-demo','kg',34000,'Khoai tây tươi, vỏ mỏng.'],
        ['hanh-tim-vinh-chau','Hành tím Vĩnh Châu','cu-than-cu','soc-trang-demo','vuon-soc-trang-demo','kg',52000,'Hành tím thơm, củ chắc.'],
        ['toi-ly-son','Tỏi Lý Sơn','cu-than-cu','ly-son-demo','vuon-ly-son','kg',145000,'Tỏi Lý Sơn tép nhỏ, thơm dịu.'],
        ['gung-tuoi','Gừng tươi','cu-than-cu','gia-lai-demo','vuon-gia-lai','kg',58000,'Gừng tươi cay ấm, củ nhiều nhánh.'],
        ['nghe-tuoi','Nghệ tươi','cu-than-cu','binh-phuoc-demo','vuon-gia-lai','kg',48000,'Nghệ tươi màu vàng tự nhiên.'],
        ['xoai-cat-hoa-loc-demo','Xoài cát Hòa Lộc','trai-cay-demo','tien-giang-demo','vuon-tien-giang-demo','kg',92000,'Xoài thơm, thịt mềm và ngọt.'],
        ['buoi-da-xanh-demo','Bưởi da xanh','trai-cay-demo','ben-tre-demo','vuon-ben-tre-demo','trái',78000,'Bưởi da xanh tép hồng, ít hạt.'],
        ['chom-chom','Chôm chôm miền Tây','trai-cay-demo','vinh-long-demo','vuon-vinh-long-demo','kg',55000,'Chôm chôm tươi theo mùa.'],
        ['sau-rieng','Sầu riêng Ri6','trai-cay-demo','tien-giang-demo','vuon-tien-giang-demo','kg',135000,'Sầu riêng Ri6 cơm vàng, thu hoạch theo lứa.'],
        ['thanh-long','Thanh long ruột đỏ','trai-cay-demo','binh-phuoc-demo','vuon-binh-phuoc-demo','kg',48000,'Thanh long ruột đỏ, vị thanh.'],
        ['cam-sanh','Cam sành miền Nam','trai-cay-demo','vinh-long-demo','vuon-vinh-long-demo','kg',42000,'Cam sành mọng nước, vị chua dịu.'],
        ['gao-st25','Gạo ST25','gao-ngu-coc-demo','soc-trang-demo','vuon-soc-trang-demo','túi 5kg',165000,'Gạo thơm hạt dài, nấu dẻo vừa.'],
        ['gao-lut','Gạo lứt đỏ','gao-ngu-coc-demo','dong-thap-demo','vuon-dong-thap-demo','túi 1kg',52000,'Gạo lứt đỏ nguyên cám.'],
        ['gao-nep','Nếp thơm','gao-ngu-coc-demo','an-giang-demo','vuon-an-giang-demo','kg',38000,'Nếp dẻo thơm, hạt đều.'],
        ['dau-xanh','Đậu xanh nguyên hạt','gao-ngu-coc-demo','dong-thap-demo','vuon-dong-thap-demo','túi 500g',34000,'Đậu xanh hạt mẩy, sạch.'],
        ['dau-phong','Đậu phộng vỏ lụa','gao-ngu-coc-demo','binh-phuoc-demo','vuon-binh-phuoc-demo','túi 500g',39000,'Đậu phộng hạt chắc, bùi.'],
        ['me-den','Mè đen','gao-ngu-coc-demo','an-giang-demo','vuon-an-giang-demo','túi 500g',45000,'Mè đen thơm, dùng trong món ăn hằng ngày.'],
        ['nam-rom','Nấm rơm tươi','nam-hat-cong-nghiep','dong-thap-demo','vuon-dong-thap-demo','khay',48000,'Nấm rơm tươi, thu hoạch trong ngày.'],
        ['nam-bao-ngu','Nấm bào ngư','nam-hat-cong-nghiep','da-lat-demo','rau-da-lat-demo','khay',42000,'Nấm bào ngư mũ dày, vị dịu.'],
        ['tieu-phu-quoc','Hạt tiêu Phú Quốc','nam-hat-cong-nghiep','phu-quoc-demo','tieu-phu-quoc-demo','túi 500g',98000,'Hạt tiêu thơm cay tự nhiên.'],
        ['hat-dieu','Hạt điều Bình Phước','nam-hat-cong-nghiep','binh-phuoc-demo','vuon-binh-phuoc-demo','túi 500g',115000,'Hạt điều thô, hạt chắc.'],
        ['ca-phe-nhan-buon-ma-thuot','Cà phê nhân Buôn Ma Thuột','nam-hat-cong-nghiep','buon-ma-thuot-demo','ca-phe-buon-ma-thuot-demo','kg',145000,'Cà phê nhân Robusta vùng cao.'],
        ['nhan-tuoi','Nhãn tươi miền Nam','nam-hat-cong-nghiep','tien-giang-demo','vuon-tien-giang-demo','kg',62000,'Nhãn tươi cùi dày, vị ngọt.'],
    ];
    $insertProduct = $pdo->prepare(
        "INSERT INTO products
         (category_id, region_id, farm_id, name, slug, sku, unit, price, sale_price, suggested_shelf_life_days,
          storage_instructions, certifications, short_description, season, nutrition, preparation, tags,
          seo_title, seo_description, status, is_featured, total_stock, min_stock, description)
         VALUES (:category_id, :region_id, :farm_id, :name, :slug, :sku, :unit, :price, :sale_price, :shelf,
          :storage, :certifications, :short, :season, :nutrition, :preparation, :tags, :seo_title, :seo_description,
          'active', :featured, :stock, :min_stock, :description)
         ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), region_id=VALUES(region_id), farm_id=VALUES(farm_id),
          name=VALUES(name), unit=VALUES(unit), price=VALUES(price), sale_price=VALUES(sale_price),
          suggested_shelf_life_days=VALUES(suggested_shelf_life_days), storage_instructions=VALUES(storage_instructions),
          certifications=VALUES(certifications), short_description=VALUES(short_description), season=VALUES(season),
          nutrition=VALUES(nutrition), preparation=VALUES(preparation), tags=VALUES(tags), seo_title=VALUES(seo_title),
          seo_description=VALUES(seo_description), status='active', is_featured=VALUES(is_featured),
          total_stock=VALUES(total_stock), min_stock=VALUES(min_stock), description=VALUES(description), deleted_at=NULL"
    );
    $findProduct = $pdo->prepare('SELECT id FROM products WHERE slug = :slug');
    $findProductByName = static function (PDO $pdo, string $name) use ($normalize): ?array {
        foreach ($pdo->query('SELECT id, name, slug FROM products')->fetchAll() as $row) {
            if ($normalize((string) $row['name']) === $normalize($name)) {
                return $row;
            }
        }
        return null;
    };
    $image = $pdo->prepare('INSERT INTO product_images (product_id, image_url, alt_text, sort_order, is_primary)
                            SELECT :product_id, :image_url, :alt_text, 0, 1
                            WHERE NOT EXISTS (SELECT 1 FROM product_images WHERE product_id = :product_id_check)');
    $batch = $pdo->prepare(
        "INSERT INTO product_batches
         (product_id, farm_id, batch_code, qr_token, harvested_at, packed_at, expires_at, quantity_received,
          quantity_remaining, average_unit_cost, inspection_result, inspection_notes, status)
         VALUES (:product_id, :farm_id, :code, :qr, CURDATE() - INTERVAL 3 DAY, CURDATE() - INTERVAL 2 DAY,
          :expires, :received, :remaining, :cost, 'passed', :notes, 'selling')
         ON DUPLICATE KEY UPDATE product_id=VALUES(product_id), farm_id=VALUES(farm_id), expires_at=VALUES(expires_at),
          quantity_received=VALUES(quantity_received), quantity_remaining=VALUES(quantity_remaining),
          average_unit_cost=VALUES(average_unit_cost), inspection_result='passed', status='selling'"
    );
    $event = $pdo->prepare(
        'INSERT INTO trace_events (batch_id, event_type, event_at, location, description, sort_order)
         SELECT :batch_id, :type, :event_at, :location, :description, :sort_order
         WHERE NOT EXISTS (SELECT 1 FROM trace_events WHERE batch_id = :batch_check AND event_type = :type_check)'
    );
    $receipt = $pdo->prepare(
        "INSERT INTO warehouse_receipts (receipt_no, supplier_name, supplier_farm_id, received_at, status, confirmed_at, notes)
         VALUES ('DEMO-RECEIPT-001', 'Các nông trại minh họa', :farm_id, NOW(), 'received', NOW(), 'Dữ liệu mẫu')
         ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)"
    );
    $receipt->execute(['farm_id' => $farms['rau-ha-noi']]);
    $receiptId = (int) $pdo->lastInsertId();
    $receiptItem = $pdo->prepare(
        'INSERT INTO warehouse_receipt_items (receipt_id, batch_id, quantity, unit_cost)
         VALUES (:receipt_id, :batch_id, :quantity, :cost)
         ON DUPLICATE KEY UPDATE quantity=VALUES(quantity), unit_cost=VALUES(unit_cost)'
    );
    foreach ($products as $index => [$slug, $name, $category, $region, $farm, $unit, $price, $short]) {
        $remaining = $index % 13 === 0 ? 0 : ($index % 7 === 0 ? 3 : 24 + ($index % 5) * 6);
        $received = max($remaining, 48);
        $expires = $index % 11 === 0 ? date('Y-m-d', strtotime('-2 days')) : date('Y-m-d', strtotime('+' . (10 + ($index % 40)) . ' days'));
        $season = in_array($category, ['trai-cay-demo', 'trai-cay'], true) ? 'Theo mùa, tùy vùng trồng.' : 'Quanh năm, cao điểm theo mùa mưa hoặc mùa nắng.';
        $existing = $findProductByName($pdo, $name);
        if ($existing !== null) {
            $slug = (string) $existing['slug'];
        }
        $insertProduct->execute([
            'category_id' => $categories[$category], 'region_id' => $regions[$region], 'farm_id' => $farms[$farm],
            'name' => $name, 'slug' => $slug, 'sku' => 'NS-DEMO-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
            'unit' => $unit, 'price' => $price, 'sale_price' => $index % 5 === 0 ? $price - 3000 : null,
            'shelf' => 14, 'storage' => 'Bảo quản nơi khô mát; sản phẩm tươi nên dùng sớm.', 'certifications' => 'VietGAP theo hồ sơ nông trại',
            'short' => $short, 'season' => $season, 'nutrition' => 'Cung cấp chất xơ và các dưỡng chất tự nhiên của nông sản.',
            'preparation' => 'Rửa sạch, sơ chế theo món ăn; dùng trong ngày sau khi mở hoặc cắt.', 'tags' => $name . ', nông sản sạch, theo mùa',
            'seo_title' => $name . ' | Nông sản sạch', 'seo_description' => $short . ' Có thông tin truy xuất nguồn gốc.',
            'featured' => $index < 6 ? 1 : 0, 'stock' => $remaining, 'min_stock' => 5,
            'description' => $short . "\n\nSản phẩm được chọn từ nông trại đối tác, đóng gói theo quy cách ghi trên trang. Thông tin sản phẩm được cập nhật theo từng lô hàng.",
        ]);
        $findProduct->execute(['slug' => $slug]);
        $productId = (int) $findProduct->fetchColumn();
        $image->execute(['product_id' => $productId, 'product_id_check' => $productId, 'image_url' => '/img/products/_fallback/' . $category . '.svg', 'alt_text' => $name]);
        $code = 'DEMO-BATCH-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
        $batch->execute(['product_id' => $productId, 'farm_id' => $farms[$farm], 'code' => $code, 'qr' => hash('sha256', 'nongsan-demo-' . $code), 'expires' => $expires, 'received' => $received, 'remaining' => $remaining, 'cost' => max(1000, $price * 0.6), 'notes' => 'Lô hàng được kiểm tra trước khi nhập kho.']);
        $batchId = (int) $pdo->query("SELECT id FROM product_batches WHERE batch_code = " . $pdo->quote($code))->fetchColumn();
        foreach ([['planting', 30, 'Chuẩn bị đất và chăm sóc theo quy trình canh tác.'], ['harvest', 3, 'Thu hoạch và kiểm tra chất lượng tại nông trại.'], ['packaging', 2, 'Đóng gói, ghi nhận mã lô và chuyển vào kho.']] as $sort => [$type, $days, $description]) {
            $event->execute(['batch_id' => $batchId, 'batch_check' => $batchId, 'type' => $type, 'type_check' => $type, 'event_at' => date('Y-m-d H:i:s', strtotime('-' . $days . ' days')), 'location' => $name, 'description' => $description, 'sort_order' => $sort + 1]);
        }
        $receiptItem->execute(['receipt_id' => $receiptId, 'batch_id' => $batchId, 'quantity' => $received, 'cost' => max(1000, $price * 0.6)]);
    }
    loadCommerceSampleData($pdo);
    $pdo->commit();
    echo "Đã nạp/cập nhật 36 sản phẩm mẫu, 14 vùng, 10 nông trại và 36 lô.\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
