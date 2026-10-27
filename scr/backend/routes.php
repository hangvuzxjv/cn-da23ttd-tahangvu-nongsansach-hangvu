<?php

$router->get('/', [HomeController::class, 'index']);
$router->get('/san-pham', [StorefrontController::class, 'shop']);
$router->get('/san-pham/{slug}', [StorefrontController::class, 'product']);
$router->get('/vung-mien/{slug}', [StorefrontController::class, 'region']);
$router->get('/danh-muc/{slug}', [StorefrontController::class, 'category']);
$router->get('/sitemap.xml', [StorefrontController::class, 'sitemap']);
$router->get('/truy-xuat', [TraceabilityController::class, 'lookup']);
$router->get('/truy-xuat/{code}', [TraceabilityController::class, 'show']);
$router->get('/qr/{code}', [TraceabilityController::class, 'qr']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login'], ['csrf']);
$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register'], ['csrf']);
$router->get('/forgot-password', [AuthController::class, 'showForgotPassword']);
$router->post('/forgot-password', [AuthController::class, 'forgotPassword'], ['csrf']);
$router->get('/reset-password/{slug}', [AuthController::class, 'showResetPassword']);
$router->post('/reset-password/{slug}', [AuthController::class, 'resetPassword'], ['csrf']);
$router->get('/verify-email/{slug}', [AuthController::class, 'verifyEmail']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth', 'csrf']);
$router->get('/profile', [ProfileController::class, 'show'], ['auth']);
$router->post('/profile', [ProfileController::class, 'update'], ['auth', 'csrf']);
$router->post('/profile/password', [ProfileController::class, 'changePassword'], ['auth', 'csrf']);
$router->get('/addresses', [ProfileController::class, 'addresses'], ['auth']);
$router->post('/addresses', [ProfileController::class, 'storeAddress'], ['auth', 'csrf']);
$router->post('/addresses/{id}', [ProfileController::class, 'updateAddress'], ['auth', 'csrf']);
$router->post('/addresses/{id}/delete', [ProfileController::class, 'deleteAddress'], ['auth', 'csrf']);
$router->get('/admin', [AdminController::class, 'index'], ['permission:dashboard.view']);
$router->get('/admin/users', [AdminController::class, 'users'], ['permission:users.view']);
$router->post('/admin/users/{id}/update', [AdminController::class, 'updateUser'], ['permission:users.manage', 'csrf']);
$router->get('/admin/orders', static fn () => (new AdminController())->section('orders'), ['permission:orders.view']);
$router->get('/admin/vouchers', static fn () => (new AdminController())->section('vouchers'), ['permission:vouchers.view']);
$router->get('/admin/reviews', static fn () => (new AdminController())->section('reviews'), ['permission:reviews.view']);
$router->get('/admin/customers', [AdminController::class, 'customers'], ['permission:customers.view']);
$router->get('/admin/inventory', static fn () => (new AdminController())->section('inventory'), ['permission:inventory.view']);
$router->get('/admin/batches', [TraceAdminController::class, 'batches'], ['permission:batches.view']);
$router->post('/admin/batches', [TraceAdminController::class, 'storeBatch'], ['permission:batches.manage', 'csrf']);
$router->get('/admin/batches/{id}', [TraceAdminController::class, 'showBatch'], ['permission:batches.view']);
$router->post('/admin/batches/{id}/update', [TraceAdminController::class, 'updateBatch'], ['permission:batches.manage', 'csrf']);
$router->post('/admin/batches/{id}/delete', [TraceAdminController::class, 'deleteBatch'], ['permission:batches.manage', 'csrf']);
$router->post('/admin/batches/{id}/events', [TraceAdminController::class, 'storeEvent'], ['permission:batches.manage', 'csrf']);
$router->post('/admin/batches/{id}/events/quick', [TraceAdminController::class, 'quickEvents'], ['permission:batches.manage', 'csrf']);
$router->post('/admin/trace-events/{id}/update', [TraceAdminController::class, 'updateEvent'], ['permission:batches.manage', 'csrf']);
$router->post('/admin/trace-events/{id}/delete', [TraceAdminController::class, 'deleteEvent'], ['permission:batches.manage', 'csrf']);
$router->get('/admin/farms', [TraceAdminController::class, 'farms'], ['permission:farms.view']);
$router->post('/admin/farms', [TraceAdminController::class, 'storeFarm'], ['permission:farms.manage', 'csrf']);
$router->post('/admin/farms/{id}/update', [TraceAdminController::class, 'updateFarm'], ['permission:farms.manage', 'csrf']);
$router->post('/admin/farms/{id}/delete', [TraceAdminController::class, 'deleteFarm'], ['permission:farms.manage', 'csrf']);
$router->get('/admin/labels', [TraceAdminController::class, 'printLabels'], ['permission:batches.view']);
$router->get('/admin/regions', [CatalogAdminController::class, 'regions'], ['permission:regions.view']);
$router->post('/admin/regions', [CatalogAdminController::class, 'storeRegion'], ['permission:regions.manage', 'csrf']);
$router->post('/admin/regions/{id}/update', [CatalogAdminController::class, 'updateRegion'], ['permission:regions.manage', 'csrf']);
$router->post('/admin/regions/{id}/delete', [CatalogAdminController::class, 'deleteRegion'], ['permission:regions.manage', 'csrf']);
$router->get('/admin/categories', [CatalogAdminController::class, 'categories'], ['permission:categories.view']);
$router->post('/admin/categories', [CatalogAdminController::class, 'storeCategory'], ['permission:categories.manage', 'csrf']);
$router->post('/admin/categories/{id}/update', [CatalogAdminController::class, 'updateCategory'], ['permission:categories.manage', 'csrf']);
$router->post('/admin/categories/{id}/delete', [CatalogAdminController::class, 'deleteCategory'], ['permission:categories.manage', 'csrf']);
$router->get('/admin/products', [CatalogAdminController::class, 'products'], ['permission:products.view']);
$router->get('/admin/products/new', [CatalogAdminController::class, 'newProduct'], ['permission:products.manage']);
$router->post('/admin/products', [CatalogAdminController::class, 'storeProduct'], ['permission:products.manage', 'csrf']);
$router->get('/admin/products/{id}/edit', [CatalogAdminController::class, 'editProduct'], ['permission:products.manage']);
$router->post('/admin/products/{id}/edit', [CatalogAdminController::class, 'updateProduct'], ['permission:products.manage', 'csrf']);
$router->post('/admin/products/{id}/delete', [CatalogAdminController::class, 'deleteProduct'], ['permission:products.manage', 'csrf']);
$router->post('/admin/products/{id}/images/reorder', [CatalogAdminController::class, 'reorderImages'], ['permission:products.manage', 'csrf']);
$router->post('/admin/products/images/{id}/delete', [CatalogAdminController::class, 'deleteImage'], ['permission:products.manage', 'csrf']);
$router->get('/products/{slug}', [StorefrontController::class, 'product']);
$router->get('/users/{id}', [HomeController::class, 'user']);

if (($config['app']['debug'] ?? false) === true) {
    $router->get('/_dev/db-check', static function () use ($config): Response {
        $database = Database::connection();
        $tableQuery = $database->prepare(
            'SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = :database_name AND TABLE_TYPE = \'BASE TABLE\'
             ORDER BY TABLE_NAME'
        );
        $tableQuery->execute(['database_name' => $config['database']['name']]);
        $tables = $tableQuery->fetchAll(PDO::FETCH_COLUMN);

        $rows = '';
        foreach ($tables as $table) {
            $quotedTable = '`' . str_replace('`', '``', $table) . '`';
            $count = (int) $database->query('SELECT COUNT(*) FROM ' . $quotedTable)->fetchColumn();
            $rows .= '<tr><td>' . e($table) . '</td><td>' . $count . '</td></tr>';
        }

        $content = '<!doctype html><html lang="vi"><meta charset="utf-8"><title>Database check</title>'
            . '<h1>Kết nối cơ sở dữ liệu thành công</h1>'
            . '<p>Số bảng: ' . count($tables) . '</p>'
            . '<table><thead><tr><th>Bảng</th><th>Số bản ghi</th></tr></thead><tbody>'
            . $rows . '</tbody></table></html>';

        return new Response($content, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    });
}
