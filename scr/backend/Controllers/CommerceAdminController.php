<?php

declare(strict_types=1);

final class CommerceAdminController extends Controller
{
    public function orders(): void
    {
        $database = Database::connection();
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => (string) ($_GET['status'] ?? ''),
            'payment_status' => (string) ($_GET['payment_status'] ?? ''),
            'from' => (string) ($_GET['from'] ?? ''),
            'to' => (string) ($_GET['to'] ?? ''),
        ];
        $conditions = [];
        $parameters = [];
        if ($filters['q'] !== '') {
            $conditions[] = '(o.order_no LIKE :order_no OR o.recipient_name LIKE :recipient OR o.recipient_phone LIKE :phone)';
            $search = '%' . $filters['q'] . '%';
            $parameters += ['order_no' => $search, 'recipient' => $search, 'phone' => $search];
        }
        if (in_array($filters['status'], ['pending', 'confirmed', 'processing', 'shipping', 'completed', 'cancelled', 'refunded'], true)) {
            $conditions[] = 'o.status = :status';
            $parameters['status'] = $filters['status'];
        } else {
            $filters['status'] = '';
        }
        if (in_array($filters['payment_status'], ['unpaid', 'pending', 'paid', 'failed', 'refunded'], true)) {
            $conditions[] = 'o.payment_status = :payment_status';
            $parameters['payment_status'] = $filters['payment_status'];
        } else {
            $filters['payment_status'] = '';
        }
        if ($this->validDate($filters['from'])) {
            $conditions[] = 'o.placed_at >= :from_date';
            $parameters['from_date'] = $filters['from'] . ' 00:00:00';
        } else {
            $filters['from'] = '';
        }
        if ($this->validDate($filters['to'])) {
            $conditions[] = 'o.placed_at < :to_date';
            $parameters['to_date'] = date('Y-m-d', strtotime($filters['to'] . ' +1 day')) . ' 00:00:00';
        } else {
            $filters['to'] = '';
        }
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $count = $database->prepare('SELECT COUNT(*) FROM orders o ' . $where);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $pages = max(1, (int) ceil($total / 20));
        $page = min($page, $pages);
        $query = $database->prepare(
            'SELECT o.*, u.name AS customer_name, u.email AS customer_email
             FROM orders o JOIN users u ON u.id = o.user_id ' . $where . '
             ORDER BY o.placed_at DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $key => $value) {
            $query->bindValue(':' . $key, $value);
        }
        $query->bindValue(':limit', 20, PDO::PARAM_INT);
        $query->bindValue(':offset', ($page - 1) * 20, PDO::PARAM_INT);
        $query->execute();
        $this->adminView('admin/orders', [
            'title' => 'Đơn hàng',
            'orders' => $query->fetchAll(),
            'filters' => $filters,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'canManage' => Auth::can('orders.manage'),
            'canFulfill' => Auth::can('orders.fulfill'),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function order(string $id): void
    {
        $database = Database::connection();
        $query = $database->prepare('SELECT o.*, u.name AS customer_name, u.email AS customer_email FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = :id');
        $query->execute(['id' => (int) $id]);
        $order = $query->fetch();
        if (!$order) {
            http_response_code(404);
            View::renderError(404);
            return;
        }
        $items = $database->prepare(
            'SELECT i.*, b.batch_code FROM order_items i LEFT JOIN product_batches b ON b.id = i.batch_id
             WHERE i.order_id = :id ORDER BY i.id'
        );
        $items->execute(['id' => (int) $id]);
        $history = $database->prepare(
            'SELECT h.*, u.name AS changed_by_name FROM order_status_history h
             LEFT JOIN users u ON u.id = h.changed_by WHERE h.order_id = :id ORDER BY h.created_at, h.id'
        );
        $history->execute(['id' => (int) $id]);
        $payments = $database->prepare('SELECT * FROM payments WHERE order_id = :id ORDER BY id DESC');
        $payments->execute(['id' => (int) $id]);
        $this->adminView('admin/order-detail', [
            'title' => 'Đơn hàng ' . $order['order_no'],
            'order' => $order,
            'items' => $items->fetchAll(),
            'history' => $history->fetchAll(),
            'payments' => $payments->fetchAll(),
            'canManage' => Auth::can('orders.manage'),
            'canFulfill' => Auth::can('orders.fulfill'),
            'print' => isset($_GET['print']),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function updateOrder(string $id): void
    {
        $status = (string) ($_POST['status'] ?? '');
        try {
            (new OrderService())->transition(
                (int) $id,
                (int) Auth::user()['id'],
                $status,
                trim((string) ($_POST['note'] ?? '')),
                Auth::can('orders.manage')
            );
            flash('success', 'Đã cập nhật trạng thái đơn hàng.');
        } catch (InventoryException | InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('/admin/orders/' . (int) $id);
    }

    public function exportOrders(): void
    {
        $rows = Database::connection()->query(
            'SELECT order_no, recipient_name, recipient_phone, shipping_address, status, payment_status,
                    subtotal, discount_amount, shipping_fee, total_amount, placed_at
             FROM orders ORDER BY placed_at DESC'
        )->fetchAll();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="orders.csv"');
        $output = fopen('php://output', 'wb');
        if ($output === false) {
            throw new RuntimeException('Không mở được luồng xuất CSV.');
        }
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['Mã đơn', 'Người nhận', 'Điện thoại', 'Địa chỉ', 'Trạng thái', 'Thanh toán', 'Tạm tính', 'Giảm giá', 'Phí ship', 'Tổng', 'Ngày đặt']);
        foreach ($rows as $row) {
            foreach (['order_no', 'recipient_name', 'recipient_phone', 'shipping_address', 'status', 'payment_status'] as $field) {
                $row[$field] = $this->csvText($row[$field]);
            }
            fputcsv($output, array_values($row));
        }
        fclose($output);
        exit;
    }

    public function vouchers(): void
    {
        $database = Database::connection();
        $vouchers = $database->query(
            'SELECT v.*,
                (SELECT GROUP_CONCAT(category_id) FROM voucher_categories vc WHERE vc.voucher_id = v.id) AS category_ids,
                (SELECT GROUP_CONCAT(product_id) FROM voucher_products vp WHERE vp.voucher_id = v.id) AS product_ids,
                (SELECT COUNT(*) FROM voucher_usages vu WHERE vu.voucher_id = v.id AND vu.status = \'used\') AS active_uses
             FROM vouchers v ORDER BY v.id DESC'
        )->fetchAll();
        $categories = $database->query('SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name')->fetchAll();
        $products = $database->query('SELECT id, name, sku FROM products WHERE status = \'active\' AND deleted_at IS NULL ORDER BY name')->fetchAll();
        $this->adminView('admin/vouchers', [
            'title' => 'Quản lý voucher',
            'vouchers' => $vouchers,
            'categories' => $categories,
            'products' => $products,
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function saveVoucher(?string $id = null): void
    {
        $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
        $type = (string) ($_POST['discount_type'] ?? '');
        $value = filter_var($_POST['discount_value'] ?? null, FILTER_VALIDATE_INT);
        $minimum = filter_var($_POST['minimum_order'] ?? 0, FILTER_VALIDATE_INT);
        $maximum = ($_POST['maximum_discount'] ?? '') === '' ? null : filter_var($_POST['maximum_discount'], FILTER_VALIDATE_INT);
        $usageLimit = ($_POST['usage_limit'] ?? '') === '' ? null : filter_var($_POST['usage_limit'], FILTER_VALIDATE_INT);
        $perUser = filter_var($_POST['per_user_limit'] ?? 1, FILTER_VALIDATE_INT);
        $starts = trim((string) ($_POST['starts_at'] ?? ''));
        $ends = trim((string) ($_POST['ends_at'] ?? ''));
        $status = (string) ($_POST['status'] ?? 'active');
        if (preg_match('/\A[A-Z0-9_-]{3,50}\z/', $code) !== 1
            || !in_array($type, ['fixed', 'percent'], true) || $value === false || $value < 1
            || $minimum === false || $minimum < 0 || ($maximum !== null && ($maximum === false || $maximum < 1))
            || ($usageLimit !== null && ($usageLimit === false || $usageLimit < 1))
            || $perUser === false || $perUser < 1
            || !$this->validDateTime($starts) || !$this->validDateTime($ends) || $ends <= $starts
            || !in_array($status, ['active', 'inactive'], true) || ($type === 'percent' && $value > 100)) {
            flash('error', 'Dữ liệu voucher không hợp lệ.');
            redirect('/admin/vouchers');
        }
        $categoryIds = $this->validIds($_POST['category_ids'] ?? []);
        $productIds = $this->validIds($_POST['product_ids'] ?? []);
        $database = Database::connection();
        $database->beginTransaction();
        try {
            if ($id === null) {
                $database->prepare(
                    'INSERT INTO vouchers
                     (code, description, discount_type, discount_value, minimum_order, maximum_discount,
                      usage_limit, per_user_limit, starts_at, ends_at, status)
                     VALUES (:code, :description, :type, :value, :minimum, :maximum, :usage_limit, :per_user, :starts, :ends, :status)'
                )->execute([
                    'code' => $code, 'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
                    'type' => $type, 'value' => $value, 'minimum' => $minimum, 'maximum' => $maximum,
                    'usage_limit' => $usageLimit, 'per_user' => $perUser, 'starts' => str_replace('T', ' ', $starts) . ':00',
                    'ends' => str_replace('T', ' ', $ends) . ':00', 'status' => $status,
                ]);
                $voucherId = (int) $database->lastInsertId();
            } else {
                $voucherId = (int) $id;
                $database->prepare(
                    'UPDATE vouchers SET code = :code, description = :description, discount_type = :type,
                        discount_value = :value, minimum_order = :minimum, maximum_discount = :maximum,
                        usage_limit = :usage_limit, per_user_limit = :per_user, starts_at = :starts,
                        ends_at = :ends, status = :status WHERE id = :id'
                )->execute([
                    'code' => $code, 'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
                    'type' => $type, 'value' => $value, 'minimum' => $minimum, 'maximum' => $maximum,
                    'usage_limit' => $usageLimit, 'per_user' => $perUser, 'starts' => str_replace('T', ' ', $starts) . ':00',
                    'ends' => str_replace('T', ' ', $ends) . ':00', 'status' => $status, 'id' => $voucherId,
                ]);
                if ($database->query('SELECT ROW_COUNT()')->fetchColumn() === '0') {
                    $exists = $database->prepare('SELECT id FROM vouchers WHERE id = :id');
                    $exists->execute(['id' => $voucherId]);
                    if ($exists->fetchColumn() === false) {
                        throw new InvalidArgumentException('Không tìm thấy voucher cần cập nhật.');
                    }
                }
            }
            $database->prepare('DELETE FROM voucher_categories WHERE voucher_id = :id')->execute(['id' => $voucherId]);
            $database->prepare('DELETE FROM voucher_products WHERE voucher_id = :id')->execute(['id' => $voucherId]);
            $categoryInsert = $database->prepare('INSERT INTO voucher_categories (voucher_id, category_id) VALUES (:voucher_id, :target_id)');
            foreach ($categoryIds as $categoryId) {
                $categoryInsert->execute(['voucher_id' => $voucherId, 'target_id' => $categoryId]);
            }
            $productInsert = $database->prepare('INSERT INTO voucher_products (voucher_id, product_id) VALUES (:voucher_id, :target_id)');
            foreach ($productIds as $productId) {
                $productInsert->execute(['voucher_id' => $voucherId, 'target_id' => $productId]);
            }
            AuditLog::record((int) Auth::user()['id'], $id === null ? 'voucher_created' : 'voucher_updated', 'voucher', $voucherId, $code, $database);
            $database->commit();
            flash('success', $id === null ? 'Đã tạo voucher.' : 'Đã cập nhật voucher.');
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            if ($exception instanceof PDOException && $exception->getCode() === '23000') {
                flash('error', 'Mã voucher đã tồn tại hoặc danh mục/sản phẩm không hợp lệ.');
            } elseif ($exception instanceof InvalidArgumentException) {
                flash('error', $exception->getMessage());
            } else {
                throw $exception;
            }
        }
        redirect('/admin/vouchers');
    }

    public function shipping(): void
    {
        $database = Database::connection();
        $fees = $database->query(
            'SELECT sf.*, r.name AS region_name FROM shipping_fees sf JOIN regions r ON r.id = sf.region_id
             ORDER BY r.level, r.name'
        )->fetchAll();
        $regions = $database->query('SELECT id, name, level FROM regions WHERE is_active = 1 AND level IN (1, 2) ORDER BY level, name')->fetchAll();
        $minimum = $database->query("SELECT setting_value FROM settings WHERE setting_key = 'free_shipping_minimum'")->fetchColumn();
        $this->adminView('admin/shipping', [
            'title' => 'Phí vận chuyển',
            'fees' => $fees,
            'regions' => $regions,
            'freeMinimum' => $minimum === false ? '500000' : $minimum,
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function saveShipping(?string $id = null): void
    {
        $regionId = filter_var($_POST['region_id'] ?? null, FILTER_VALIDATE_INT);
        $fee = filter_var($_POST['fee'] ?? null, FILTER_VALIDATE_INT);
        $minimum = ($_POST['free_shipping_minimum'] ?? '') === '' ? null : filter_var($_POST['free_shipping_minimum'], FILTER_VALIDATE_INT);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        if (!$regionId || $fee === false || $fee < 0 || ($minimum !== null && ($minimum === false || $minimum < 1))) {
            flash('error', 'Tỉnh/thành, phí ship hoặc ngưỡng miễn phí không hợp lệ.');
            redirect('/admin/shipping');
        }
        $database = Database::connection();
        $region = $database->prepare('SELECT id FROM regions WHERE id = :id AND level IN (1, 2) AND is_active = 1');
        $region->execute(['id' => $regionId]);
        if ($region->fetchColumn() === false) {
            flash('error', 'Chỉ chọn miền hoặc tỉnh/thành đang hiển thị.');
            redirect('/admin/shipping');
        }
        $globalMinimum = filter_var($_POST['global_free_shipping_minimum'] ?? null, FILTER_VALIDATE_INT);
        if ($globalMinimum === false || $globalMinimum < 1) {
            flash('error', 'Ngưỡng miễn phí vận chuyển chung phải lớn hơn 0.');
            redirect('/admin/shipping');
        }
        $database->beginTransaction();
        try {
            if ($id !== null) {
                $database->prepare(
                    'UPDATE shipping_fees SET region_id = :region_id, fee = :fee,
                        free_shipping_minimum = :minimum, is_active = :active WHERE id = :id'
                )->execute(['region_id' => $regionId, 'fee' => $fee, 'minimum' => $minimum, 'active' => $isActive, 'id' => (int) $id]);
            } else {
                $database->prepare(
                    'INSERT INTO shipping_fees (region_id, fee, free_shipping_minimum, is_active)
                     VALUES (:region_id, :fee, :minimum, :active)
                     ON DUPLICATE KEY UPDATE fee = VALUES(fee),
                        free_shipping_minimum = VALUES(free_shipping_minimum), is_active = VALUES(is_active)'
                )->execute(['region_id' => $regionId, 'fee' => $fee, 'minimum' => $minimum, 'active' => $isActive]);
            }
            $database->prepare(
                'INSERT INTO settings (setting_key, setting_value, description)
                 VALUES (\'free_shipping_minimum\', :value, \'Giá trị đơn tối thiểu để miễn phí vận chuyển\')
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            )->execute(['value' => (string) $globalMinimum]);
            AuditLog::record((int) Auth::user()['id'], 'shipping_fee_saved', 'shipping_fee', $id === null ? null : (int) $id, 'region=' . $regionId);
            $database->commit();
            flash('success', 'Đã lưu cấu hình phí vận chuyển.');
        } catch (PDOException $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            if ($exception->getCode() === '23000') {
                flash('error', 'Tỉnh/thành đã có cấu hình phí vận chuyển.');
            } else {
                throw $exception;
            }
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
        redirect('/admin/shipping');
    }

    public function deleteShipping(string $id): void
    {
        Database::connection()->prepare('DELETE FROM shipping_fees WHERE id = :id')->execute(['id' => (int) $id]);
        AuditLog::record((int) Auth::user()['id'], 'shipping_fee_deleted', 'shipping_fee', (int) $id);
        flash('success', 'Đã xóa cấu hình phí vận chuyển.');
        redirect('/admin/shipping');
    }

    private function adminView(string $template, array $data): void
    {
        $data['menu'] = (new AdminController())->menu();
        $this->view($template, $data, 'admin');
    }

    private function validIds(mixed $input): array
    {
        if (!is_array($input)) {
            return [];
        }
        $ids = [];
        foreach ($input as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT);
            if ($id !== false && $id > 0) {
                $ids[] = $id;
            }
        }
        return array_values(array_unique($ids));
    }

    private function validDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function validDateTime(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value);
        return $date !== false && $date->format('Y-m-d\TH:i') === $value;
    }

    private function csvText(mixed $value): string
    {
        $text = (string) $value;
        return preg_match('/^[\t\r\n ]*[=+\-@]/', $text) === 1 ? "'" . $text : $text;
    }
}
