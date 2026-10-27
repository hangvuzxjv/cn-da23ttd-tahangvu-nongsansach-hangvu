<?php

$router->get('/', [HomeController::class, 'index']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login'], ['csrf']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth', 'csrf']);
$router->get('/admin', [AdminController::class, 'index'], ['auth']);
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
