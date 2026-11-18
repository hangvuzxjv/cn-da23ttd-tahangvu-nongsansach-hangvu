<?php

declare(strict_types=1);

final class CommerceAdminController extends Controller
{
    public function orders(): void
    {
        (new OrderService())->runLazyAutoCompletionSafely();
        (new PaymentService())->expirePending();
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
        if (in_array($filters['status'], ['pending', 'confirmed', 'processing', 'shipping', 'completed', 'cancelled', 'refunded', 'delivery_failed'], true)) {
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
            'SELECT o.*, u.name AS customer_name, u.email AS customer_email,
                    (SELECT p.method FROM payments p WHERE p.order_id = o.id ORDER BY p.id DESC LIMIT 1) AS payment_method
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
            'canQuickAdvance' => Auth::can('orders.manage') || Auth::can('orders.fulfill'),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function deliveryFailures(): void
    {
        $database = Database::connection();
        $reason = (string) ($_GET['reason'] ?? '');
        $reasons = OrderService::DELIVERY_FAILURE_REASONS;
        if (!array_key_exists($reason, $reasons)) {
            $reason = '';
        }

        $where = '';
        $parameters = [];
        if ($reason !== '') {
            $where = 'WHERE df.reason = :reason';
            $parameters['reason'] = $reason;
        }
        $count = $database->prepare('SELECT COUNT(*) FROM delivery_failures df ' . $where);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $perPage = 20;
        $pages = max(1, (int) ceil($total / $perPage));
        $requestedPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
        $page = min(max(1, $requestedPage === false ? 1 : $requestedPage), $pages);
        $query = $database->prepare(
            'SELECT df.*, o.order_no, u.name AS customer_name, u.email AS customer_email
             FROM delivery_failures df
             JOIN orders o ON o.id = df.order_id
             JOIN users u ON u.id = df.user_id ' . $where . '
             ORDER BY df.created_at DESC, df.id DESC
             LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $name => $value) {
            $query->bindValue(':' . $name, $value, PDO::PARAM_STR);
        }
        $query->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $query->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $query->execute();
        $this->adminView('admin/delivery-failures', [
            'title' => 'Giao thất bại',
            'failures' => $query->fetchAll(),
            'reasons' => $reasons,
            'reason' => $reason,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'paginationUrl' => '/admin/delivery-failures',
            'paginationQuery' => $reason === '' ? [] : ['reason' => $reason],
            'paginationLabel' => 'lượt giao thất bại',
        ]);
    }

    public function codRisk(): void
    {
        $service = new CodRiskService();
        $risk = $service->activeBlocks((int) ($_GET['page'] ?? 1));
        $this->adminView('admin/cod-risk', [
            'title' => 'Rủi ro COD',
            'settings' => $service->settings(),
            'oldSettings' => flash('cod_risk_old') ?? [],
            'blocks' => $risk['items'],
            'page' => $risk['page'],
            'pages' => $risk['pages'],
            'total' => $risk['total'],
            'waivers' => $service->recentWaivers(),
            'paginationUrl' => '/admin/cod-risk',
            'paginationQuery' => [],
            'paginationLabel' => 'định danh đang bị chặn',
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function saveCodRiskSettings(): void
    {
        try {
            (new CodRiskService())->saveSettings($_POST, (int) Auth::user()['id']);
            flash('success', 'Đã lưu ngưỡng chặn COD.');
        } catch (InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
            $oldSettings = [];
            foreach ([
                'cod_risk_failure_days',
                'cod_risk_failure_threshold',
                'cod_risk_new_customer_limit',
                'cod_risk_open_orders_limit',
            ] as $key) {
                if (isset($_POST[$key]) && is_scalar($_POST[$key])) {
                    $oldSettings[$key] = (string) $_POST[$key];
                }
            }
            flash('cod_risk_old', $oldSettings);
        }
        redirect('/admin/cod-risk');
    }

    public function waiveCodRisk(): void
    {
        try {
            (new CodRiskService())->waive(
                (string) ($_POST['type'] ?? ''),
                (string) ($_POST['value'] ?? ''),
                (string) ($_POST['reason'] ?? ''),
                (int) Auth::user()['id']
            );
            flash('success', 'Đã gỡ chặn Luật A. Luật B và Luật C vẫn được áp dụng.');
        } catch (InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('/admin/cod-risk');
    }

    public function order(string $id): void
    {
        (new PaymentService())->expireForOrder((int) $id);
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
        $autoCompleteAt = null;
        if ($order['status'] === 'shipping') {
            $autoCompleteQuery = $database->prepare(
                "SELECT DATE_FORMAT(
                    COALESCE(
                        (
                            SELECT DATE_ADD(h.created_at, INTERVAL " . OrderService::AUTO_COMPLETION_DAYS . " DAY)
                            FROM order_status_history h
                            WHERE h.order_id = o.id AND h.status = 'shipping'
                            ORDER BY h.id DESC LIMIT 1
                        ),
                        DATE_ADD(o.updated_at, INTERVAL " . OrderService::AUTO_COMPLETION_DAYS . " DAY)
                    ),
                    '%d/%m/%Y %H:%i'
                 )
                 FROM orders o WHERE o.id = :id"
            );
            $autoCompleteQuery->execute(['id' => (int) $id]);
            $autoCompleteAt = $autoCompleteQuery->fetchColumn() ?: null;
        }
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
            'deliveryFailureReasons' => OrderService::DELIVERY_FAILURE_REASONS,
            'autoCompleteAt' => $autoCompleteAt,
            'autoCompletionDays' => OrderService::AUTO_COMPLETION_DAYS,
            'quickNextStep' => (new OrderService($database))->nextMainlineStep(
                (int) $order['id'],
                Auth::can('orders.manage')
            ),
            'print' => isset($_GET['print']),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function updateOrder(string $id): void
    {
        $status = (string) ($_POST['status'] ?? '');
        try {
            $service = new OrderService();
            if ($status === 'completed') {
                $service->adminConfirmDelivered(
                    (int) $id,
                    (int) Auth::user()['id'],
                    (string) ($_POST['note'] ?? ''),
                    Auth::can('orders.manage')
                );
            } else {
                $service->transition(
                    (int) $id,
                    (int) Auth::user()['id'],
                    $status,
                    trim((string) ($_POST['note'] ?? '')),
                    Auth::can('orders.manage')
                );
            }
            flash('success', 'Đã cập nhật trạng thái đơn hàng.');
        } catch (InventoryException | InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('/admin/orders/' . (int) $id);
    }

    public function recordDeliveryFailure(string $id): void
    {
        try {
            (new OrderService())->recordDeliveryFailure(
                (int) $id,
                (int) Auth::user()['id'],
                (string) ($_POST['reason'] ?? ''),
                (string) ($_POST['note'] ?? ''),
                Auth::can('orders.manage')
            );
            flash('success', 'Đã ghi nhận giao hàng thất bại và hoàn lại tồn kho.');
        } catch (InventoryException | InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('/admin/orders/' . (int) $id);
    }

    public function advanceOrder(string $id): void
    {
        try {
            $nextStatus = (new OrderService())->transitionToNext(
                (int) $id,
                (int) Auth::user()['id'],
                Auth::can('orders.manage'),
                'quick'
            );
            flash('success', 'Đã chuyển đơn sang ' . $this->orderStatusLabel($nextStatus) . '.');
        } catch (InventoryException | InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('/admin/orders/' . (int) $id);
    }

    public function advanceOrders(): void
    {
        $selectedOrderIds = $_POST['order_ids'] ?? [];
        if (!is_array($selectedOrderIds) || !$selectedOrderIds) {
            flash('error', 'Hãy chọn ít nhất một đơn hàng.');
            redirect('/admin/orders');
        }

        $result = (new OrderService())->advanceMany(
            $selectedOrderIds,
            (int) Auth::user()['id'],
            Auth::can('orders.manage')
        );
        $advancedCount = count($result['advanced']);
        $skippedCount = count($result['skipped']);
        $summary = 'Đã chuyển ' . $advancedCount . ' đơn, bỏ qua ' . $skippedCount . ' đơn.';
        if ($result['skipped']) {
            $details = array_map(
                static fn (array $item): string => $item['order_no'] . ': ' . $item['reason'],
                $result['skipped']
            );
            $summary .= ' ' . implode(' ', $details);
        }
        flash('success', $summary);
        redirect('/admin/orders');
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

    public function payments(): void
    {
        (new PaymentService())->expirePending();
        $database = Database::connection();
        $filters = [
            'method' => (string) ($_GET['method'] ?? ''),
            'status' => (string) ($_GET['status'] ?? ''),
            'from' => (string) ($_GET['from'] ?? ''),
            'to' => (string) ($_GET['to'] ?? ''),
        ];
        $conditions = [];
        $parameters = [];
        if (in_array($filters['method'], ['cod', 'bank_transfer', 'vnpay', 'online'], true)) {
            $conditions[] = 'p.method = :method';
            $parameters['method'] = $filters['method'];
        } else {
            $filters['method'] = '';
        }
        if (in_array($filters['status'], ['pending', 'paid', 'failed', 'refunded'], true)) {
            $conditions[] = 'p.status = :status';
            $parameters['status'] = $filters['status'];
        } else {
            $filters['status'] = '';
        }
        if ($this->validDate($filters['from'])) {
            $conditions[] = 'p.created_at >= :from_date';
            $parameters['from_date'] = $filters['from'] . ' 00:00:00';
        } else {
            $filters['from'] = '';
        }
        if ($this->validDate($filters['to'])) {
            $conditions[] = 'p.created_at < :to_date';
            $parameters['to_date'] = date('Y-m-d', strtotime($filters['to'] . ' +1 day')) . ' 00:00:00';
        } else {
            $filters['to'] = '';
        }
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $countQuery = $database->prepare(
            'SELECT COUNT(*) FROM payments p JOIN orders o ON o.id = p.order_id
             JOIN users u ON u.id = o.user_id ' . $where
        );
        $countQuery->execute($parameters);
        $total = (int) $countQuery->fetchColumn();
        $perPage = 20;
        $pages = max(1, (int) ceil($total / $perPage));
        $requestedPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
        $page = min(max(1, $requestedPage === false ? 1 : $requestedPage), $pages);
        $query = $database->prepare(
            'SELECT p.*, o.order_no, o.user_id, o.status AS order_status, u.name AS customer_name
             FROM payments p JOIN orders o ON o.id = p.order_id
             JOIN users u ON u.id = o.user_id ' . $where . '
             ORDER BY p.created_at DESC, p.id DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $name => $value) {
            $query->bindValue(':' . $name, $value, PDO::PARAM_STR);
        }
        $query->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $query->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $query->execute();
        $totals = (new PaymentRevenueService($database))->totalsByMethod();
        $this->adminView('admin/payments', [
            'title' => 'Thanh toán',
            'payments' => $query->fetchAll(),
            'totals' => $totals,
            'filters' => $filters,
            'page' => $page,
            'pages' => $pages,
            'paginationUrl' => '/admin/payments',
            'paginationQuery' => array_filter($filters, static fn (string $value): bool => $value !== ''),
            'paginationLabel' => 'giao dịch thanh toán',
            'canManage' => Auth::can('orders.manage'),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function payment(string $id): void
    {
        $query = Database::connection()->prepare(
            'SELECT p.*, o.order_no, o.total_amount, o.status AS order_status,
                    o.payment_status, u.name AS customer_name, u.email AS customer_email
             FROM payments p JOIN orders o ON o.id = p.order_id
             JOIN users u ON u.id = o.user_id WHERE p.id = :id'
        );
        $query->execute(['id' => (int) $id]);
        $payment = $query->fetch();
        if (!$payment) {
            http_response_code(404);
            View::renderError(404);
            return;
        }
        $payment['raw_response'] = $payment['raw_response']
            ? json_decode((string) $payment['raw_response'], true)
            : [];
        $this->adminView('admin/payment-detail', [
            'title' => 'Giao dịch #' . $payment['id'],
            'payment' => $payment,
            'canManage' => Auth::can('orders.manage'),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function paymentProof(string $id): Response
    {
        $proof = (new PaymentService())->proofFile((int) $id);
        if ($proof === null) {
            return new Response('Không tìm thấy ảnh biên lai.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }
        return new Response(
            (string) file_get_contents($proof['path']),
            200,
            ['Content-Type' => $proof['mime'], 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']
        );
    }

    public function paymentSettings(): void
    {
        $this->adminView('admin/payment-settings', [
            'title' => 'Cấu hình thanh toán',
            'settings' => (new PaymentService())->settings(),
            'vnpayConfigured' => (new PaymentService())->vnpayConfigured(),
            'canManage' => Auth::can('orders.manage'),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function savePaymentSettings(): void
    {
        try {
            (new PaymentService())->saveSettings($_POST, (int) Auth::user()['id']);
            flash('success', 'Đã lưu cấu hình thanh toán.');
        } catch (InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('/admin/payments/settings');
    }

    public function confirmPayment(string $id): void
    {
        try {
            $amount = filter_var($_POST['received_amount'] ?? null, FILTER_VALIDATE_INT);
            if ($amount === false || $amount < 1) {
                throw new InvalidArgumentException('Nhập số tiền thực nhận hợp lệ.');
            }
            (new PaymentService())->confirmBankTransfer((int) $id, (int) Auth::user()['id'], $amount);
            flash('success', 'Đã xác nhận giao dịch và xuất kho.');
        } catch (InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('/admin/payments/' . (int) $id);
    }

    public function reconcileVnpayPayment(string $id): void
    {
        try {
            (new PaymentService())->reconcileVnpayManually(
                (int) $id,
                (int) Auth::user()['id'],
                (string) ($_POST['transaction_no'] ?? ''),
                (string) ($_POST['reason'] ?? '')
            );
            flash('success', 'Đã đối soát VNPay, ghi nhận thanh toán và trừ kho.');
        } catch (InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('/admin/payments/' . (int) $id);
    }

    public function rejectPayment(string $id): void
    {
        try {
            (new PaymentService())->rejectBankTransfer(
                (int) $id,
                (int) Auth::user()['id'],
                trim((string) ($_POST['reason'] ?? ''))
            );
            flash('success', 'Đã từ chối thanh toán và hủy đơn, giải phóng tồn kho/voucher.');
        } catch (InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('/admin/payments/' . (int) $id);
    }

    public function refundPayment(string $id): void
    {
        try {
            (new PaymentService())->refund(
                (int) $id,
                (int) Auth::user()['id'],
                trim((string) ($_POST['reason'] ?? ''))
            );
            flash('success', 'Đã ghi nhận hoàn tiền thủ công và hoàn lại kho/voucher.');
        } catch (InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('/admin/payments/' . (int) $id);
    }

    public function markDeliveryFailureRefunded(string $id): void
    {
        try {
            (new PaymentService())->markDeliveryFailureRefunded(
                (int) $id,
                (int) Auth::user()['id'],
                (string) ($_POST['reference'] ?? '')
            );
            flash('success', 'Đã ghi nhận khoản thanh toán được hoàn tiền.');
        } catch (InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('/admin/payments/' . (int) $id);
    }

    public function vouchers(): void
    {
        $database = Database::connection();
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => (string) ($_GET['status'] ?? ''),
            'audience' => (string) ($_GET['audience'] ?? ''),
        ];
        $statusExpression = "CASE
            WHEN v.status <> 'active' THEN 'inactive'
            WHEN v.starts_at > NOW() THEN 'upcoming'
            WHEN v.ends_at < NOW() THEN 'expired'
            WHEN v.usage_limit IS NOT NULL AND v.used_count >= v.usage_limit THEN 'exhausted'
            ELSE 'running'
        END";
        $conditions = [];
        $parameters = [];
        if ($filters['q'] !== '') {
            $conditions[] = 'v.code LIKE :code';
            $parameters['code'] = '%' . $filters['q'] . '%';
        }
        if (in_array($filters['status'], ['running', 'upcoming', 'inactive', 'expired', 'exhausted'], true)) {
            $conditions[] = $statusExpression . ' = :computed_status';
            $parameters['computed_status'] = $filters['status'];
        } else {
            $filters['status'] = '';
        }
        if (in_array($filters['audience'], ['code', 'public', 'targeted'], true)) {
            $conditions[] = 'v.audience = :audience';
            $parameters['audience'] = $filters['audience'];
        } else {
            $filters['audience'] = '';
        }
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $countQuery = $database->prepare('SELECT COUNT(*) FROM vouchers v ' . $where);
        $countQuery->execute($parameters);
        $total = (int) $countQuery->fetchColumn();
        $perPage = 20;
        $pages = max(1, (int) ceil($total / $perPage));
        $requestedPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
        $page = min(max(1, $requestedPage === false ? 1 : $requestedPage), $pages);
        $voucherQuery = $database->prepare(
            'SELECT v.*, NOW() AS status_now,
                ' . $statusExpression . ' AS computed_status,
                EXISTS(SELECT 1 FROM voucher_usages vu WHERE vu.voucher_id = v.id) AS has_ever_been_used
             FROM vouchers v ' . $where . '
             ORDER BY CASE WHEN (' . $statusExpression . ') = \'running\' THEN 0
                           WHEN (' . $statusExpression . ') = \'upcoming\' THEN 1 ELSE 2 END,
                      v.id DESC
             LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $key => $value) {
            $voucherQuery->bindValue(':' . $key, $value);
        }
        $voucherQuery->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $voucherQuery->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $voucherQuery->execute();
        $vouchers = $voucherQuery->fetchAll();
        foreach ($vouchers as &$voucher) {
            $voucher['computed_status'] = VoucherAdminService::status($voucher, new DateTimeImmutable($voucher['status_now']));
            unset($voucher['status_now']);
        }
        unset($voucher);
        $this->adminView('admin/vouchers', [
            'title' => 'Quản lý voucher',
            'vouchers' => $vouchers,
            'filters' => $filters,
            'page' => $page,
            'pages' => $pages,
            'paginationUrl' => '/admin/vouchers',
            'paginationQuery' => $filters,
            'paginationLabel' => 'voucher',
            'total' => $total,
            'canManage' => Auth::can('vouchers.manage'),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function createVoucher(): void
    {
        $this->renderVoucherEditor(null);
    }

    public function editVoucher(string $id): void
    {
        $voucherId = filter_var($id, FILTER_VALIDATE_INT);
        if ($voucherId === false || $voucherId < 1) {
            flash('error', 'Không tìm thấy voucher.');
            redirect('/admin/vouchers');
        }
        $this->renderVoucherEditor((int) $voucherId);
    }

    public function saveVoucher(?string $id = null): void
    {
        $voucherId = null;
        if ($id !== null) {
            $validatedId = filter_var($id, FILTER_VALIDATE_INT);
            if ($validatedId === false || $validatedId < 1) {
                flash('error', 'Không tìm thấy voucher cần cập nhật.');
                redirect('/admin/vouchers');
            }
            $voucherId = (int) $validatedId;
        }
        $service = new VoucherAdminService();
        $validation = $service->validateInput($_POST, $voucherId);
        if ($validation['errors']) {
            $this->renderVoucherEditor($voucherId, $_POST, $validation['errors']);
            return;
        }
        try {
            $service->save(
                $validation['values'],
                $validation['values']['category_ids'],
                $validation['values']['product_ids'],
                $voucherId,
                (int) Auth::user()['id']
            );
            flash('success', $voucherId === null ? 'Đã tạo voucher.' : 'Đã cập nhật voucher.');
            redirect('/admin/vouchers');
        } catch (Throwable $exception) {
            if ($exception instanceof PDOException && $exception->getCode() === '23000') {
                $this->renderVoucherEditor($voucherId, $_POST, [
                    'code' => 'Mã này đã được sử dụng. Hãy chọn mã khác.',
                ]);
            } elseif ($exception instanceof InvalidArgumentException) {
                $this->renderVoucherEditor($voucherId, $_POST, ['form' => $exception->getMessage()]);
            } else {
                throw $exception;
            }
        }
    }

    public function toggleVoucher(string $id): void
    {
        try {
            $status = (new VoucherAdminService())->toggle((int) $id, (int) Auth::user()['id']);
            flash('success', $status === 'active' ? 'Đã bật voucher.' : 'Đã tắt voucher.');
        } catch (InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('/admin/vouchers');
    }

    public function duplicateVoucher(string $id): void
    {
        try {
            $copyId = (new VoucherAdminService())->duplicate((int) $id, (int) Auth::user()['id']);
            flash('success', 'Đã nhân bản voucher ở trạng thái tắt. Hãy kiểm tra và sửa mã trước khi bật.');
            redirect('/admin/vouchers/' . $copyId . '/edit');
        } catch (InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
            redirect('/admin/vouchers');
        }
    }

    public function deleteVoucher(string $id): void
    {
        try {
            $deleted = (new VoucherAdminService())->delete((int) $id, (int) Auth::user()['id']);
            flash(
                $deleted ? 'success' : 'error',
                $deleted ? 'Đã xóa voucher.' : 'Voucher đã từng được sử dụng nên không thể xóa; hãy tắt voucher nếu không muốn tiếp tục áp dụng.'
            );
        } catch (InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('/admin/vouchers');
    }

    private function renderVoucherEditor(?int $voucherId, ?array $formValues = null, array $errors = []): void
    {
        $database = Database::connection();
        $voucher = null;
        $usageHistory = [];
        $walletHolders = [];
        $walletHolderTotal = 0;
        $walletHolderPage = 1;
        $walletHolderPages = 1;
        if ($voucherId !== null) {
            $query = $database->prepare(
                'SELECT v.*,
                    (SELECT GROUP_CONCAT(category_id) FROM voucher_categories WHERE voucher_id = v.id) AS category_ids,
                    (SELECT GROUP_CONCAT(product_id) FROM voucher_products WHERE voucher_id = v.id) AS product_ids
                 FROM vouchers v WHERE v.id = :id'
            );
            $query->execute(['id' => $voucherId]);
            $voucher = $query->fetch();
            if (!$voucher) {
                flash('error', 'Không tìm thấy voucher.');
                redirect('/admin/vouchers');
            }
            $history = $database->prepare(
                'SELECT o.order_no, u.name AS customer_name, vu.used_at, vu.discount_amount, vu.status
                 FROM voucher_usages vu
                 JOIN orders o ON o.id = vu.order_id
                 JOIN users u ON u.id = vu.user_id
                 WHERE vu.voucher_id = :id
                 ORDER BY vu.used_at DESC, vu.id DESC LIMIT 10'
            );
            $history->execute(['id' => $voucherId]);
            $usageHistory = $history->fetchAll();
            $holderCount = $database->prepare('SELECT COUNT(*) FROM user_vouchers WHERE voucher_id = :id');
            $holderCount->execute(['id' => $voucherId]);
            $walletHolderTotal = (int) $holderCount->fetchColumn();
            $walletHolderPages = max(1, (int) ceil($walletHolderTotal / 50));
            $requestedHolderPage = filter_var($_GET['holder_page'] ?? 1, FILTER_VALIDATE_INT);
            $walletHolderPage = min(
                max(1, $requestedHolderPage === false ? 1 : $requestedHolderPage),
                $walletHolderPages
            );
            $holders = $database->prepare(
                'SELECT uv.id AS user_voucher_id, u.name AS customer_name, uv.granted_at,
                        COALESCE(uv.expires_at, v.ends_at) AS expires_at,
                        uv.used_order_id, uv.used_at, uv.source
                 FROM user_vouchers uv
                 JOIN users u ON u.id = uv.user_id
                 JOIN vouchers v ON v.id = uv.voucher_id
                 WHERE uv.voucher_id = :id
                 ORDER BY uv.granted_at DESC, uv.id DESC LIMIT :limit OFFSET :offset'
            );
            $holders->bindValue(':id', $voucherId, PDO::PARAM_INT);
            $holders->bindValue(':limit', 50, PDO::PARAM_INT);
            $holders->bindValue(':offset', ($walletHolderPage - 1) * 50, PDO::PARAM_INT);
            $holders->execute();
            $walletHolders = $holders->fetchAll();
        }
        $this->adminView('admin/voucher-edit', [
            'title' => $voucherId === null ? 'Tạo voucher' : 'Sửa voucher',
            'voucher' => $voucher,
            'voucherId' => $voucherId,
            'formValues' => $formValues ?? $voucher ?? [],
            'errors' => $errors,
            'categories' => $database->query('SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name')->fetchAll(),
            'products' => $database->query("SELECT id, name, sku FROM products WHERE status = 'active' AND deleted_at IS NULL ORDER BY name")->fetchAll(),
            'usageHistory' => $usageHistory,
            'walletHolders' => $walletHolders,
            'walletHolderTotal' => $walletHolderTotal,
            'walletHolderPage' => $walletHolderPage,
            'walletHolderPages' => $walletHolderPages,
            'formAction' => $voucherId === null ? '/admin/vouchers' : '/admin/vouchers/' . $voucherId,
            'success' => flash('success'),
        ]);
    }

    public function voucherGift(string $id): void
    {
        $voucherId = filter_var($id, FILTER_VALIDATE_INT);
        if ($voucherId === false || $voucherId < 1) {
            flash('error', 'Không tìm thấy voucher.');
            redirect('/admin/vouchers');
        }

        $database = Database::connection();
        $voucherQuery = $database->prepare('SELECT id, code, description, audience FROM vouchers WHERE id = :id');
        $voucherQuery->execute(['id' => $voucherId]);
        $voucher = $voucherQuery->fetch();
        if (!$voucher) {
            flash('error', 'Không tìm thấy voucher.');
            redirect('/admin/vouchers');
        }
        if ($voucher['audience'] !== 'targeted') {
            flash('error', 'Chỉ voucher dành cho khách được tặng mới có thể phát hành thủ công.');
            redirect('/admin/vouchers/' . (int) $voucherId . '/edit');
        }

        $search = trim((string) ($_GET['q'] ?? ''));
        $customerSql = "SELECT id, name, email, phone FROM users WHERE role = 'customer' AND status = 'active'";
        $parameters = [];
        if ($search !== '') {
            $customerSql .= ' AND (name LIKE :name OR email LIKE :email OR phone LIKE :phone)';
            $parameters = [
                'name' => '%' . $search . '%',
                'email' => '%' . $search . '%',
                'phone' => '%' . $search . '%',
            ];
        }
        $customerSql .= ' ORDER BY name, id LIMIT 100';
        $customers = $database->prepare($customerSql);
        $customers->execute($parameters);
        $this->adminView('admin/voucher-gift', [
            'title' => 'Tặng voucher ' . $voucher['code'],
            'voucher' => $voucher,
            'customers' => $customers->fetchAll(),
            'search' => $search,
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function giftVoucher(string $id): void
    {
        $voucherId = filter_var($id, FILTER_VALIDATE_INT);
        if ($voucherId === false || $voucherId < 1) {
            flash('error', 'Không tìm thấy voucher.');
            redirect('/admin/vouchers');
        }

        try {
            $allActiveCustomers = (string) ($_POST['all_active'] ?? '') === '1';
            if ($allActiveCustomers && (string) ($_POST['confirm_all'] ?? '') !== '1') {
                throw new InvalidArgumentException('Hãy xác nhận trước khi tặng voucher cho toàn bộ khách đang hoạt động.');
            }
            $userIds = $_POST['user_ids'] ?? [];
            if (!is_array($userIds)) {
                throw new InvalidArgumentException('Danh sách khách hàng không hợp lệ.');
            }
            $result = (new VoucherWalletService())->giftToUsers(
                (int) $voucherId,
                $userIds,
                $allActiveCustomers,
                (int) Auth::user()['id']
            );
            flash('success', 'Đã tặng ' . $result['granted'] . ', bỏ qua ' . $result['skipped'] . ' khách.');
        } catch (InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('/admin/vouchers/' . (int) $voucherId . '/gift');
    }

    public function revokeVoucherHolder(string $id): void
    {
        $voucherId = filter_var($id, FILTER_VALIDATE_INT);
        $walletId = filter_var($_POST['user_voucher_id'] ?? null, FILTER_VALIDATE_INT);
        if ($voucherId === false || $voucherId < 1 || $walletId === false || $walletId < 1) {
            flash('error', 'Không tìm thấy voucher trong ví khách hàng.');
            redirect('/admin/vouchers');
        }

        try {
            (new VoucherWalletService())->revoke(
                (int) $voucherId,
                (int) $walletId,
                (int) Auth::user()['id']
            );
            flash('success', 'Đã thu hồi voucher chưa sử dụng.');
        } catch (InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('/admin/vouchers/' . (int) $voucherId . '/edit');
    }

    public function shipping(): void
    {
        $database = Database::connection();
        $total = (int) $database->query('SELECT COUNT(*) FROM shipping_fees')->fetchColumn();
        $perPage = 20;
        $pages = max(1, (int) ceil($total / $perPage));
        $requestedPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
        $page = min(max(1, $requestedPage === false ? 1 : $requestedPage), $pages);
        $feeQuery = $database->prepare(
            'SELECT sf.*, r.name AS region_name FROM shipping_fees sf JOIN regions r ON r.id = sf.region_id
             ORDER BY r.level, r.name LIMIT :limit OFFSET :offset'
        );
        $feeQuery->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $feeQuery->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $feeQuery->execute();
        $fees = $feeQuery->fetchAll();
        $regions = $database->query('SELECT id, name, level FROM regions WHERE is_active = 1 AND level IN (1, 2) ORDER BY level, name')->fetchAll();
        $minimum = $database->query("SELECT setting_value FROM settings WHERE setting_key = 'free_shipping_minimum'")->fetchColumn();
        $this->adminView('admin/shipping', [
            'title' => 'Phí vận chuyển',
            'fees' => $fees,
            'regions' => $regions,
            'freeMinimum' => $minimum === false ? '500000' : $minimum,
            'page' => $page,
            'pages' => $pages,
            'paginationUrl' => '/admin/shipping',
            'paginationQuery' => [],
            'paginationLabel' => 'mức phí vận chuyển',
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

    private function orderStatusLabel(string $status): string
    {
        return [
            'confirmed' => 'Đã xác nhận',
            'processing' => 'Đang đóng gói',
            'shipping' => 'Đang giao',
            'completed' => 'Đã giao',
            'delivery_failed' => 'Giao thất bại',
        ][$status] ?? $status;
    }

    private function adminView(string $template, array $data): void
    {
        $data['menu'] = (new AdminController())->menu();
        $this->view($template, $data, 'admin');
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
