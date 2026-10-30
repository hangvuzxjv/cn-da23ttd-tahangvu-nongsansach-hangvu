<?php

declare(strict_types=1);

final class CheckoutController extends Controller
{
    public function cart(): void
    {
        $summary = (new CartService())->summary($this->userId());
        $this->view('client/cart', [
            'title' => 'Giỏ hàng',
            'cart' => $summary,
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function addToCart(): Response
    {
        try {
            $summary = (new CartService())->add(
                (int) ($_POST['product_id'] ?? 0),
                (int) ($_POST['quantity'] ?? 0),
                $this->userId()
            );
            return Response::json(true, 'Đã thêm sản phẩm vào giỏ.', [
                'cart_count' => $this->itemCount($summary),
                'subtotal' => $summary['subtotal'],
                'formatted_subtotal' => number_format($summary['subtotal'], 0, ',', '.') . ' ₫',
            ]);
        } catch (InventoryException | InvalidArgumentException $exception) {
            return Response::json(false, $exception->getMessage(), [], 422);
        }
    }

    public function updateCart(): Response
    {
        try {
            $service = new CartService();
            $productId = (int) ($_POST['product_id'] ?? 0);
            $summary = match ((string) ($_POST['action'] ?? 'update')) {
                'remove' => $service->remove($productId, $this->userId()),
                default => $service->setQuantity($productId, (int) ($_POST['quantity'] ?? 0), $this->userId()),
            };
            return Response::json(true, 'Giỏ hàng đã được cập nhật.', [
                'cart_count' => $this->itemCount($summary),
                'subtotal' => $summary['subtotal'],
                'formatted_subtotal' => number_format($summary['subtotal'], 0, ',', '.') . ' ₫',
            ]);
        } catch (InventoryException | InvalidArgumentException $exception) {
            return Response::json(false, $exception->getMessage(), [], 422);
        }
    }

    public function checkout(): void
    {
        $user = Auth::user();
        $paymentService = new PaymentService();
        $cart = (new CartService())->summary((int) $user['id']);
        if (!$cart['items']) {
            flash('error', 'Giỏ hàng đang trống.');
            redirect('/gio-hang');
        }
        Session::start();
        if (!isset($_SESSION['_checkout_token'])) {
            $_SESSION['_checkout_token'] = bin2hex(random_bytes(32));
        }
        $addressesQuery = Database::connection()->prepare(
            'SELECT a.*, r.name AS region_name FROM addresses a
             JOIN regions r ON r.id = a.region_id
             WHERE a.user_id = :user_id AND r.is_active = 1 ORDER BY a.is_default DESC, a.id DESC'
        );
        $addressesQuery->execute(['user_id' => $user['id']]);
        $addresses = $addressesQuery->fetchAll();
        $regions = Database::connection()->query(
            'SELECT id, name FROM regions WHERE level = 2 AND is_active = 1 ORDER BY name'
        )->fetchAll();
        $defaultAddress = null;
        foreach ($addresses as $address) {
            if ((int) $address['is_default'] === 1) {
                $defaultAddress = (int) $address['id'];
                break;
            }
        }
        $oldInput = flash('checkout_old') ?? [];
        $selectedRegionId = null;
        $targetAddressId = (int) ($oldInput['address_id'] ?? $defaultAddress ?? 0);
        foreach ($addresses as $address) {
            if ((int) $address['id'] === $targetAddressId) {
                $selectedRegionId = (int) $address['region_id'];
                break;
            }
        }
        if ($selectedRegionId === null && !empty($oldInput['region_id'])) {
            $selectedRegionId = (int) $oldInput['region_id'];
        }
        $initialQuote = null;
        if ($selectedRegionId && array_filter($cart['items'], static fn (array $item): bool => !$item['valid']) === []) {
            try {
                $initialQuote = (new PricingService())->quote(
                    $this->validPricingItems($cart['items']),
                    $selectedRegionId,
                    trim((string) ($oldInput['voucher_code'] ?? '')) ?: null,
                    (int) $user['id']
                );
            } catch (PricingException | InventoryException) {
                $initialQuote = null;
            }
        }
        $this->view('client/checkout', [
            'title' => 'Thanh toán',
            'cart' => $cart,
            'addresses' => $addresses,
            'regions' => $regions,
            'defaultAddress' => $defaultAddress,
            'checkoutToken' => $_SESSION['_checkout_token'],
            'errors' => flash('checkout_errors') ?? [],
            'oldInput' => $oldInput,
            'initialQuote' => $initialQuote,
            'paymentMethods' => $paymentService->enabledMethods(),
        ]);
    }

    public function previewVoucher(): Response
    {
        try {
            $cart = (new CartService())->summary($this->userId());
            $regionId = filter_var($_POST['region_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$regionId) {
                throw new PricingException('Chọn địa chỉ nhận hàng để tính phí vận chuyển.');
            }
            $items = $this->validPricingItems($cart['items']);
            $quote = (new PricingService())->quote(
                $items,
                (int) $regionId,
                trim((string) ($_POST['voucher_code'] ?? '')) ?: null,
                $this->userId()
            );
            return Response::json(true, 'Đã tính lại tổng tiền.', $this->formatQuote($quote));
        } catch (PricingException | InventoryException | InvalidArgumentException $exception) {
            return Response::json(false, $exception->getMessage(), [], 422);
        }
    }

    public function placeOrder(): void
    {
        $user = Auth::user();
        $input = $_POST;
        Session::start();
        $submittedToken = (string) ($input['idempotency_token'] ?? '');
        if (preg_match('/\A[a-f0-9]{64}\z/', $submittedToken) !== 1) {
            $this->checkoutFailure('Phiên đặt hàng đã dùng hoặc không hợp lệ. Hãy tải lại trang thanh toán.', $input);
        }
        $tokenIsCurrent = isset($_SESSION['_checkout_token'])
            && hash_equals((string) $_SESSION['_checkout_token'], $submittedToken);
        if (!$tokenIsCurrent) {
            $alreadyPlaced = Database::connection()->prepare(
                'SELECT id FROM orders WHERE user_id = :user_id AND idempotency_token = :token'
            );
            $alreadyPlaced->execute(['user_id' => $user['id'], 'token' => $submittedToken]);
            $existingOrderId = $alreadyPlaced->fetchColumn();
            if ($existingOrderId !== false) {
                $this->redirectAfterPlace((int) $existingOrderId, (int) $user['id']);
            }
            $this->checkoutFailure('Phiên đặt hàng đã hết hạn. Hãy tải lại trang thanh toán.', $input);
        }
        try {
            $result = (new OrderService())->place((int) $user['id'], $input);
        } catch (InventoryException | PricingException | InvalidArgumentException $exception) {
            $this->checkoutFailure($exception->getMessage(), $input);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $this->checkoutFailure('Yêu cầu này đã được xử lý. Kiểm tra danh sách đơn hàng của bạn.', $input);
            }
            throw $exception;
        }
        unset($_SESSION['_checkout_token']);
        if (empty($result['existing'])) {
            $this->sendConfirmation($result, $user);
        }
        $this->redirectAfterPlace((int) $result['id'], (int) $user['id']);
    }

    public function success(string $id): void
    {
        $query = Database::connection()->prepare(
            'SELECT * FROM orders WHERE id = :id AND user_id = :user_id'
        );
        $query->execute(['id' => (int) $id, 'user_id' => $this->userId()]);
        $order = $query->fetch();
        if (!$order) {
            http_response_code(404);
            View::renderError(404);
            return;
        }
        if ($order['payment_status'] === 'pending') {
            $this->redirectAfterPlace((int) $id, $this->userId() ?? 0);
        }
        $items = $this->orderItems((int) $id);
        $paymentQuery = Database::connection()->prepare('SELECT * FROM payments WHERE order_id = :id ORDER BY id DESC LIMIT 1');
        $paymentQuery->execute(['id' => (int) $id]);
        $this->view('client/order-success', [
            'title' => 'Đặt hàng thành công',
            'order' => $order,
            'items' => $items,
            'payment' => $paymentQuery->fetch() ?: null,
            'emailWarning' => flash('error'),
        ]);
    }

    public function paymentWaiting(string $id): void
    {
        $orderId = (int) $id;
        $service = new PaymentService();
        $query = Database::connection()->prepare(
            'SELECT o.* FROM orders o WHERE o.id = :id AND o.user_id = :user_id'
        );
        $query->execute(['id' => $orderId, 'user_id' => $this->userId()]);
        $order = $query->fetch();
        if (!$order) {
            http_response_code(404);
            View::renderError(404);
            return;
        }
        $service->expireForOrder($orderId);
        $query->execute(['id' => $orderId, 'user_id' => $this->userId()]);
        $order = $query->fetch();
        $payment = $service->ownedPendingPayment($orderId, $this->userId() ?? 0, 'bank_transfer');
        if (!$payment || $order['payment_status'] !== 'pending') {
            redirect('/tai-khoan/don-hang/' . $orderId);
        }
        $settings = $service->settings();
        $this->view('client/payment-waiting', [
            'title' => 'Chờ thanh toán ' . $order['order_no'],
            'order' => $order,
            'payment' => $payment,
            'qrSvg' => VietQr::svg(
                $settings['vietqr_bank_bin'],
                $settings['vietqr_account_number'],
                $settings['vietqr_account_name'],
                $order['order_no'],
                PricingService::moneyToVnd((string) $order['total_amount'])
            ),
            'bankSettings' => $settings,
            'deadline' => $service->paymentDeadline($orderId),
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function uploadPaymentProof(string $id): void
    {
        try {
            (new PaymentService())->saveProof(
                (int) $id,
                $this->userId() ?? 0,
                $_FILES['proof'] ?? []
            );
            flash('success', 'Đã gửi biên lai. Đơn hàng đang chờ quản trị viên xác nhận.');
        } catch (InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('/tai-khoan/don-hang/' . (int) $id . '/thanh-toan');
    }

    public function retryVnpay(string $id): void
    {
        try {
            $url = (new PaymentService())->createVnpayAttempt((int) $id, $this->userId() ?? 0);
            redirect($url);
        } catch (InvalidArgumentException | RuntimeException $exception) {
            flash('error', $exception->getMessage());
            redirect('/tai-khoan/don-hang/' . (int) $id);
        }
    }

    public function vnpayReturn(): void
    {
        $result = (new PaymentService())->handleVnpay($_GET, 'return');
        $reference = (string) ($_GET['vnp_TxnRef'] ?? '');
        $query = Database::connection()->prepare(
            "SELECT o.id, o.user_id FROM payments p JOIN orders o ON o.id = p.order_id
             WHERE p.method = 'vnpay' AND p.transaction_ref = :reference LIMIT 1"
        );
        $query->execute(['reference' => $reference]);
        $order = $query->fetch();
        if (!$order || (int) $order['user_id'] !== ($this->userId() ?? 0)) {
            flash('error', $result['message']);
            redirect('/tai-khoan/don-hang');
        }
        flash($result['success'] ?? false ? 'success' : 'error', $result['message']);
        redirect('/tai-khoan/don-hang/' . (int) $order['id']);
    }

    public function vnpayIpn(): Response
    {
        try {
            $result = (new PaymentService())->handleVnpay($_GET, 'ipn');
            $messages = [
                '00' => 'Confirm Success',
                '01' => 'Order not found',
                '04' => 'Invalid amount or order information',
                '97' => 'Invalid signature',
            ];
            $message = $messages[$result['code']] ?? 'Processing error';
            return new Response(
                json_encode(['RspCode' => $result['code'], 'Message' => $message], JSON_THROW_ON_ERROR),
                200,
                ['Content-Type' => 'application/json; charset=UTF-8', 'Cache-Control' => 'no-store']
            );
        } catch (Throwable $exception) {
            error_log('VNPay IPN failed: ' . $exception->getMessage());
            return new Response(
                '{"RspCode":"99","Message":"Unknown error"}',
                200,
                ['Content-Type' => 'application/json; charset=UTF-8', 'Cache-Control' => 'no-store']
            );
        }
    }

    public function myOrders(): void
    {
        (new PaymentService())->expirePending();
        $status = (string) ($_GET['status'] ?? '');
        $allowedStatuses = ['pending', 'confirmed', 'processing', 'shipping', 'completed', 'cancelled', 'refunded'];
        $where = 'user_id = :user_id';
        $parameters = ['user_id' => $this->userId()];
        if (in_array($status, $allowedStatuses, true)) {
            $where .= ' AND status = :status';
            $parameters['status'] = $status;
        } else {
            $status = '';
        }
        $database = Database::connection();
        $count = $database->prepare('SELECT COUNT(*) FROM orders WHERE ' . $where);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $pages = max(1, (int) ceil($total / 10));
        $page = min($page, $pages);
        $query = $database->prepare(
            'SELECT o.*, (SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id) AS item_count,
                    (SELECT p.method FROM payments p WHERE p.order_id = o.id ORDER BY p.id DESC LIMIT 1) AS payment_method
             FROM orders o WHERE ' . $where . ' ORDER BY placed_at DESC LIMIT 10 OFFSET :offset'
        );
        foreach ($parameters as $key => $value) {
            $query->bindValue(':' . $key, $value);
        }
        $query->bindValue(':offset', ($page - 1) * 10, PDO::PARAM_INT);
        $query->execute();
        $this->view('client/orders', [
            'title' => 'Đơn hàng của tôi',
            'orders' => $query->fetchAll(),
            'status' => $status,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function myOrder(string $id): void
    {
        $query = Database::connection()->prepare('SELECT * FROM orders WHERE id = :id AND user_id = :user_id');
        $query->execute(['id' => (int) $id, 'user_id' => $this->userId()]);
        $order = $query->fetch();
        if (!$order) {
            http_response_code(404);
            View::renderError(404);
            return;
        }
        (new PaymentService())->expireForOrder((int) $id);
        $query->execute(['id' => (int) $id, 'user_id' => $this->userId()]);
        $order = $query->fetch();
        $paymentQuery = Database::connection()->prepare('SELECT * FROM payments WHERE order_id = :id ORDER BY id DESC LIMIT 1');
        $paymentQuery->execute(['id' => (int) $id]);
        $order['payment'] = $paymentQuery->fetch() ?: null;
        $this->view('client/order-detail', [
            'title' => 'Chi tiết ' . $order['order_no'],
            'order' => $order,
            'items' => $this->orderItems((int) $id),
            'history' => $this->orderHistory((int) $id),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function cancelMyOrder(string $id): void
    {
        try {
            (new OrderService())->cancel((int) $id, $this->userId(), trim((string) ($_POST['reason'] ?? 'Khách hàng hủy đơn')));
            flash('success', 'Đã hủy đơn hàng và hoàn lại tồn kho/mã giảm giá nếu có.');
        } catch (InventoryException | InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('/tai-khoan/don-hang/' . (int) $id);
    }

    public function reorder(string $id): void
    {
        $query = Database::connection()->prepare(
            'SELECT i.product_id, i.quantity, p.status, p.deleted_at
             FROM order_items i JOIN orders o ON o.id = i.order_id
             LEFT JOIN products p ON p.id = i.product_id
             WHERE i.order_id = :id AND o.user_id = :user_id'
        );
        $query->execute(['id' => (int) $id, 'user_id' => $this->userId()]);
        $cart = new CartService();
        $added = 0;
        foreach ($query->fetchAll() as $item) {
            if (!$item['product_id'] || $item['status'] !== 'active' || $item['deleted_at'] !== null) {
                continue;
            }
            try {
                $cart->add((int) $item['product_id'], (int) $item['quantity'], $this->userId());
                $added++;
            } catch (InventoryException | InvalidArgumentException) {
                continue;
            }
        }
        flash($added > 0 ? 'success' : 'error', $added > 0 ? 'Các sản phẩm còn bán đã được thêm vào giỏ.' : 'Không có sản phẩm nào trong đơn cũ còn bán và đủ hàng.');
        redirect('/gio-hang');
    }

    private function validPricingItems(array $items): array
    {
        $valid = [];
        foreach ($items as $item) {
            if (!$item['valid']) {
                throw new InventoryException('"' . $item['name'] . '": ' . $item['message']);
            }
            $valid[] = [
                'product_id' => (int) $item['product_id'],
                'category_id' => (int) $item['category_id'],
                'quantity' => (int) $item['quantity'],
                'unit_price' => (string) $item['current_price'],
            ];
        }
        return $valid;
    }

    private function formatQuote(array $quote): array
    {
        return [
            'subtotal' => $quote['subtotal'],
            'discount' => $quote['discount'],
            'shipping_fee' => $quote['shipping_fee'],
            'total' => $quote['total'],
            'formatted_subtotal' => number_format($quote['subtotal'], 0, ',', '.') . ' ₫',
            'formatted_discount' => number_format($quote['discount'], 0, ',', '.') . ' ₫',
            'formatted_shipping' => number_format($quote['shipping_fee'], 0, ',', '.') . ' ₫',
            'formatted_total' => number_format($quote['total'], 0, ',', '.') . ' ₫',
        ];
    }

    private function checkoutFailure(string $message, array $input): never
    {
        flash('checkout_errors', ['checkout' => $message]);
        flash('checkout_old', array_intersect_key($input, array_flip(['address_id', 'recipient_name', 'phone', 'region_id', 'address_line', 'ward', 'voucher_code', 'customer_note', 'payment_method'])));
        redirect('/thanh-toan');
    }

    private function redirectAfterPlace(int $orderId, int $userId): never
    {
        $query = Database::connection()->prepare(
            'SELECT o.payment_status, p.method FROM orders o
             LEFT JOIN payments p ON p.order_id = o.id
             WHERE o.id = :id AND o.user_id = :user_id ORDER BY p.id DESC LIMIT 1'
        );
        $query->execute(['id' => $orderId, 'user_id' => $userId]);
        $order = $query->fetch();
        if ($order && $order['payment_status'] === 'pending') {
            if ($order['method'] === 'bank_transfer') {
                redirect('/tai-khoan/don-hang/' . $orderId . '/thanh-toan');
            }
            if ($order['method'] === 'vnpay') {
                try {
                    redirect((new PaymentService())->createVnpayAttempt($orderId, $userId));
                } catch (InvalidArgumentException | RuntimeException $exception) {
                    flash('error', $exception->getMessage());
                    redirect('/tai-khoan/don-hang/' . $orderId);
                }
            }
        }
        redirect('/dat-hang-thanh-cong/' . $orderId);
    }

    private function sendConfirmation(array $result, array $user): void
    {
        $orderQuery = Database::connection()->prepare('SELECT total_amount FROM orders WHERE id = :id');
        $orderQuery->execute(['id' => $result['id']]);
        $total = (string) $orderQuery->fetchColumn();
        $config = require BACKEND_PATH . '/config/config.php';
        $paymentDescription = match ($result['payment_method'] ?? 'cod') {
            'bank_transfer' => 'Chuyển khoản VietQR: vui lòng hoàn tất thanh toán trong thời hạn giữ hàng.',
            'vnpay' => 'VNPay sandbox: vui lòng hoàn tất thanh toán trong thời hạn giữ hàng.',
            default => 'Thanh toán khi nhận hàng (COD).',
        };
        try {
            MailerFactory::make($config['mailer'] ?? ['driver' => 'log'])->send(
                (string) $user['email'],
                'Xác nhận đơn hàng ' . $result['order_no'],
                "Xin chào {$user['name']},\n\nĐơn hàng {$result['order_no']} đã được tạo thành công.\nTổng thanh toán: " . number_format(PricingService::moneyToVnd($total), 0, ',', '.') . " ₫\n{$paymentDescription}\n\nTheo dõi đơn tại: " . absolute_url('/tai-khoan/don-hang/' . $result['id'])
            );
        } catch (Throwable $exception) {
            error_log('Order confirmation email failed for ' . $result['order_no'] . ': ' . $exception->getMessage());
            flash('error', 'Đơn hàng đã được lưu nhưng email xác nhận chưa gửi được. Mã đơn: ' . $result['order_no']);
        }
    }

    private function orderItems(int $orderId): array
    {
        $query = Database::connection()->prepare(
            'SELECT i.*, b.batch_code, p.slug AS product_slug
             FROM order_items i LEFT JOIN product_batches b ON b.id = i.batch_id
             LEFT JOIN products p ON p.id = i.product_id
             WHERE i.order_id = :id ORDER BY i.id'
        );
        $query->execute(['id' => $orderId]);
        return $query->fetchAll();
    }

    private function orderHistory(int $orderId): array
    {
        $query = Database::connection()->prepare(
            'SELECT h.*, u.name AS changed_by_name FROM order_status_history h
             LEFT JOIN users u ON u.id = h.changed_by WHERE h.order_id = :id ORDER BY h.created_at, h.id'
        );
        $query->execute(['id' => $orderId]);
        return $query->fetchAll();
    }

    private function itemCount(array $summary): int
    {
        return array_sum(array_map(static fn (array $item): int => (int) $item['quantity'], $summary['items']));
    }

    private function userId(): ?int
    {
        $user = Auth::user();
        return $user === null ? null : (int) $user['id'];
    }

}
