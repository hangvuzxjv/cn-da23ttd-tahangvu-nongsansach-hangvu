<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

function dashboardAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function dashboardReportSum(ReportService $service, string $type, string $from, string $to, string $column): float
{
    $sum = 0.0;
    foreach ($service->stream($type, $from, $to) as $row) {
        $sum += (float) ($row[$column] ?? 0);
    }
    return $sum;
}

function dashboardInsertOrder(
    PDO $database,
    int $userId,
    string $orderNo,
    string $status,
    int $total,
    int $productId,
    string $snapshotName,
    int $unitPrice,
    int $quantity,
    int $regionId,
    ?int $flashSaleItemId
): int {
    $database->prepare(
        'INSERT INTO orders
            (user_id, order_no, status, payment_status, recipient_name, recipient_phone,
             shipping_address, subtotal, total_amount, region_id)
         VALUES (:user_id, :order_no, :status, \'paid\', \'Report Test\', \'0900000000\',
                 \'Test address\', :subtotal, :order_total, :region_id)'
    )->execute([
        'user_id' => $userId,
        'order_no' => $orderNo,
        'status' => $status,
        'subtotal' => $total,
        'order_total' => $total,
        'region_id' => $regionId,
    ]);
    $orderId = (int) $database->lastInsertId();
    $database->prepare(
        'INSERT INTO order_items
            (order_id, product_id, flash_sale_item_id, product_name, sku, unit, unit_price, quantity, line_total)
         VALUES (:order_id, :product_id, :flash_sale_item_id, :name, \'REPORT-TEST\', \'kg\', :unit_price, :quantity, :line_total)'
    )->execute([
        'order_id' => $orderId,
        'product_id' => $productId,
        'flash_sale_item_id' => $flashSaleItemId,
        'name' => $snapshotName,
        'unit_price' => $unitPrice,
        'quantity' => $quantity,
        'line_total' => $unitPrice * $quantity,
    ]);
    $database->prepare(
        'INSERT INTO payments (order_id, method, amount, status) VALUES (:order_id, \'cod\', :amount, \'paid\')'
    )->execute(['order_id' => $orderId, 'amount' => $total]);
    return $orderId;
}

dashboardAssert(
    ReportService::csvText('=1+1') === "'=1+1"
        && ReportService::csvText('+SUM(A1:A2)') === "'+SUM(A1:A2)"
        && ReportService::csvText('-1+1') === "'-1+1"
        && ReportService::csvText('@SUM(A1)') === "'@SUM(A1)"
        && ReportService::csvText(' +SUM(A1:A2)') === "' +SUM(A1:A2)"
        && ReportService::csvText('nông sản') === 'nông sản',
    'CSV output must neutralize formula prefixes while preserving ordinary Vietnamese text.'
);
$unsafeHtml = StaticPageService::sanitizeHtml(
    '<h2>Thông tin</h2><script>alert(1)</script><p onclick="evil()">An toàn <strong>tươi</strong></p>'
    . '<a href="javascript:alert(1)" onmouseover="evil()">liên kết</a>'
);
dashboardAssert(
    !str_contains($unsafeHtml, '<script')
        && !str_contains($unsafeHtml, 'onclick')
        && !str_contains($unsafeHtml, 'javascript:')
        && str_contains($unsafeHtml, '<strong>tươi</strong>')
        && str_contains($unsafeHtml, 'liên kết'),
    'CMS HTML filtering must preserve allowed markup and remove executable content and attributes.'
);

$permissions = require BACKEND_PATH . '/config/permissions.php';
dashboardAssert(
    !in_array('reports.view', $permissions['customer'] ?? [], true)
        && !in_array('reports.view', $permissions['staff_sales'] ?? [], true)
        && !in_array('reports.view', $permissions['staff_warehouse'] ?? [], true)
        && in_array('*', $permissions['admin'] ?? [], true),
    'Only administrators should receive report permissions.'
);

$database = inventoryTestDatabase();
$adminId = (int) $database->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1")->fetchColumn();
$product = $database->query(
    "SELECT p.id, p.farm_id, p.category_id, p.region_id, p.unit, p.price, r.parent_id
     FROM products p JOIN regions r ON r.id = p.region_id AND r.level = 2
     WHERE p.status = 'active' AND p.deleted_at IS NULL AND p.farm_id IS NOT NULL
     ORDER BY p.id LIMIT 1"
)->fetch();
if ($adminId < 1 || !$product) {
    throw new RuntimeException('Dashboard report tests require seeded admin and active product fixtures.');
}
$aboutPage = $database->query(
    "SELECT title, content, status FROM pages WHERE slug = 'gioi-thieu' LIMIT 1"
)->fetch();
dashboardAssert(
    $aboutPage && $aboutPage['status'] === 'published'
        && $aboutPage['title'] === 'Giới thiệu'
        && str_contains($aboutPage['content'], 'nông sản theo mùa')
        && !str_contains($aboutPage['content'], 'Trang giới thiệu mẫu'),
    'The migration must provide real Vietnamese static-page content without retaining the sample placeholder.'
);

$token = strtoupper(bin2hex(random_bytes(6)));
$email = 'dashboard-report-' . strtolower($token) . '@example.test';
$suffix = 'REPORT-' . $token;
$from = date('Y-m-d', strtotime('-1 day'));
$to = date('Y-m-d', strtotime('+1 day'));
$emptyFrom = '2099-01-01';
$emptyTo = '2099-01-02';
$database->beginTransaction();
try {
    $database->prepare(
        "INSERT INTO users (name, email, phone, password, role, status)
         VALUES ('Dashboard Report Test', :email, '0900000000', :password, 'customer', 'active')"
    )->execute([
        'email' => $email,
        'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
    ]);
    $userId = (int) $database->lastInsertId();

    $parentProductName = $suffix . '-PARENT';
    $database->prepare(
        "INSERT INTO products
            (category_id, region_id, farm_id, name, slug, sku, unit, price, status, total_stock, min_stock)
         VALUES (:category_id, :region_id, :farm_id, :name, :slug, :sku, :unit, :price, 'active', 0, 0)"
    )->execute([
        'category_id' => $product['category_id'],
        'region_id' => $product['parent_id'],
        'farm_id' => $product['farm_id'],
        'name' => $parentProductName,
        'slug' => strtolower($suffix) . '-parent',
        'sku' => $suffix . '-P',
        'unit' => $product['unit'],
        'price' => $product['price'],
    ]);
    $parentProductId = (int) $database->lastInsertId();

    $campaignName = 'Dashboard Report ' . $token;
    $database->prepare(
        "INSERT INTO flash_sales (name, starts_at, ends_at, status)
         VALUES (:name, DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_ADD(NOW(), INTERVAL 1 HOUR), 'active')"
    )->execute(['name' => $campaignName]);
    $campaignId = (int) $database->lastInsertId();
    $database->prepare(
        'INSERT INTO flash_sale_items (flash_sale_id, product_id, sale_price, quota, sold_quantity, per_user_limit)
         VALUES (:campaign_id, :product_id, 700, 2, 2, 2)'
    )->execute(['campaign_id' => $campaignId, 'product_id' => $parentProductId]);
    $saleItemId = (int) $database->lastInsertId();

    $reportService = new ReportService($database);
    $dashboardBefore = $reportService->dashboard();
    $beforeRevenue = dashboardReportSum($reportService, 'revenue', $from, $to, 'revenue');
    $beforePaymentRevenue = dashboardReportSum($reportService, 'payments', $from, $to, 'revenue');
    $beforeRegions = [];
    foreach ($reportService->stream('regions', $from, $to) as $row) {
        $beforeRegions[(string) $row['region_name']] = (float) $row['revenue'];
    }
    $childRegionId = (int) $product['region_id'];
    $parentRegionId = (int) $product['parent_id'];
    $parentRegion = $database->prepare('SELECT name FROM regions WHERE id = :id');
    $parentRegion->execute(['id' => $parentRegionId]);
    $parentRegionName = (string) $parentRegion->fetchColumn();
    dashboardInsertOrder($database, $userId, 'DR-N-' . $token, 'completed', 2500, (int) $product['id'], $suffix, 1250, 2, $childRegionId, null);
    dashboardInsertOrder($database, $userId, 'DR-F-' . $token, 'completed', 1400, $parentProductId, $parentProductName, 700, 2, $parentRegionId, $saleItemId);
    dashboardInsertOrder($database, $userId, 'DR-C-' . $token, 'cancelled', 9000, (int) $product['id'], $suffix, 9000, 1, $childRegionId, null);

    dashboardAssert(
        dashboardReportSum($reportService, 'revenue', $from, $to, 'revenue') - $beforeRevenue === 3900.0,
        'Revenue reports must exclude cancelled orders and use each order total snapshot.'
    );
    dashboardAssert(
        dashboardReportSum($reportService, 'payments', $from, $to, 'revenue') - $beforePaymentRevenue === 3900.0,
        'Payment reports must count each valid order once using its latest payment method.'
    );
    $productRows = [];
    foreach ($reportService->stream('products', $from, $to) as $row) {
        $productRows[(string) $row['product_name']] = [
            'revenue' => (float) $row['revenue'],
            'quantity' => (int) $row['quantity'],
        ];
    }
    dashboardAssert(
        ($productRows[$suffix]['revenue'] ?? 0.0) === 2500.0
            && ($productRows[$suffix]['quantity'] ?? 0) === 2
            && ($productRows[$parentProductName]['revenue'] ?? 0.0) === 1400.0
            && ($productRows[$parentProductName]['quantity'] ?? 0) === 2,
        'Product reports must group by each product-name snapshot and sum its line-total and quantity snapshots.'
    );
    $afterRegions = [];
    foreach ($reportService->stream('regions', $from, $to) as $row) {
        $afterRegions[(string) $row['region_name']] = (float) $row['revenue'];
    }
    dashboardAssert(
        ($afterRegions[$parentRegionName] ?? 0.0) - ($beforeRegions[$parentRegionName] ?? 0.0) === 3900.0,
        'Regional revenue must group child-region orders into their parent region and include parent-level orders.'
    );
    $flashRevenue = 0.0;
    foreach ($reportService->stream('flash_sales', $from, $to) as $row) {
        if ($row['campaign_name'] === $campaignName) {
            $flashRevenue += (float) $row['revenue'];
            dashboardAssert((int) $row['quota'] === 2 && (int) $row['sold_quantity'] === 2, 'Flash Sale quota and sold figures must match campaign snapshots.');
        }
    }
    dashboardAssert($flashRevenue === 1400.0, 'Flash Sale revenue must use the discounted order-item snapshot and exclude cancelled orders.');

    $dashboardAfter = $reportService->dashboard();
    dashboardAssert(
        (float) $dashboardAfter['revenue']['last_30_days'] - (float) $dashboardBefore['revenue']['last_30_days'] === 3900.0,
        'Dashboard revenue must exclude cancelled orders and preserve both regular and Flash Sale snapshot totals.'
    );
    $dashboardProductRows = [];
    foreach ($dashboardAfter['topProducts'] as $row) {
        $dashboardProductRows[(string) $row['product_name']] = (float) $row['revenue'];
    }
    dashboardAssert(
        ($dashboardProductRows[$suffix] ?? 0.0) === 2500.0
            && ($dashboardProductRows[$parentProductName] ?? 0.0) === 1400.0,
        'Dashboard top products must use each regular and Flash Sale product-name and line-total snapshot.'
    );
    $dashboardRegionRevenue = array_sum(array_map(
        static fn (array $row): float => $row['region_name'] === $parentRegionName ? (float) $row['revenue'] : 0.0,
        $dashboardAfter['regionalRevenue']
    ));
    $beforeDashboardRegionRevenue = array_sum(array_map(
        static fn (array $row): float => $row['region_name'] === $parentRegionName ? (float) $row['revenue'] : 0.0,
        $dashboardBefore['regionalRevenue']
    ));
    dashboardAssert(
        $dashboardRegionRevenue - $beforeDashboardRegionRevenue === 3900.0,
        'Dashboard regional chart must aggregate child- and parent-region orders at the same parent.'
    );

    foreach (array_keys(ReportService::definitions()) as $type) {
        $nonEmptyRange = $reportService->report($type, $from, $to, 1, 1000);
        $emptyRange = $reportService->report($type, $emptyFrom, $emptyTo, 1, 1000);
        dashboardAssert(is_array($nonEmptyRange['rows']) && is_array($emptyRange['rows']), 'Every report must execute through paginated queries in both date ranges.');
        foreach ($reportService->stream($type, $from, $to) as $_row) {
        }
        foreach ($reportService->stream($type, $emptyFrom, $emptyTo) as $_row) {
        }
        if ($type !== 'inventory') {
            dashboardAssert($emptyRange['rows'] === [], 'Date-filtered reports without matching data must return no rows.');
        }
    }

    $batchCode = 'REPORT-' . $token;
    $database->prepare(
        'INSERT INTO product_batches
            (product_id, farm_id, batch_code, qr_token, harvested_at, expires_at,
             quantity_received, quantity_remaining, status)
         VALUES (:product_id, :farm_id, :batch_code, :qr_token, CURDATE(),
                 DATE_ADD(CURDATE(), INTERVAL 30 DAY), 7, 7, \'selling\')'
    )->execute([
        'product_id' => $product['id'],
        'farm_id' => $product['farm_id'],
        'batch_code' => $batchCode,
        'qr_token' => hash('sha256', random_bytes(32)),
    ]);
    $batchId = (int) $database->lastInsertId();
    foreach ([['in', 10], ['out', -3]] as [$movementType, $quantity]) {
        $database->prepare(
            'INSERT INTO inventory_movements (product_id, batch_id, movement_type, quantity, performed_by)
             VALUES (:product_id, :batch_id, :movement_type, :quantity, :user_id)'
        )->execute([
            'product_id' => $product['id'],
            'batch_id' => $batchId,
            'movement_type' => $movementType,
            'quantity' => $quantity,
            'user_id' => $adminId,
        ]);
    }
    $inventory = null;
    foreach ($reportService->stream('inventory', $from, $to) as $row) {
        if ($row['batch_code'] === $batchCode) {
            $inventory = $row;
            break;
        }
    }
    dashboardAssert(
        $inventory !== null
            && (int) $inventory['opening_stock'] === 0
            && (int) $inventory['incoming'] === 10
            && (int) $inventory['outgoing'] === 3
            && (int) $inventory['closing_stock'] === 7
            && $inventory['expiry_status'] === 'Sắp hết hạn',
        'Inventory report must correctly calculate opening, incoming, outgoing, and current batch stock.'
    );

    $database->rollBack();
} catch (Throwable $exception) {
    if ($database->inTransaction()) {
        $database->rollBack();
    }
    throw $exception;
}

echo "PASS CSV formula neutralization\n";
echo "PASS CMS HTML whitelist filtering\n";
echo "PASS report access permissions\n";
echo "PASS localized static-page migration content\n";
echo "PASS ONLY_FULL_GROUP_BY dashboard and every report query with/without data";
echo "PASS cancelled order exclusion and snapshot revenue\n";
echo "PASS Flash Sale revenue and inventory movement balances\n";
