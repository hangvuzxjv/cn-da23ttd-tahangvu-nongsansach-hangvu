<?php

$router->get('/', [HomeController::class, 'index']);
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
$router->get('/admin/batches', static fn () => (new AdminController())->section('batches'), ['permission:batches.view']);
$router->get('/admin/products', static fn () => (new AdminController())->section('products'), ['permission:products.view']);
$router->get('/products/{slug}', [HomeController::class, 'product']);
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
