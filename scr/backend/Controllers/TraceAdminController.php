<?php

declare(strict_types=1);

final class TraceAdminController extends Controller
{
    private const EVENT_TYPES = [
        'planting' => 'Gieo trồng',
        'care' => 'Chăm sóc',
        'harvest' => 'Thu hoạch',
        'processing' => 'Sơ chế',
        'packaging' => 'Đóng gói',
        'transport' => 'Vận chuyển',
    ];

    public function farms(): void
    {
        $database = Database::connection();
        $farms = $database->query(
            'SELECT f.*, r.name AS region_name,
                    (SELECT COUNT(*) FROM product_batches b WHERE b.farm_id = f.id) AS batch_count,
                    (SELECT COUNT(*) FROM products p WHERE p.farm_id = f.id) AS product_count
             FROM farms f JOIN regions r ON r.id = f.region_id ORDER BY f.name'
        )->fetchAll();
        $regions = $database->query('SELECT id, name FROM regions WHERE level = 2 AND is_active = 1 ORDER BY name')->fetchAll();
        $this->adminView('admin/farms', [
            'title' => 'Nông trại',
            'farms' => $farms,
            'regions' => $regions,
            'menu' => $this->menu(),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function storeFarm(): void
    {
        try {
            $input = $this->farmInput();
        } catch (RuntimeException $exception) {
            flash('error', $exception->getMessage());
            redirect('/admin/farms');
        }
        $uploaded = [];
        try {
            $uploaded['image'] = $this->storeImage('image');
            $uploaded['certificate'] = $this->storeImage('certificate_image');
            $database = Database::connection();
            $database->beginTransaction();
            $database->prepare(
                'INSERT INTO farms (name, slug, region_id, address, contact_name, phone, certifications,
                    certification_number, certification_expires_at, certificate_image_url, image_url,
                    latitude, longitude, description, status)
                 VALUES (:name, :slug, :region_id, :address, :contact_name, :phone, :certifications,
                    :certification_number, :certification_expires_at, :certificate_image_url, :image_url,
                    :latitude, :longitude, :description, :status)'
            )->execute($input + [
                'slug' => $this->uniqueFarmSlug($input['name']),
                'certificate_image_url' => $uploaded['certificate']['url'] ?? null,
                'image_url' => $uploaded['image']['url'] ?? null,
            ]);
            $farmId = (int) $database->lastInsertId();
            AuditLog::record((int) Auth::user()['id'], 'farm_created', 'farm', $farmId, $input['name']);
            $database->commit();
        } catch (RuntimeException $exception) {
            if (isset($database) && $database->inTransaction()) {
                $database->rollBack();
            }
            $this->removeFiles($uploaded);
            flash('error', $exception->getMessage());
            redirect('/admin/farms');
        } catch (Throwable $exception) {
            if (isset($database) && $database->inTransaction()) {
                $database->rollBack();
            }
            $this->removeFiles($uploaded);
            throw $exception;
        }
        flash('success', 'Đã thêm nông trại.');
        redirect('/admin/farms');
    }

    public function updateFarm(string $id): void
    {
        $farmId = (int) $id;
        try {
            $input = $this->farmInput();
        } catch (RuntimeException $exception) {
            flash('error', $exception->getMessage());
            redirect('/admin/farms');
        }
        $database = Database::connection();
        $beforeQuery = $database->prepare('SELECT * FROM farms WHERE id = :id');
        $beforeQuery->execute(['id' => $farmId]);
        $before = $beforeQuery->fetch();
        if (!$before) {
            flash('error', 'Không tìm thấy nông trại.');
            redirect('/admin/farms');
        }
        $uploaded = [];
        try {
            $uploaded['image'] = $this->storeImage('image');
            $uploaded['certificate'] = $this->storeImage('certificate_image');
            $database->beginTransaction();
            $database->prepare(
                'UPDATE farms SET name = :name, region_id = :region_id, address = :address,
                    contact_name = :contact_name, phone = :phone, certifications = :certifications,
                    certification_number = :certification_number, certification_expires_at = :certification_expires_at,
                    certificate_image_url = :certificate_image_url, image_url = :image_url,
                    latitude = :latitude, longitude = :longitude, description = :description, status = :status
                 WHERE id = :id'
            )->execute($input + [
                'certificate_image_url' => $uploaded['certificate']['url']
                    ?? (isset($_POST['remove_certificate_image']) ? null : $before['certificate_image_url']),
                'image_url' => $uploaded['image']['url']
                    ?? (isset($_POST['remove_image']) ? null : $before['image_url']),
                'id' => $farmId,
            ]);
            AuditLog::record((int) Auth::user()['id'], 'farm_updated', 'farm', $farmId, $input['name']);
            $database->commit();
        } catch (RuntimeException $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            $this->removeFiles($uploaded);
            flash('error', $exception->getMessage());
            redirect('/admin/farms');
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            $this->removeFiles($uploaded);
            throw $exception;
        }
        foreach ([
            'image_url' => $uploaded['image']['url'] ?? (isset($_POST['remove_image']) ? null : $before['image_url']),
            'certificate_image_url' => $uploaded['certificate']['url'] ?? (isset($_POST['remove_certificate_image']) ? null : $before['certificate_image_url']),
        ] as $column => $newUrl) {
            if ($before[$column] && $before[$column] !== $newUrl) {
                $this->removeUrl($before[$column]);
            }
        }
        flash('success', 'Đã cập nhật nông trại.');
        redirect('/admin/farms');
    }

    public function deleteFarm(string $id): void
    {
        $farmId = (int) $id;
        $database = Database::connection();
        $check = $database->prepare(
            'SELECT (SELECT COUNT(*) FROM product_batches WHERE farm_id = :batch_farm) +
                    (SELECT COUNT(*) FROM products WHERE farm_id = :product_farm)'
        );
        $check->execute(['batch_farm' => $farmId, 'product_farm' => $farmId]);
        if ((int) $check->fetchColumn() > 0) {
            flash('error', 'Không thể xóa nông trại khi còn sản phẩm hoặc lô hàng liên kết.');
            redirect('/admin/farms');
        }
        $farmQuery = $database->prepare('SELECT image_url, certificate_image_url, name FROM farms WHERE id = :id');
        $farmQuery->execute(['id' => $farmId]);
        $farm = $farmQuery->fetch();
        if (!$farm) {
            flash('error', 'Không tìm thấy nông trại.');
            redirect('/admin/farms');
        }
        $database->beginTransaction();
        try {
            $database->prepare('DELETE FROM farms WHERE id = :id')->execute(['id' => $farmId]);
            AuditLog::record((int) Auth::user()['id'], 'farm_deleted', 'farm', $farmId, $farm['name']);
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
        $this->removeUrl($farm['image_url']);
        $this->removeUrl($farm['certificate_image_url']);
        flash('success', 'Đã xóa nông trại.');
        redirect('/admin/farms');
    }

    public function batches(): void
    {
        $database = Database::connection();
        $batches = $database->query(
            'SELECT b.id, b.batch_code, b.harvested_at, b.packed_at, b.expires_at,
                    b.quantity_received, b.quantity_remaining, b.inspection_result, b.status,
                    p.name AS product_name, f.name AS farm_name,
                    (SELECT COUNT(*) FROM trace_scans s WHERE s.batch_id = b.id) AS scan_count
             FROM product_batches b JOIN products p ON p.id = b.product_id
             LEFT JOIN farms f ON f.id = b.farm_id ORDER BY b.created_at DESC, b.id DESC LIMIT 300'
        )->fetchAll();
        $products = $database->query("SELECT id, name, sku, farm_id FROM products WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
        $farms = $database->query("SELECT id, name, region_id FROM farms WHERE status = 'active' ORDER BY name")->fetchAll();
        $this->adminView('admin/batches', [
            'title' => 'Quản lý lô hàng',
            'batches' => $batches,
            'products' => $products,
            'farms' => $farms,
            'menu' => $this->menu(),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function storeBatch(): void
    {
        $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
        $farmId = filter_var($_POST['farm_id'] ?? null, FILTER_VALIDATE_INT);
        $harvested = trim((string) ($_POST['harvested_at'] ?? ''));
        $packed = trim((string) ($_POST['packed_at'] ?? ''));
        $expires = trim((string) ($_POST['expires_at'] ?? ''));
        $received = filter_var($_POST['quantity_received'] ?? null, FILTER_VALIDATE_INT);
        $remaining = filter_var($_POST['quantity_remaining'] ?? null, FILTER_VALIDATE_INT);
        $status = (string) ($_POST['status'] ?? 'selling');
        $inspection = (string) ($_POST['inspection_result'] ?? 'pending');
        $reason = trim((string) ($_POST['recall_reason'] ?? ''));
        if (!$productId || !$farmId || !$this->validDate($harvested) || !$this->validDate($packed) || !$this->validDate($expires)
            || $harvested > $packed || $packed > $expires             || !$received || $received < 1 || $received > 4294967295 || $remaining === false
            || $remaining < 0 || $remaining > 4294967295 || $remaining > $received || !in_array($status, ['selling', 'suspended', 'recalled', 'ended'], true)
            || !in_array($inspection, ['pending', 'passed', 'failed'], true) || ($status === 'recalled' && $reason === '')) {
            flash('error', 'Dữ liệu lô không hợp lệ. Kiểm tra ngày thu hoạch ≤ đóng gói ≤ hết hạn, số lượng, trạng thái và lý do thu hồi.');
            redirect('/admin/batches');
        }

        $database = Database::connection();
        $database->beginTransaction();
        try {
            $productQuery = $database->prepare('SELECT sku, farm_id, region_id FROM products WHERE id = :id AND deleted_at IS NULL FOR UPDATE');
            $productQuery->execute(['id' => $productId]);
            $product = $productQuery->fetch();
            $farmQuery = $database->prepare('SELECT region_id FROM farms WHERE id = :id AND status = \'active\'');
            $farmQuery->execute(['id' => $farmId]);
            $farmRegionId = $farmQuery->fetchColumn();
            if (!$product || $farmRegionId === false || (int) $product['region_id'] !== (int) $farmRegionId) {
                throw new RuntimeException('Hãy chọn sản phẩm và nông trại cùng vùng miền đang hoạt động.');
            }
            $prefix = 'NS-' . date('ym') . '-' . strtoupper($product['sku']) . '-';
            $sequenceQuery = $database->prepare(
                'SELECT MAX(CAST(SUBSTRING(batch_code, CHAR_LENGTH(:prefix_length) + 1) AS UNSIGNED))
                 FROM product_batches
                 WHERE LEFT(batch_code, CHAR_LENGTH(:prefix_for_length)) = :prefix_for_value'
            );
            $sequenceQuery->execute([
                'prefix_length' => $prefix,
                'prefix_for_length' => $prefix,
                'prefix_for_value' => $prefix,
            ]);
            $batchCode = $prefix . str_pad((string) ((int) $sequenceQuery->fetchColumn() + 1), 3, '0', STR_PAD_LEFT);
            $database->prepare(
                'INSERT INTO product_batches (product_id, farm_id, batch_code, qr_token, harvested_at, packed_at,
                    expires_at, quantity_received, quantity_remaining, inspection_result, inspection_notes,
                    status, recall_reason)
                 VALUES (:product_id, :farm_id, :batch_code, :qr_token, :harvested_at, :packed_at, :expires_at,
                    :quantity_received, :quantity_remaining, :inspection_result, :inspection_notes, :status, :recall_reason)'
            )->execute([
                'product_id' => $productId,
                'farm_id' => $farmId,
                'batch_code' => $batchCode,
                'qr_token' => hash('sha256', random_bytes(32)),
                'harvested_at' => $harvested,
                'packed_at' => $packed,
                'expires_at' => $expires,
                'quantity_received' => $received,
                'quantity_remaining' => $remaining,
                'inspection_result' => $inspection,
                'inspection_notes' => trim((string) ($_POST['inspection_notes'] ?? '')) ?: null,
                'status' => $status,
                'recall_reason' => $status === 'recalled' ? $reason : null,
            ]);
            $batchId = (int) $database->lastInsertId();
            AuditLog::record((int) Auth::user()['id'], $status === 'recalled' ? 'batch_recalled' : 'batch_created', 'product_batch', $batchId, $batchCode . ($status === 'recalled' ? ': ' . $reason : ''));
            $database->commit();
        } catch (RuntimeException $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            flash('error', $exception->getMessage());
            redirect('/admin/batches');
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
        flash('success', 'Đã tạo lô ' . $batchCode . '. Mã lô được khóa và không thể sửa.');
        redirect('/admin/batches/' . $batchId);
    }

    public function showBatch(string $id): void
    {
        $database = Database::connection();
        $query = $database->prepare(
            'SELECT b.*, p.name AS product_name, p.sku, p.region_id, f.name AS farm_name
             FROM product_batches b JOIN products p ON p.id = b.product_id LEFT JOIN farms f ON f.id = b.farm_id
             WHERE b.id = :id'
        );
        $query->execute(['id' => (int) $id]);
        $batch = $query->fetch();
        if (!$batch) {
            http_response_code(404);
            View::renderError(404);
            return;
        }
        $eventQuery = $database->prepare('SELECT * FROM trace_events WHERE batch_id = :id ORDER BY sort_order, event_at, id');
        $eventQuery->execute(['id' => (int) $id]);
        $farmQuery = $database->prepare('SELECT id, name, status FROM farms WHERE region_id = :region_id ORDER BY name');
        $farmQuery->execute(['region_id' => $batch['region_id']]);
        $this->adminView('admin/batch-detail', [
            'title' => 'Lô ' . $batch['batch_code'],
            'batch' => $batch,
            'events' => $eventQuery->fetchAll(),
            'farms' => $farmQuery->fetchAll(),
            'eventTypes' => self::EVENT_TYPES,
            'scanCount' => $this->scanCount((int) $id),
            'success' => flash('success'),
            'error' => flash('error'),
            'menu' => $this->menu(),
        ]);
    }

    public function updateBatch(string $id): void
    {
        $batchId = (int) $id;
        $status = (string) ($_POST['status'] ?? '');
        if (($_POST['action'] ?? '') === 'recall') {
            $status = 'recalled';
        }
        $farmId = filter_var($_POST['farm_id'] ?? null, FILTER_VALIDATE_INT);
        $harvested = trim((string) ($_POST['harvested_at'] ?? ''));
        $packed = trim((string) ($_POST['packed_at'] ?? ''));
        $expires = trim((string) ($_POST['expires_at'] ?? ''));
        $received = filter_var($_POST['quantity_received'] ?? null, FILTER_VALIDATE_INT);
        $inspection = (string) ($_POST['inspection_result'] ?? '');
        $remaining = filter_var($_POST['quantity_remaining'] ?? null, FILTER_VALIDATE_INT);
        $reason = trim((string) ($_POST['recall_reason'] ?? ''));
        if (!$farmId || !$this->validDate($harvested) || !$this->validDate($packed) || !$this->validDate($expires)
            || $harvested > $packed || $packed > $expires || $received === false || $received < 1 || $received > 4294967295
            || !in_array($status, ['selling', 'suspended', 'recalled', 'ended'], true)
            || !in_array($inspection, ['pending', 'passed', 'failed'], true) || $remaining === false
            || $remaining < 0 || $remaining > 4294967295
            || $remaining > $received || ($status === 'recalled' && $reason === '')) {
            flash('error', 'Ngày, số lượng, trạng thái hoặc lý do thu hồi không hợp lệ.');
            redirect('/admin/batches/' . $batchId);
        }

        $database = Database::connection();
        $query = $database->prepare(
            'SELECT b.batch_code, b.status, p.region_id
             FROM product_batches b JOIN products p ON p.id = b.product_id WHERE b.id = :id'
        );
        $query->execute(['id' => $batchId]);
        $before = $query->fetch();
        $farmCheck = $database->prepare('SELECT id FROM farms WHERE id = :farm_id AND region_id = :region_id');
        if ($before) {
            $farmCheck->execute(['farm_id' => $farmId, 'region_id' => $before['region_id']]);
        }
        if (!$before || !$farmCheck->fetch()) {
            flash('error', 'Không tìm thấy lô hoặc nông trại cùng vùng miền.');
            redirect('/admin/batches/' . $batchId);
        }
        $database->beginTransaction();
        try {
            $database->prepare(
            'UPDATE product_batches SET farm_id = :farm_id, harvested_at = :harvested_at, packed_at = :packed_at,
                expires_at = :expires_at, quantity_received = :quantity_received,
                status = :status, inspection_result = :inspection_result,
                inspection_notes = :inspection_notes, quantity_remaining = :quantity_remaining, recall_reason = :recall_reason
             WHERE id = :id'
            )->execute([
            'farm_id' => $farmId,
            'harvested_at' => $harvested,
            'packed_at' => $packed,
            'expires_at' => $expires,
            'quantity_received' => $received,
            'status' => $status,
            'inspection_result' => $inspection,
            'inspection_notes' => trim((string) ($_POST['inspection_notes'] ?? '')) ?: null,
            'quantity_remaining' => $remaining,
            'recall_reason' => $status === 'recalled' ? $reason : null,
            'id' => $batchId,
            ]);
            if ($status === 'recalled' && $before['status'] !== 'recalled') {
                AuditLog::record((int) Auth::user()['id'], 'batch_recalled', 'product_batch', $batchId, $before['batch_code'] . ': ' . $reason);
            } else {
                AuditLog::record(
                    (int) Auth::user()['id'],
                    'batch_updated',
                    'product_batch',
                    $batchId,
                    $before['batch_code'] . '; status=' . $before['status'] . '->' . $status
                        . '; harvest=' . $harvested . '; packed=' . $packed . '; expires=' . $expires
                );
            }
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
        flash('success', 'Đã cập nhật trạng thái và kiểm định lô.');
        redirect('/admin/batches/' . $batchId);
    }

    public function deleteBatch(string $id): void
    {
        $batchId = (int) $id;
        $database = Database::connection();
        $query = $database->prepare(
            'SELECT b.batch_code,
                    (SELECT COUNT(*) FROM trace_events WHERE batch_id = b.id) AS event_count,
                    (SELECT COUNT(*) FROM trace_scans WHERE batch_id = b.id) AS scan_count,
                    (SELECT COUNT(*) FROM warehouse_receipt_items WHERE batch_id = b.id) AS receipt_count,
                    (SELECT COUNT(*) FROM inventory_movements WHERE batch_id = b.id) AS movement_count,
                    (SELECT COUNT(*) FROM order_items WHERE batch_id = b.id) AS order_count
             FROM product_batches b WHERE b.id = :id'
        );
        $query->execute(['id' => $batchId]);
        $batch = $query->fetch();
        if (!$batch) {
            flash('error', 'Không tìm thấy lô.');
            redirect('/admin/batches');
        }
        foreach (['event_count', 'scan_count', 'receipt_count', 'movement_count', 'order_count'] as $referenceCount) {
            if ((int) $batch[$referenceCount] > 0) {
                flash('error', 'Không thể xóa lô đã có sự kiện, lượt quét hoặc tham chiếu kho/đơn hàng; hãy đổi trạng thái lô.');
                redirect('/admin/batches/' . $batchId);
            }
        }
        $database->beginTransaction();
        try {
            $database->prepare('DELETE FROM product_batches WHERE id = :id')->execute(['id' => $batchId]);
            AuditLog::record((int) Auth::user()['id'], 'batch_deleted', 'product_batch', $batchId, $batch['batch_code']);
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
        flash('success', 'Đã xóa lô chưa phát sinh lịch sử.');
        redirect('/admin/batches');
    }

    public function storeEvent(string $id): void
    {
        $batchId = (int) $id;
        try {
            $input = $this->eventInput();
        } catch (RuntimeException $exception) {
            flash('error', $exception->getMessage());
            redirect('/admin/batches/' . $batchId);
        }
        $file = [];
        try {
            $file = $this->storeImage('image');
            $database = Database::connection();
            $database->beginTransaction();
            $database->prepare(
                'INSERT INTO trace_events (batch_id, event_type, event_at, location, description, image_url, sort_order, recorded_by)
                 VALUES (:batch_id, :event_type, :event_at, :location, :description, :image_url, :sort_order, :recorded_by)'
            )->execute($input + [
                'batch_id' => $batchId,
                'image_url' => $file['url'] ?? null,
                'recorded_by' => (int) Auth::user()['id'],
            ]);
            $eventId = (int) $database->lastInsertId();
            AuditLog::record((int) Auth::user()['id'], 'trace_event_created', 'trace_event', $eventId, (string) $batchId);
            $database->commit();
        } catch (RuntimeException $exception) {
            if (isset($database) && $database->inTransaction()) {
                $database->rollBack();
            }
            $this->removeFiles(['image' => $file]);
            flash('error', $exception->getMessage());
            redirect('/admin/batches/' . $batchId);
        } catch (Throwable $exception) {
            if (isset($database) && $database->inTransaction()) {
                $database->rollBack();
            }
            $this->removeFiles(['image' => $file]);
            throw $exception;
        }
        flash('success', 'Đã thêm sự kiện truy xuất.');
        redirect('/admin/batches/' . $batchId);
    }

    public function updateEvent(string $id): void
    {
        $eventId = (int) $id;
        $query = Database::connection()->prepare('SELECT batch_id, image_url FROM trace_events WHERE id = :id');
        $query->execute(['id' => $eventId]);
        $before = $query->fetch();
        if (!$before) {
            flash('error', 'Không tìm thấy sự kiện.');
            redirect('/admin/batches');
        }
        $batchId = (int) $before['batch_id'];
        try {
            $input = $this->eventInput();
        } catch (RuntimeException $exception) {
            flash('error', $exception->getMessage());
            redirect('/admin/batches/' . $batchId);
        }
        $file = [];
        $database = Database::connection();
        try {
            $file = $this->storeImage('image');
            $database->beginTransaction();
            $database->prepare(
                'UPDATE trace_events SET event_type = :event_type, event_at = :event_at, location = :location,
                    description = :description, image_url = :image_url, sort_order = :sort_order
                 WHERE id = :id'
            )->execute($input + [
                'image_url' => $file['url'] ?? (isset($_POST['remove_image']) ? null : $before['image_url']),
                'id' => $eventId,
            ]);
            AuditLog::record((int) Auth::user()['id'], 'trace_event_updated', 'trace_event', $eventId, (string) $batchId);
            $database->commit();
        } catch (RuntimeException $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            $this->removeFiles(['image' => $file]);
            flash('error', $exception->getMessage());
            redirect('/admin/batches/' . $batchId);
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            $this->removeFiles(['image' => $file]);
            throw $exception;
        }
        if ($before['image_url'] && ($file['url'] ?? null) !== $before['image_url']) {
            $this->removeUrl($before['image_url']);
        }
        flash('success', 'Đã cập nhật sự kiện.');
        redirect('/admin/batches/' . $batchId);
    }

    public function deleteEvent(string $id): void
    {
        $database = Database::connection();
        $query = $database->prepare('SELECT batch_id, image_url FROM trace_events WHERE id = :id');
        $query->execute(['id' => (int) $id]);
        $event = $query->fetch();
        if (!$event) {
            flash('error', 'Không tìm thấy sự kiện.');
            redirect('/admin/batches');
        }
        $database->beginTransaction();
        try {
            $database->prepare('DELETE FROM trace_events WHERE id = :id')->execute(['id' => (int) $id]);
            AuditLog::record((int) Auth::user()['id'], 'trace_event_deleted', 'trace_event', (int) $id, (string) $event['batch_id']);
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
        $this->removeUrl($event['image_url']);
        flash('success', 'Đã xóa sự kiện.');
        redirect('/admin/batches/' . (int) $event['batch_id']);
    }

    public function quickEvents(string $id): void
    {
        $batchId = (int) $id;
        $database = Database::connection();
        $query = $database->prepare('SELECT harvested_at, packed_at FROM product_batches WHERE id = :id');
        $query->execute(['id' => $batchId]);
        $batch = $query->fetch();
        if (!$batch) {
            flash('error', 'Không tìm thấy lô.');
            redirect('/admin/batches');
        }
        $existing = $database->prepare('SELECT COUNT(*) FROM trace_events WHERE batch_id = :id');
        $existing->execute(['id' => $batchId]);
        if ((int) $existing->fetchColumn() > 0) {
            flash('error', 'Lô đã có sự kiện. Không tạo nhanh để tránh ghi đè hoặc trùng dữ liệu.');
            redirect('/admin/batches/' . $batchId);
        }
        $harvest = new DateTimeImmutable($batch['harvested_at']);
        $packageDate = max(
            $batch['packed_at'] ?: $harvest->modify('+1 day')->format('Y-m-d'),
            $harvest->modify('+1 day')->format('Y-m-d')
        );
        $dates = [
            $harvest->modify('-90 days')->format('Y-m-d 08:00:00'),
            $harvest->modify('-30 days')->format('Y-m-d 08:00:00'),
            $harvest->format('Y-m-d 08:00:00'),
            $harvest->modify('+1 day')->format('Y-m-d 08:00:00'),
            $packageDate . ' 10:00:00',
            $packageDate . ' 15:00:00',
        ];
        $descriptions = [
            'Gieo trồng hạt giống/cây giống tại nông trại.',
            'Theo dõi sinh trưởng, chăm sóc và kiểm soát quy trình canh tác.',
            'Thu hoạch sản phẩm và chọn lọc tại vùng trồng.',
            'Sơ chế, làm sạch và phân loại sản phẩm.',
            'Đóng gói, dán nhãn và gắn mã truy xuất cho lô hàng.',
            'Vận chuyển lô hàng đến điểm phân phối trong điều kiện phù hợp.',
        ];
        $database->beginTransaction();
        try {
            $insert = $database->prepare(
                'INSERT INTO trace_events (batch_id, event_type, event_at, description, sort_order, recorded_by)
                 VALUES (:batch_id, :event_type, :event_at, :description, :sort_order, :recorded_by)'
            );
            $index = 0;
            foreach (self::EVENT_TYPES as $type => $label) {
                $insert->execute([
                    'batch_id' => $batchId,
                    'event_type' => $type,
                    'event_at' => $dates[$index],
                    'description' => $descriptions[$index],
                    'sort_order' => $index,
                    'recorded_by' => (int) Auth::user()['id'],
                ]);
                $index++;
            }
            AuditLog::record((int) Auth::user()['id'], 'trace_events_quick_created', 'product_batch', $batchId);
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
        flash('success', 'Đã tạo 6 bước truy xuất mẫu.');
        redirect('/admin/batches/' . $batchId);
    }

    public function printLabels(): void
    {
        $ids = $_GET['ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }
        $ids = array_values(array_unique(array_filter(array_map(
            static fn ($value) => filter_var($value, FILTER_VALIDATE_INT),
            $ids
        ), static fn ($value) => $value !== false && $value > 0)));
        if (!$ids || count($ids) > 60) {
            http_response_code(400);
            $this->adminView('admin/labels', [
                'title' => 'In tem truy xuất',
                'batches' => [],
                'error' => 'Chọn từ 1 đến 60 lô để in tem.',
                'menu' => $this->menu(),
            ]);
            return;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $query = Database::connection()->prepare(
            'SELECT b.id, b.batch_code, b.expires_at, p.name AS product_name
             FROM product_batches b JOIN products p ON p.id = b.product_id
             WHERE b.id IN (' . $placeholders . ') ORDER BY b.id'
        );
        $query->execute($ids);
        $this->adminView('admin/labels', [
            'title' => 'In tem truy xuất',
            'batches' => $query->fetchAll(),
            'error' => null,
            'menu' => $this->menu(),
        ]);
    }

    private function farmInput(): array
    {
        $name = trim((string) ($_POST['name'] ?? ''));
        $regionId = filter_var($_POST['region_id'] ?? null, FILTER_VALIDATE_INT);
        $address = trim((string) ($_POST['address'] ?? ''));
        $latitude = trim((string) ($_POST['latitude'] ?? ''));
        $longitude = trim((string) ($_POST['longitude'] ?? ''));
        $status = (string) ($_POST['status'] ?? 'active');
        $expires = trim((string) ($_POST['certification_expires_at'] ?? ''));
        if ($name === '' || mb_strlen($name) > 180 || !$regionId || $address === '' || mb_strlen($address) > 255
            || !in_array($status, ['active', 'inactive'], true)
            || ($expires !== '' && !$this->validDate($expires))
            || ($latitude !== '' && (!is_numeric($latitude) || (float) $latitude < -90 || (float) $latitude > 90))
            || ($longitude !== '' && (!is_numeric($longitude) || (float) $longitude < -180 || (float) $longitude > 180))) {
            throw new RuntimeException('Thông tin nông trại không hợp lệ. Kiểm tra tên, địa chỉ, vùng, ngày chứng nhận và tọa độ.');
        }
        $regionCheck = Database::connection()->prepare('SELECT id FROM regions WHERE id = :id AND level = 2');
        $regionCheck->execute(['id' => $regionId]);
        if (!$regionCheck->fetch()) {
            throw new RuntimeException('Chọn một tỉnh/vùng cấp 2 hợp lệ cho nông trại.');
        }
        return [
            'name' => $name,
            'region_id' => $regionId,
            'address' => $address,
            'contact_name' => trim((string) ($_POST['contact_name'] ?? '')) ?: null,
            'phone' => trim((string) ($_POST['phone'] ?? '')) ?: null,
            'certifications' => trim((string) ($_POST['certifications'] ?? '')) ?: null,
            'certification_number' => trim((string) ($_POST['certification_number'] ?? '')) ?: null,
            'certification_expires_at' => $expires ?: null,
            'latitude' => $latitude === '' ? null : $latitude,
            'longitude' => $longitude === '' ? null : $longitude,
            'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
            'status' => $status,
        ];
    }

    private function eventInput(): array
    {
        $type = (string) ($_POST['event_type'] ?? '');
        $date = trim((string) ($_POST['event_at'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $sortOrder = filter_var($_POST['sort_order'] ?? 0, FILTER_VALIDATE_INT);
        if (!isset(self::EVENT_TYPES[$type]) || !$this->validDateTime($date) || $description === ''
            || $sortOrder === false || $sortOrder < 0) {
            throw new RuntimeException('Thông tin sự kiện không hợp lệ.');
        }
        return [
            'event_type' => $type,
            'event_at' => str_replace('T', ' ', $date) . (strlen($date) === 16 ? ':00' : ''),
            'location' => trim((string) ($_POST['location'] ?? '')) ?: null,
            'description' => $description,
            'sort_order' => $sortOrder,
        ];
    }

    private function storeImage(string $field): array
    {
        if (!isset($_FILES[$field]) || (int) ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [];
        }
        $file = $_FILES[$field];
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (int) ($file['size'] ?? 0) <= 0
            || (int) $file['size'] > 2 * 1024 * 1024) {
            throw new RuntimeException('Ảnh phải tải thành công và có dung lượng tối đa 2 MB.');
        }
        $temporary = (string) $file['tmp_name'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporary);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $imageInfo = getimagesize($temporary);
        if (!isset($allowed[$mime]) || $imageInfo === false || ($imageInfo['mime'] ?? '') !== $mime) {
            throw new RuntimeException('Chỉ chấp nhận ảnh JPEG, PNG hoặc WebP hợp lệ.');
        }
        if ((int) $imageInfo[0] < 1 || (int) $imageInfo[1] < 1
            || (int) $imageInfo[0] > intdiv(40000000, (int) $imageInfo[1])) {
            throw new RuntimeException('Kích thước ảnh vượt quá giới hạn xử lý 40 megapixel.');
        }
        $directory = ROOT_PATH . '/uploads/traceability';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Không thể tạo thư mục lưu ảnh truy xuất.');
        }
        $filename = bin2hex(random_bytes(20)) . '.' . $allowed[$mime];
        $path = $directory . '/' . $filename;
        if (!move_uploaded_file($temporary, $path)) {
            throw new RuntimeException('Không thể lưu ảnh tải lên.');
        }
        try {
            $this->resizeImage($path, (string) $mime);
        } catch (Throwable $exception) {
            unlink($path);
            throw $exception;
        }
        return ['path' => $path, 'url' => '/uploads/traceability/' . $filename];
    }

    private function resizeImage(string $path, string $mime): void
    {
        if (!extension_loaded('gd')) {
            return;
        }
        $decoder = ['image/jpeg' => 'imagecreatefromjpeg', 'image/png' => 'imagecreatefrompng', 'image/webp' => 'imagecreatefromwebp'][$mime];
        if (!function_exists($decoder)) {
            return;
        }
        $image = $decoder($path);
        if ($image === false) {
            throw new RuntimeException('Không thể giải mã ảnh để thu nhỏ.');
        }
        try {
            $width = imagesx($image);
            $height = imagesy($image);
            if (max($width, $height) <= 1000) {
                return;
            }
            $ratio = 1000 / max($width, $height);
            $target = imagecreatetruecolor((int) round($width * $ratio), (int) round($height * $ratio));
            if ($mime === 'image/png' || $mime === 'image/webp') {
                imagealphablending($target, false);
                imagesavealpha($target, true);
            }
            imagecopyresampled($target, $image, 0, 0, 0, 0, imagesx($target), imagesy($target), $width, $height);
            if ($mime === 'image/jpeg') {
                imagejpeg($target, $path, 88);
            } elseif ($mime === 'image/png') {
                imagepng($target, $path, 6);
            } else {
                imagewebp($target, $path, 88);
            }
            imagedestroy($target);
        } finally {
            imagedestroy($image);
        }
    }

    private function removeFiles(array $files): void
    {
        foreach ($files as $file) {
            if (isset($file['path']) && is_file($file['path'])) {
                unlink($file['path']);
            }
        }
    }

    private function removeUrl(?string $url): void
    {
        if ($url && str_starts_with($url, '/uploads/traceability/')) {
            $path = ROOT_PATH . str_replace('/', DIRECTORY_SEPARATOR, $url);
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function uniqueFarmSlug(string $name): string
    {
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $base = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', (string) $transliterated), '-'));
        $base = $base !== '' ? $base : 'nong-trai';
        $slug = $base;
        $index = 2;
        $check = Database::connection()->prepare('SELECT id FROM farms WHERE slug = :slug LIMIT 1');
        while (true) {
            $check->execute(['slug' => $slug]);
            if (!$check->fetch()) {
                return $slug;
            }
            $slug = $base . '-' . $index++;
        }
    }

    private function validDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    private function validDateTime(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $date);
        $errors = DateTimeImmutable::getLastErrors();
        return $parsed !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
    }

    private function scanCount(int $batchId): int
    {
        $query = Database::connection()->prepare('SELECT COUNT(*) FROM trace_scans WHERE batch_id = :id');
        $query->execute(['id' => $batchId]);
        return (int) $query->fetchColumn();
    }

    private function adminView(string $template, array $data): void
    {
        $this->view($template, $data, 'admin');
    }

    private function menu(): array
    {
        $items = [
            ['permission' => 'dashboard.view', 'label' => 'Bảng điều khiển', 'url' => '/admin'],
            ['permission' => 'orders.view', 'label' => 'Đơn hàng', 'url' => '/admin/orders'],
            ['permission' => 'vouchers.view', 'label' => 'Voucher', 'url' => '/admin/vouchers'],
            ['permission' => 'reviews.view', 'label' => 'Đánh giá', 'url' => '/admin/reviews'],
            ['permission' => 'customers.view', 'label' => 'Khách hàng', 'url' => '/admin/customers'],
            ['permission' => 'inventory.view', 'label' => 'Kho hàng', 'url' => '/admin/inventory'],
            ['permission' => 'farms.view', 'label' => 'Nông trại', 'url' => '/admin/farms'],
            ['permission' => 'batches.view', 'label' => 'Lô hàng và truy xuất', 'url' => '/admin/batches'],
            ['permission' => 'regions.view', 'label' => 'Vùng miền', 'url' => '/admin/regions'],
            ['permission' => 'categories.view', 'label' => 'Danh mục', 'url' => '/admin/categories'],
            ['permission' => 'products.view', 'label' => 'Sản phẩm', 'url' => '/admin/products'],
            ['permission' => 'users.view', 'label' => 'Người dùng', 'url' => '/admin/users'],
        ];
        return array_values(array_filter($items, static fn (array $item): bool => Auth::can($item['permission'])));
    }
}
