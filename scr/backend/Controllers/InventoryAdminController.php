<?php

declare(strict_types=1);

final class InventoryAdminController extends Controller
{
    public function index(): void
    {
        $database = Database::connection();
        $warningDays = $this->expiryWarningDays();
        $underStock = $database->query(
            'SELECT id, name, sku, total_stock, min_stock
             FROM products WHERE deleted_at IS NULL AND total_stock < min_stock
             ORDER BY total_stock, name LIMIT 100'
        )->fetchAll();
        $nearExpiry = $database->prepare(
            'SELECT COUNT(*) FROM product_batches
             WHERE status = \'selling\' AND quantity_remaining > 0
               AND expires_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)'
        );
        $nearExpiry->bindValue(':days', $warningDays, PDO::PARAM_INT);
        $nearExpiry->execute();
        $expiredCount = (int) $database->query(
            'SELECT COUNT(*) FROM product_batches WHERE expires_at < CURDATE() AND quantity_remaining > 0'
        )->fetchColumn();
        $value = (float) $database->query(
            'SELECT COALESCE(SUM(quantity_remaining * average_unit_cost), 0) FROM product_batches'
        )->fetchColumn();
        $recent = $database->query(
            'SELECT m.*, p.name AS product_name, b.batch_code, u.name AS performed_by_name
             FROM inventory_movements m
             JOIN products p ON p.id = m.product_id
             LEFT JOIN product_batches b ON b.id = m.batch_id
             LEFT JOIN users u ON u.id = m.performed_by
             ORDER BY m.id DESC LIMIT 10'
        )->fetchAll();
        $this->adminView('admin/inventory-dashboard', [
            'title' => 'Tổng quan kho',
            'warningDays' => $warningDays,
            'underStock' => $underStock,
            'nearExpiryCount' => (int) $nearExpiry->fetchColumn(),
            'expiredCount' => $expiredCount,
            'inventoryValue' => $value,
            'recentMovements' => $recent,
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function stock(): void
    {
        $rows = $this->stockRows((string) ($_GET['filter'] ?? ''), trim((string) ($_GET['q'] ?? '')), 500);
        $products = Database::connection()->query(
            'SELECT id, name, sku, total_stock, min_stock FROM products WHERE deleted_at IS NULL ORDER BY name'
        )->fetchAll();
        $this->adminView('admin/inventory-stock', [
            'title' => 'Tồn kho',
            'rows' => $rows,
            'products' => $products,
            'query' => trim((string) ($_GET['q'] ?? '')),
            'filter' => (string) ($_GET['filter'] ?? ''),
            'warningDays' => $this->expiryWarningDays(),
            'canManage' => Auth::can('inventory.manage'),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function exportCsv(): void
    {
        $rows = $this->stockRows((string) ($_GET['filter'] ?? ''), trim((string) ($_GET['q'] ?? '')), null);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="inventory-stock.csv"');
        $output = fopen('php://output', 'wb');
        if ($output === false) {
            throw new InventoryException('Không mở được luồng xuất CSV.');
        }
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['Mã lô', 'Sản phẩm', 'SKU', 'Hạn dùng', 'Trạng thái', 'Tồn thực', 'Đang giữ chỗ', 'Tồn khả dụng', 'Giá nhập TB']);
        foreach ($rows as $row) {
            fputcsv($output, [
                $this->csvText($row['batch_code']), $this->csvText($row['product_name']),
                $this->csvText($row['sku']), $this->csvText($row['expires_at']),
                $this->csvText($row['status']), $row['quantity_remaining'], $row['reserved_quantity'],
                $row['available_quantity'], $row['average_unit_cost'],
            ]);
        }
        fclose($output);
        exit;
    }

    public function movements(): void
    {
        $database = Database::connection();
        $filters = $this->movementFilters();
        $conditions = [];
        $parameters = [];
        foreach (['product_id' => 'm.product_id', 'batch_id' => 'm.batch_id', 'movement_type' => 'm.movement_type', 'performed_by' => 'm.performed_by'] as $key => $column) {
            if ($filters[$key] !== '') {
                $conditions[] = $column . ' = :' . $key;
                $parameters[$key] = $filters[$key];
            }
        }
        foreach (['from' => 'm.created_at >=', 'to' => 'm.created_at <'] as $key => $expression) {
            if ($filters[$key] !== '') {
                $conditions[] = $expression . ' :' . $key;
                $parameters[$key] = $key === 'to' ? date('Y-m-d', strtotime($filters[$key] . ' +1 day')) : $filters[$key];
            }
        }
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $countQuery = $database->prepare('SELECT COUNT(*) FROM inventory_movements m ' . $where);
        $countQuery->execute($parameters);
        $total = (int) $countQuery->fetchColumn();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 30;
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $sql = 'SELECT m.*, p.name AS product_name, p.sku, b.batch_code, u.name AS performed_by_name,
                    (SELECT COALESCE(SUM(prior.quantity), 0)
                     FROM inventory_movements prior
                     WHERE prior.batch_id = m.batch_id
                       AND (prior.created_at < m.created_at OR (prior.created_at = m.created_at AND prior.id <= m.id))
                    ) AS balance_after
                FROM inventory_movements m
                JOIN products p ON p.id = m.product_id
                LEFT JOIN product_batches b ON b.id = m.batch_id
                LEFT JOIN users u ON u.id = m.performed_by
                ' . $where . '
                ORDER BY m.created_at DESC, m.id DESC LIMIT :limit OFFSET :offset';
        $query = $database->prepare($sql);
        foreach ($parameters as $key => $value) {
            $query->bindValue(':' . $key, $value);
        }
        $query->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $query->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $query->execute();
        $this->adminView('admin/inventory-movements', [
            'title' => 'Sổ cái biến động',
            'rows' => $query->fetchAll(),
            'filters' => $filters,
            'products' => $database->query('SELECT id, name, sku FROM products ORDER BY name')->fetchAll(),
            'batches' => $database->query('SELECT id, batch_code FROM product_batches ORDER BY id DESC LIMIT 300')->fetchAll(),
            'users' => $database->query('SELECT id, name FROM users ORDER BY name')->fetchAll(),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ]);
    }

    public function receipts(): void
    {
        $database = Database::connection();
        $receipts = $database->query(
            'SELECT r.*, f.name AS farm_name, u.name AS receiver_name,
                    (SELECT COUNT(*) FROM warehouse_receipt_items i WHERE i.receipt_id = r.id) AS item_count
             FROM warehouse_receipts r LEFT JOIN farms f ON f.id = r.supplier_farm_id
             LEFT JOIN users u ON u.id = r.received_by
             ORDER BY r.id DESC LIMIT 100'
        )->fetchAll();
        $this->adminView('admin/inventory-receipts', [
            'title' => 'Phiếu nhập kho',
            'receipts' => $receipts,
            'farms' => $database->query('SELECT id, name FROM farms WHERE status = \'active\' ORDER BY name')->fetchAll(),
            'products' => $database->query('SELECT id, name, sku, farm_id FROM products WHERE deleted_at IS NULL ORDER BY name')->fetchAll(),
            'batches' => $database->query(
                'SELECT id, product_id, batch_code FROM product_batches WHERE status <> \'ended\' ORDER BY id DESC LIMIT 500'
            )->fetchAll(),
            'canManage' => Auth::can('inventory.manage'),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function createReceipt(): void
    {
        $farmId = filter_var($_POST['supplier_farm_id'] ?? null, FILTER_VALIDATE_INT);
        $quantities = $_POST['quantity'] ?? [];
        $productIds = $_POST['product_id'] ?? [];
        $batchIds = $_POST['batch_id'] ?? [];
        $costs = $_POST['unit_cost'] ?? [];
        $harvestDates = $_POST['harvested_at'] ?? [];
        $packedDates = $_POST['packed_at'] ?? [];
        $expiryDates = $_POST['expires_at'] ?? [];
        if (!$farmId || !is_array($quantities) || count($quantities) < 1 || count($quantities) > 50) {
            flash('error', 'Hãy chọn nông trại và nhập từ 1 đến 50 dòng hàng.');
            redirect('/admin/inventory/receipts');
        }

        $database = Database::connection();
        $database->beginTransaction();
        try {
            $farmQuery = $database->prepare('SELECT id, name, region_id FROM farms WHERE id = :id AND status = \'active\' FOR UPDATE');
            $farmQuery->execute(['id' => $farmId]);
            $farm = $farmQuery->fetch();
            if (!$farm) {
                throw new InventoryException('Không tìm thấy nông trại đang hoạt động.');
            }
            $receiptNo = $this->nextDocumentNumber('PN-' . date('ym') . '-', 'receipt:' . date('ym'));
            $database->prepare(
                'INSERT INTO warehouse_receipts (receipt_no, supplier_name, supplier_farm_id, received_at, status, notes)
                 VALUES (:receipt_no, :supplier_name, :farm_id, NOW(), \'draft\', :notes)'
            )->execute([
                'receipt_no' => $receiptNo,
                'supplier_name' => $farm['name'],
                'farm_id' => $farmId,
                'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
            ]);
            $receiptId = (int) $database->lastInsertId();
            $lineCount = count($quantities);
            $savedLines = 0;
            for ($index = 0; $index < $lineCount; $index++) {
                $productValue = trim((string) ($productIds[$index] ?? ''));
                $batchValue = trim((string) ($batchIds[$index] ?? ''));
                $quantityValue = trim((string) ($quantities[$index] ?? ''));
                $costValue = trim((string) ($costs[$index] ?? ''));
                $harvested = trim((string) ($harvestDates[$index] ?? ''));
                $packed = trim((string) ($packedDates[$index] ?? ''));
                $expires = trim((string) ($expiryDates[$index] ?? ''));
                if ($productValue === '' && $batchValue === '' && $quantityValue === ''
                    && in_array($costValue, ['', '0', '0.0', '0.00'], true)
                    && $harvested === '' && $packed === '' && $expires === '') {
                    continue;
                }
                $quantity = filter_var($quantityValue, FILTER_VALIDATE_INT);
                $productId = filter_var($productValue, FILTER_VALIDATE_INT);
                $batchId = $batchValue === '' ? false : filter_var($batchValue, FILTER_VALIDATE_INT);
                $cost = filter_var($costValue === '' ? '0' : $costValue, FILTER_VALIDATE_FLOAT);
                if (!$productId || $quantity === false || $quantity < 1 || $cost === false || $cost < 0) {
                    throw new InventoryException('Kiểm tra sản phẩm, số lượng và giá nhập ở dòng ' . ($index + 1) . '.');
                }
                if ($batchValue !== '' && $batchId === false) {
                    throw new InventoryException('Mã lô ở dòng ' . ($index + 1) . ' không hợp lệ.');
                }
                $productQuery = $database->prepare('SELECT id, sku, farm_id, region_id FROM products WHERE id = :id AND deleted_at IS NULL FOR UPDATE');
                $productQuery->execute(['id' => $productId]);
                $product = $productQuery->fetch();
                if (!$product || (int) $product['farm_id'] !== $farmId || (int) $product['region_id'] !== (int) $farm['region_id']) {
                    throw new InventoryException('Sản phẩm dòng ' . ($index + 1) . ' không thuộc nông trại đã chọn.');
                }

                if ($batchId) {
                    $batchQuery = $database->prepare('SELECT id, product_id, farm_id FROM product_batches WHERE id = :id FOR UPDATE');
                    $batchQuery->execute(['id' => $batchId]);
                    $batch = $batchQuery->fetch();
                    if (!$batch || (int) $batch['product_id'] !== $productId || (int) $batch['farm_id'] !== $farmId) {
                        throw new InventoryException('Lô hàng dòng ' . ($index + 1) . ' không thuộc sản phẩm/nông trại đã chọn.');
                    }
                } else {
                    if (!$this->validDate($harvested) || !$this->validDate($packed) || !$this->validDate($expires)
                        || $harvested > $packed || $packed > $expires) {
                        throw new InventoryException('Ngày của lô mới ở dòng ' . ($index + 1) . ' không hợp lệ.');
                    }
                    $batchCode = BatchCodeGenerator::next($database, (string) $product['sku']);
                    $database->prepare(
                        'INSERT INTO product_batches
                            (product_id, farm_id, batch_code, qr_token, harvested_at, packed_at, expires_at,
                             quantity_received, quantity_remaining, inspection_result, status)
                         VALUES (:product_id, :farm_id, :batch_code, :qr_token, :harvested_at, :packed_at,
                             :expires_at, 0, 0, \'pending\', \'selling\')'
                    )->execute([
                        'product_id' => $productId,
                        'farm_id' => $farmId,
                        'batch_code' => $batchCode,
                        'qr_token' => hash('sha256', random_bytes(32)),
                        'harvested_at' => $harvested,
                        'packed_at' => $packed,
                        'expires_at' => $expires,
                    ]);
                    $batchId = (int) $database->lastInsertId();
                }
                $database->prepare(
                    'INSERT INTO warehouse_receipt_items (receipt_id, batch_id, quantity, unit_cost)
                     VALUES (:receipt_id, :batch_id, :quantity, :unit_cost)'
                )->execute([
                    'receipt_id' => $receiptId,
                    'batch_id' => $batchId,
                    'quantity' => $quantity,
                    'unit_cost' => $cost,
                ]);
                $savedLines++;
            }
            if ($savedLines === 0) {
                throw new InventoryException('Phiếu nhập cần có ít nhất một dòng hàng.');
            }
            AuditLog::record((int) Auth::user()['id'], 'warehouse_receipt_created', 'warehouse_receipt', $receiptId, $receiptNo);
            $database->commit();
            flash('success', 'Đã tạo phiếu nháp ' . $receiptNo . '. Tồn kho chỉ tăng sau khi xác nhận.');
        } catch (InventoryException | InvalidArgumentException $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            flash('error', $exception->getMessage());
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
        redirect('/admin/inventory/receipts');
    }

    public function confirmReceipt(string $id): void
    {
        try {
            (new InventoryService())->confirmReceipt((int) $id, (int) Auth::user()['id']);
            flash('success', 'Đã xác nhận phiếu nhập và cộng tồn kho.');
        } catch (InventoryException | InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('/admin/inventory/receipts');
    }

    public function cancelReceipt(string $id): void
    {
        try {
            (new InventoryService())->cancelReceipt((int) $id, (int) Auth::user()['id'], trim((string) ($_POST['reason'] ?? '')));
            flash('success', 'Đã hủy phiếu nhập bằng các movement điều chỉnh đảo.');
        } catch (InventoryException | InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('/admin/inventory/receipts');
    }

    public function issueOrAdjust(): void
    {
        $batchId = filter_var($_POST['batch_id'] ?? null, FILTER_VALIDATE_INT);
        $quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);
        $action = (string) ($_POST['action'] ?? '');
        $reasonCode = (string) ($_POST['reason_code'] ?? '');
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $reasons = ['damage' => 'Hư hỏng', 'expired' => 'Hết hạn', 'loss' => 'Mất mát', 'sample' => 'Hàng mẫu', 'other' => 'Khác'];
        if (!$batchId || $quantity === false || $quantity < 1 || !in_array($action, ['issue', 'adjust'], true)
            || !isset($reasons[$reasonCode]) || ($reasonCode === 'other' && $notes === '')) {
            flash('error', 'Dữ liệu xuất/điều chỉnh hoặc lý do không hợp lệ.');
            redirect('/admin/inventory/stock');
        }
        $reason = $reasons[$reasonCode] . ($notes !== '' ? ': ' . $notes : '');
        try {
            if ($action === 'issue') {
                (new InventoryService())->issue($batchId, $quantity, $reason, (int) Auth::user()['id'], 'manual_issue', null);
            } else {
                $direction = (string) ($_POST['direction'] ?? 'decrease');
                if (!in_array($direction, ['increase', 'decrease'], true)) {
                    throw new InvalidArgumentException('Chiều điều chỉnh không hợp lệ.');
                }
                (new InventoryService())->adjust(
                    $batchId,
                    $direction === 'increase' ? $quantity : -$quantity,
                    $reason,
                    (int) Auth::user()['id'],
                    'manual_adjustment',
                    null
                );
            }
            AuditLog::record((int) Auth::user()['id'], $action === 'issue' ? 'inventory_issued' : 'inventory_adjusted', 'product_batch', $batchId, $reason . '; quantity=' . $quantity);
            flash('success', $action === 'issue' ? 'Đã ghi nhận xuất kho.' : 'Đã ghi nhận điều chỉnh tồn.');
        } catch (InventoryException | InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('/admin/inventory/stock');
    }

    public function stocktakes(): void
    {
        $database = Database::connection();
        $stocktakes = $database->query(
            'SELECT s.*, u.name AS creator_name,
                    (SELECT COUNT(*) FROM stocktake_items i WHERE i.stocktake_id = s.id) AS item_count
             FROM stocktakes s LEFT JOIN users u ON u.id = s.created_by ORDER BY s.id DESC LIMIT 100'
        )->fetchAll();
        $selectedId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        $items = [];
        if ($selectedId) {
            $query = $database->prepare(
                'SELECT i.*, b.batch_code, p.name AS product_name, p.sku
                 FROM stocktake_items i JOIN product_batches b ON b.id = i.batch_id
                 JOIN products p ON p.id = b.product_id WHERE i.stocktake_id = :id ORDER BY p.name, b.expires_at'
            );
            $query->execute(['id' => $selectedId]);
            $items = $query->fetchAll();
        }
        $this->adminView('admin/inventory-stocktakes', [
            'title' => 'Kiểm kê kho',
            'stocktakes' => $stocktakes,
            'items' => $items,
            'selectedId' => (int) $selectedId,
            'canManage' => Auth::can('inventory.manage'),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function createStocktake(): void
    {
        $database = Database::connection();
        $database->beginTransaction();
        try {
            $period = date('ym');
            $number = $this->nextDocumentNumber('KK-' . $period . '-', 'stocktake:' . $period);
            $database->prepare('INSERT INTO stocktakes (stocktake_no, notes, created_by) VALUES (:number, :notes, :user_id)')
                ->execute([
                    'number' => $number,
                    'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
                    'user_id' => (int) Auth::user()['id'],
                ]);
            $stocktakeId = (int) $database->lastInsertId();
            $database->prepare(
                'INSERT INTO stocktake_items (stocktake_id, batch_id, system_quantity)
                 SELECT :stocktake_id, id, quantity_remaining FROM product_batches'
            )->execute(['stocktake_id' => $stocktakeId]);
            AuditLog::record((int) Auth::user()['id'], 'stocktake_created', 'stocktake', $stocktakeId, $number);
            $database->commit();
            flash('success', 'Đã tạo đợt kiểm kê ' . $number . '.');
            redirect('/admin/inventory/stocktakes?id=' . $stocktakeId);
        } catch (InventoryException | InvalidArgumentException $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            flash('error', $exception->getMessage());
            redirect('/admin/inventory/stocktakes');
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    public function updateStocktakeCounts(string $id): void
    {
        $counts = $_POST['counted_quantity'] ?? [];
        if (!is_array($counts)) {
            flash('error', 'Danh sách kiểm kê không hợp lệ.');
            redirect('/admin/inventory/stocktakes?id=' . (int) $id);
        }
        $database = Database::connection();
        $database->beginTransaction();
        try {
            $check = $database->prepare('SELECT status FROM stocktakes WHERE id = :id FOR UPDATE');
            $check->execute(['id' => (int) $id]);
            if ($check->fetchColumn() !== 'draft') {
                throw new InventoryException('Đợt kiểm kê đã chốt hoặc không tồn tại.');
            }
            $update = $database->prepare(
                'UPDATE stocktake_items SET counted_quantity = :quantity
                 WHERE stocktake_id = :stocktake_id AND batch_id = :batch_id'
            );
            foreach ($counts as $batchId => $count) {
                if ($count === '') {
                    continue;
                }
                $quantity = filter_var($count, FILTER_VALIDATE_INT);
                if ($quantity === false || $quantity < 0) {
                    throw new InventoryException('Số đếm phải là số nguyên không âm.');
                }
                $update->execute(['quantity' => $quantity, 'stocktake_id' => (int) $id, 'batch_id' => (int) $batchId]);
            }
            AuditLog::record((int) Auth::user()['id'], 'stocktake_counts_saved', 'stocktake', (int) $id);
            $database->commit();
            flash('success', 'Đã lưu số đếm thực tế.');
        } catch (InventoryException | InvalidArgumentException $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            flash('error', $exception->getMessage());
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
        redirect('/admin/inventory/stocktakes?id=' . (int) $id);
    }

    public function confirmStocktake(string $id): void
    {
        try {
            (new InventoryService())->confirmStocktake((int) $id, (int) Auth::user()['id']);
            flash('success', 'Đã xác nhận kiểm kê và tạo movement cho các chênh lệch.');
        } catch (InventoryException | InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('/admin/inventory/stocktakes?id=' . (int) $id);
    }

    public function updateSettings(): void
    {
        $days = filter_var($_POST['expiry_warning_days'] ?? null, FILTER_VALIDATE_INT);
        if ($days === false || $days < 1 || $days > 365) {
            flash('error', 'Số ngày cảnh báo phải từ 1 đến 365.');
            redirect('/admin/inventory');
        }
        Database::connection()->prepare(
            'INSERT INTO settings (setting_key, setting_value, description)
             VALUES (\'expiry_warning_days\', :value, \'Số ngày cảnh báo lô sắp hết hạn\')
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        )->execute(['value' => (string) $days]);
        AuditLog::record((int) Auth::user()['id'], 'inventory_setting_updated', 'setting', null, 'expiry_warning_days=' . $days);
        flash('success', 'Đã lưu ngưỡng cảnh báo hết hạn.');
        redirect('/admin/inventory');
    }

    public function endExpiredBatches(): void
    {
        try {
            $count = (new InventoryService())->expireBatches();
            flash('success', 'Đã chuyển ' . $count . ' lô quá hạn sang trạng thái đã hết.');
        } catch (InventoryException | InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('/admin/inventory');
    }

    public function testIssue(): Response
    {
        $tokenPath = BACKEND_PATH . '/storage/logs/inventory-test.token.log';
        $providedToken = (string) ($_SERVER['HTTP_X_INVENTORY_TEST_TOKEN'] ?? '');
        $testConfig = is_file($tokenPath) ? json_decode((string) file_get_contents($tokenPath), true) : null;
        $storedToken = is_array($testConfig) ? (string) ($testConfig['token'] ?? '') : '';
        if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
            || $providedToken === '' || $storedToken === '' || !hash_equals($storedToken, $providedToken)) {
            return new Response('{"error":"forbidden"}', 403, ['Content-Type' => 'application/json; charset=UTF-8']);
        }
        $databaseConfig = $testConfig['database'] ?? [];
        if (!in_array(strtolower((string) ($databaseConfig['host'] ?? '')), ['localhost', '127.0.0.1', '::1'], true)
            || !str_ends_with((string) ($databaseConfig['name'] ?? ''), '_test')) {
            return new Response('{"error":"test_database_only"}', 403, ['Content-Type' => 'application/json; charset=UTF-8']);
        }
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $databaseConfig['host'],
            (int) $databaseConfig['port'],
            $databaseConfig['name']
        );
        $database = new PDO($dsn, $databaseConfig['username'], $databaseConfig['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $batchId = filter_var($_POST['batch_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$batchId) {
            return new Response('{"error":"invalid_batch"}', 400, ['Content-Type' => 'application/json; charset=UTF-8']);
        }
        $batchQuery = $database->prepare('SELECT batch_code FROM product_batches WHERE id = :id');
        $batchQuery->execute(['id' => $batchId]);
        $batchCode = $batchQuery->fetchColumn();
        if (!is_string($batchCode) || !str_starts_with($batchCode, 'NS-TEST-')) {
            return new Response('{"error":"test_batch_only"}', 403, ['Content-Type' => 'application/json; charset=UTF-8']);
        }
        try {
            (new InventoryService($database))->issue((int) $batchId, 1, 'Concurrent inventory test', null, 'inventory_test', null);
            return new Response('{"result":"issued"}', 200, ['Content-Type' => 'application/json; charset=UTF-8']);
        } catch (InventoryException $exception) {
            if (!str_starts_with($exception->getMessage(), 'Lô không đủ tồn khả dụng')) {
                throw $exception;
            }
            return new Response('{"result":"unavailable"}', 409, ['Content-Type' => 'application/json; charset=UTF-8']);
        }
    }

    private function stockRows(string $filter, string $search, ?int $limit): array
    {
        (new InventoryService())->expireReservations();
        $conditions = [];
        $parameters = [];
        $validFilters = ['low', 'near_expiry', 'expired', 'out_of_stock'];
        if (in_array($filter, $validFilters, true)) {
            if ($filter === 'near_expiry') {
                $conditions[] = 'b.expires_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :warning_days DAY)';
                $parameters['warning_days'] = $this->expiryWarningDays();
            } elseif ($filter === 'expired') {
                $conditions[] = 'b.expires_at < CURDATE()';
            } elseif ($filter === 'out_of_stock') {
                $conditions[] = 'b.quantity_remaining = 0';
            } else {
                $conditions[] = 'p.total_stock < p.min_stock';
            }
            $filter = $filter === 'low' ? 'low' : $filter;
        } else {
            $filter = '';
        }
        if ($search !== '') {
            $conditions[] = '(p.name LIKE :name_search OR p.sku LIKE :sku_search OR b.batch_code LIKE :batch_search)';
            $parameters['name_search'] = '%' . $search . '%';
            $parameters['sku_search'] = '%' . $search . '%';
            $parameters['batch_search'] = '%' . $search . '%';
        }
        $sql = 'SELECT b.id AS batch_id, b.batch_code, b.expires_at, b.status, b.quantity_remaining,
                       b.average_unit_cost, p.id AS product_id, p.name AS product_name, p.sku, p.total_stock, p.min_stock,
                       COALESCE(r.quantity, 0) AS reserved_quantity,
                       GREATEST(0, b.quantity_remaining - COALESCE(r.quantity, 0)) AS available_quantity
                FROM product_batches b
                JOIN products p ON p.id = b.product_id
                LEFT JOIN (
                    SELECT batch_id, SUM(quantity) AS quantity
                    FROM stock_reservations
                    WHERE status = \'reserved\' AND expires_at > NOW()
                    GROUP BY batch_id
                ) r ON r.batch_id = b.id'
                . ($conditions ? ' WHERE ' . implode(' AND ', $conditions) : '')
                . ' ORDER BY b.expires_at IS NULL, b.expires_at, p.name, b.id';
        if ($limit !== null) {
            $sql .= ' LIMIT :limit';
        }
        $query = Database::connection()->prepare($sql);
        foreach ($parameters as $key => $value) {
            $query->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        if ($limit !== null) {
            $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        }
        $query->execute();
        return $query->fetchAll();
    }

    private function movementFilters(): array
    {
        $filters = [];
        foreach (['product_id', 'batch_id', 'movement_type', 'performed_by', 'from', 'to'] as $key) {
            $value = trim((string) ($_GET[$key] ?? ''));
            $filters[$key] = $value;
        }
        if ($filters['from'] !== '' && !$this->validDate($filters['from'])) {
            $filters['from'] = '';
        }
        if ($filters['to'] !== '' && !$this->validDate($filters['to'])) {
            $filters['to'] = '';
        }
        return $filters;
    }

    private function expiryWarningDays(): int
    {
        $value = Database::connection()->query(
            'SELECT setting_value FROM settings WHERE setting_key = \'expiry_warning_days\''
        )->fetchColumn();
        $days = filter_var($value, FILTER_VALIDATE_INT);
        return $days !== false && $days >= 1 && $days <= 365 ? $days : 7;
    }

    private function csvText(mixed $value): string
    {
        $text = (string) $value;
        return preg_match('/^[\t\r\n ]*[=+\-@]/', $text) === 1 ? "'" . $text : $text;
    }

    private function nextDocumentNumber(string $prefix, string $sequenceKey): string
    {
        $database = Database::connection();
        $database->prepare(
            'INSERT IGNORE INTO document_sequences (sequence_key, next_value) VALUES (:key, 1)'
        )->execute(['key' => $sequenceKey]);
        $query = $database->prepare('SELECT next_value FROM document_sequences WHERE sequence_key = :key FOR UPDATE');
        $query->execute(['key' => $sequenceKey]);
        $number = (int) $query->fetchColumn();
        if ($number < 1) {
            throw new InventoryException('Không tạo được số chứng từ.');
        }
        $database->prepare('UPDATE document_sequences SET next_value = :next WHERE sequence_key = :key')
            ->execute(['next' => $number + 1, 'key' => $sequenceKey]);
        return $prefix . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }

    private function validDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function adminView(string $template, array $data): void
    {
        $data['menu'] = $this->menu();
        $data['success'] ??= flash('success');
        $data['error'] ??= flash('error');
        $this->view($template, $data, 'admin');
    }

    private function menu(): array
    {
        $items = [
            ['permission' => 'dashboard.view', 'label' => 'Bảng điều khiển', 'url' => '/admin'],
            ['permission' => 'inventory.view', 'label' => 'Kho hàng', 'url' => '/admin/inventory'],
            ['permission' => 'inventory.view', 'label' => 'Tồn kho', 'url' => '/admin/inventory/stock'],
            ['permission' => 'inventory.manage', 'label' => 'Phiếu nhập', 'url' => '/admin/inventory/receipts'],
            ['permission' => 'inventory.view', 'label' => 'Sổ cái kho', 'url' => '/admin/inventory/movements'],
            ['permission' => 'inventory.manage', 'label' => 'Kiểm kê', 'url' => '/admin/inventory/stocktakes'],
            ['permission' => 'batches.view', 'label' => 'Lô hàng và truy xuất', 'url' => '/admin/batches'],
            ['permission' => 'products.view', 'label' => 'Sản phẩm', 'url' => '/admin/products'],
        ];
        return array_values(array_filter($items, static fn (array $item): bool => Auth::can($item['permission'])));
    }
}
