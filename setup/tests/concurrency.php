<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

function createConcurrencyTestBatch(PDO $database): array
{
    $product = $database->query(
        'SELECT id, farm_id FROM products WHERE status = \'active\' AND deleted_at IS NULL AND farm_id IS NOT NULL ORDER BY id LIMIT 1'
    )->fetch();
    if (!$product) {
        throw new RuntimeException('No active product with a farm is available for the test fixture.');
    }
    $batchCode = 'NS-TEST-' . date('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $database->prepare(
        'INSERT INTO product_batches
            (product_id, farm_id, batch_code, qr_token, harvested_at, packed_at, expires_at,
             quantity_received, quantity_remaining, inspection_result, status)
         VALUES
            (:product_id, :farm_id, :batch_code, :qr_token, CURDATE(), CURDATE(),
             DATE_ADD(CURDATE(), INTERVAL 90 DAY), 0, 0, \'passed\', \'selling\')'
    )->execute([
        'product_id' => $product['id'],
        'farm_id' => $product['farm_id'],
        'batch_code' => $batchCode,
        'qr_token' => hash('sha256', random_bytes(32)),
    ]);
    $batchId = (int) $database->lastInsertId();
    (new InventoryService($database))->receive($batchId, 20, 'Concurrency test starting stock');
    return [$batchId, $batchCode];
}

if (($argv[1] ?? '') === '--worker') {
    try {
        (new InventoryService(inventoryTestDatabase()))->issue(
            (int) ($argv[2] ?? 0),
            1,
            'Concurrent inventory test',
            null,
            'inventory_test',
            null
        );
        exit(0);
    } catch (InventoryException $exception) {
        if (str_starts_with($exception->getMessage(), 'Lô không đủ tồn khả dụng')) {
            exit(3);
        }
        fwrite(STDERR, $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}

if (($argv[1] ?? '') === '--cli') {
    $database = inventoryTestDatabase();
    [$batchId, $batchCode] = createConcurrencyTestBatch($database);
    $issued = 0;
    $unavailable = 0;
    $failures = [];
    foreach (array_chunk(range(1, 200), 40) as $wave) {
        $workers = [];
        foreach ($wave as $_) {
            $pipes = [];
            $process = proc_open([PHP_BINARY, __FILE__, '--worker', (string) $batchId], [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $pipes);
            if (!is_resource($process)) {
                throw new RuntimeException('Unable to start a PHP inventory worker.');
            }
            fclose($pipes[0]);
            $workers[] = [$process, $pipes];
        }
        foreach ($workers as [$process, $pipes]) {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $status = proc_close($process);
            if ($status === 0) {
                $issued++;
            } elseif ($status === 3) {
                $unavailable++;
            } else {
                $failures[] = trim($stderr . $stdout) ?: 'Worker exit code ' . $status;
            }
        }
    }
    $query = $database->prepare(
        'SELECT b.quantity_remaining, COALESCE(SUM(m.quantity), 0) AS movement_total
         FROM product_batches b LEFT JOIN inventory_movements m ON m.batch_id = b.id
         WHERE b.id = :id GROUP BY b.id, b.quantity_remaining'
    );
    $query->execute(['id' => $batchId]);
    $result = $query->fetch();
    printf(
        "Batch %s: requests=200, issued=%d, unavailable=%d, remaining=%d, ledger_sum=%d\n",
        $batchCode,
        $issued,
        $unavailable,
        (int) $result['quantity_remaining'],
        (int) $result['movement_total']
    );
    if ($issued !== 20 || $unavailable !== 180 || (int) $result['quantity_remaining'] !== 0
        || (int) $result['movement_total'] !== 0 || $failures) {
        fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
        exit(1);
    }
    echo "PASS: exactly 20 units issued; no negative stock; movement sum matches lot balance.\n";
    exit(0);
}

if (!extension_loaded('curl')) {
    fwrite(STDERR, "PHP cURL extension is required.\n");
    exit(2);
}

$baseUrl = rtrim((string) getenv('INVENTORY_TEST_BASE_URL'), '/');
$urlParts = parse_url($baseUrl);
if (!$urlParts || !in_array(strtolower((string) ($urlParts['host'] ?? '')), ['localhost', '127.0.0.1', '[::1]'], true)
    || !in_array(strtolower((string) ($urlParts['scheme'] ?? '')), ['http', 'https'], true)) {
    fwrite(STDERR, "Set INVENTORY_TEST_BASE_URL to the local application URL, e.g. http://localhost.\n");
    exit(2);
}

$databaseConfig = inventoryTestDatabaseConfig();
$database = inventoryTestDatabase();
[$batchId, $batchCode] = createConcurrencyTestBatch($database);
$tokenPath = BACKEND_PATH . '/storage/logs/inventory-test.token.log';
$token = bin2hex(random_bytes(32));
$tokenContent = json_encode(['token' => $token, 'database' => $databaseConfig], JSON_THROW_ON_ERROR);
if (file_put_contents($tokenPath, $tokenContent, LOCK_EX) === false) {
    fwrite(STDERR, "Unable to write the temporary test token.\n");
    exit(2);
}

$multi = curl_multi_init();
$handles = [];
$issued = 0;
$unavailable = 0;
$failures = [];
try {
    $workerUrl = $baseUrl . '/_dev/inventory-test/issue';
    for ($index = 0; $index < 200; $index++) {
        $handle = curl_init($workerUrl);
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['batch_id' => $batchId]),
            CURLOPT_HTTPHEADER => ['X-Inventory-Test-Token: ' . $token, 'Connection: keep-alive'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 45,
        ]);
        curl_multi_add_handle($multi, $handle);
        $handles[] = $handle;
    }
    do {
        $multiResult = curl_multi_exec($multi, $running);
        if ($multiResult !== CURLM_OK) {
            throw new RuntimeException('cURL multi failed: ' . curl_multi_strerror($multiResult));
        }
        if ($running > 0) {
            curl_multi_select($multi, 1.0);
        }
    } while ($running > 0);

    foreach ($handles as $handle) {
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $body = (string) curl_multi_getcontent($handle);
        if ($status === 200) {
            $issued++;
        } elseif ($status === 409) {
            $unavailable++;
        } else {
            $failures[] = 'HTTP ' . $status . ': ' . substr($body, 0, 200);
        }
        curl_multi_remove_handle($multi, $handle);
        curl_close($handle);
    }
    $handles = [];

    $batchQuery = $database->prepare('SELECT quantity_remaining FROM product_batches WHERE id = :id');
    $batchQuery->execute(['id' => $batchId]);
    $remaining = (int) $batchQuery->fetchColumn();
    $ledgerQuery = $database->prepare(
        'SELECT COALESCE(SUM(quantity), 0) FROM inventory_movements WHERE batch_id = :batch_id'
    );
    $ledgerQuery->execute(['batch_id' => $batchId]);
    $ledgerTotal = (int) $ledgerQuery->fetchColumn();
    $negativeQuery = $database->prepare('SELECT COUNT(*) FROM product_batches WHERE id = :id AND quantity_remaining < 0');
    $negativeQuery->execute(['id' => $batchId]);
    $negative = (int) $negativeQuery->fetchColumn();

    printf("Batch %s: requests=200, issued=%d, unavailable=%d, remaining=%d, ledger_sum=%d\n", $batchCode, $issued, $unavailable, $remaining, $ledgerTotal);
    if ($failures) {
        fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    }
    if ($issued !== 20 || $unavailable !== 180 || $remaining !== 0 || $ledgerTotal !== 0 || $negative !== 0 || $failures) {
        exit(1);
    }
    echo "PASS: exactly 20 units issued; no negative stock; movement sum matches lot balance.\n";
} finally {
    foreach ($handles as $handle) {
        curl_multi_remove_handle($multi, $handle);
        curl_close($handle);
    }
    curl_multi_close($multi);
    if (is_file($tokenPath)) {
        unlink($tokenPath);
    }
}
