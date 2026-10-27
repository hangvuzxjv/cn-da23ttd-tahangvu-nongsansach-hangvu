<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('ROOT_PATH', $root . DIRECTORY_SEPARATOR . 'scr');
define('BACKEND_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'backend');
define('FRONTEND_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'frontend');
define('BASE_URL', '');

$composerAutoload = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
}
require BACKEND_PATH . '/Helpers/helpers.php';

spl_autoload_register(static function (string $class): void {
    foreach (['Core', 'Controllers', 'Mailers'] as $directory) {
        $path = BACKEND_PATH . DIRECTORY_SEPARATOR . $directory . DIRECTORY_SEPARATOR . $class . '.php';
        if (is_file($path)) {
            require $path;
            return;
        }
    }
});

function inventoryTestDatabaseConfig(): array
{
    $applicationConfig = require BACKEND_PATH . '/config/config.php';
    $database = $applicationConfig['database'];
    foreach (['host', 'name', 'username', 'password', 'port'] as $key) {
        $environmentKey = 'INVENTORY_TEST_DB_' . strtoupper($key);
        $value = getenv($environmentKey);
        if ($value !== false) {
            $database[$key] = $key === 'port'
                ? (int) $value
                : ($key === 'password' && $value === '__EMPTY__' ? '' : $value);
        }
    }
    if (!in_array(strtolower((string) $database['host']), ['localhost', '127.0.0.1', '::1'], true)
        || !str_ends_with((string) $database['name'], '_test')) {
        throw new RuntimeException('Inventory tests require a loopback MySQL host and a database name ending in _test.');
    }
    return $database;
}

function inventoryTestDatabase(): PDO
{
    $database = inventoryTestDatabaseConfig();
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $database['host'],
        $database['port'],
        $database['name']
    );
    return new PDO($dsn, $database['username'], $database['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
