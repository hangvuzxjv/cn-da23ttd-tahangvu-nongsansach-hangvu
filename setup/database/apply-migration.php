<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || empty($argv[1])) {
    exit("Cách dùng: php setup/database/apply-migration.php setup/database/migrations/20261106_product_metadata.sql\n");
}

$root = dirname(__DIR__, 2);
$file = $root . DIRECTORY_SEPARATOR . $argv[1];
if (!is_file($file)) {
    fwrite(STDERR, "Không tìm thấy file migration: {$argv[1]}\n");
    exit(1);
}
$config = require $root . '/scr/backend/config/config.php';
$db = $config['database'];
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']),
    $db['username'],
    $db['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true]
);

$statements = preg_split('/;\s*(?:\r?\n|$)/', trim((string) file_get_contents($file)));
foreach ($statements as $statement) {
    $statement = trim($statement);
    if ($statement !== '' && !str_starts_with($statement, '--')) {
        $pdo->exec($statement);
    }
}
echo "Đã chạy migration {$argv[1]} trên database {$db['name']}.\n";
