<?php

declare(strict_types=1);

/**
 * Nạp dữ liệu mẫu cho các chức năng thương mại và tương tác.
 *
 * Hàm được gọi trong transaction của load.php. Dữ liệu có tiền tố DEMO-
 * hoặc email @example.test riêng để có thể dựng lại mà không ảnh hưởng dữ liệu thật.
 */
function loadCommerceSampleData(PDO $pdo, bool $remove = false): void
{
    $customerEmails = [];
    for ($index = 1; $index <= 10; $index++) {
        $customerEmails[] = sprintf('demo.customer%02d@example.test', $index);
    }

    $orderIds = $pdo->query("SELECT id FROM orders WHERE order_no LIKE 'DEMO-ORDER-%'")->fetchAll(PDO::FETCH_COLUMN);
    if ($orderIds) {
        $marks = implode(',', array_fill(0, count($orderIds), '?'));
        foreach ([
            "DELETE FROM flash_sale_purchases WHERE order_id IN ($marks)",
            "DELETE FROM voucher_usages WHERE order_id IN ($marks)",
            "DELETE FROM stock_reservations WHERE order_id IN ($marks)",
            "DELETE FROM order_status_history WHERE order_id IN ($marks)",
            "DELETE FROM payments WHERE order_id IN ($marks)",
            "DELETE FROM inventory_movements WHERE reference_type = 'demo_order' AND reference_id IN ($marks)",
            "DELETE FROM order_items WHERE order_id IN ($marks)",
            "DELETE FROM orders WHERE id IN ($marks)",
        ] as $sql) {
            $pdo->prepare($sql)->execute($orderIds);
        }
    }

    $pdo->exec("DELETE FROM flash_sale_purchases WHERE flash_sale_item_id IN (SELECT id FROM flash_sale_items WHERE flash_sale_id IN (SELECT id FROM flash_sales WHERE name LIKE 'DEMO-%'))");
    $pdo->exec("DELETE FROM flash_sale_items WHERE flash_sale_id IN (SELECT id FROM flash_sales WHERE name LIKE 'DEMO-%')");
    $pdo->exec("DELETE FROM flash_sales WHERE name LIKE 'DEMO-%'");
    $pdo->exec("DELETE FROM voucher_categories WHERE voucher_id IN (SELECT id FROM vouchers WHERE code LIKE 'DEMO-%')");
    $pdo->exec("DELETE FROM voucher_products WHERE voucher_id IN (SELECT id FROM vouchers WHERE code LIKE 'DEMO-%')");
    $pdo->exec("DELETE FROM vouchers WHERE code LIKE 'DEMO-%'");
    $pdo->exec("DELETE FROM banners WHERE title LIKE '[DEMO]%'");
    $pdo->exec("DELETE FROM pages WHERE slug LIKE 'demo-%'");
    $pdo->exec("DELETE FROM contacts WHERE email LIKE 'demo.contact%@example.test'");
    $pdo->exec("DELETE FROM notifications WHERE title LIKE '[DEMO]%'");

    if ($remove) {
        $marks = implode(',', array_fill(0, count($customerEmails), '?'));
        $users = $pdo->prepare("SELECT id FROM users WHERE email IN ($marks)");
        $users->execute($customerEmails);
        $userIds = $users->fetchAll(PDO::FETCH_COLUMN);
        if ($userIds) {
            $userMarks = implode(',', array_fill(0, count($userIds), '?'));
            $pdo->prepare("DELETE FROM carts WHERE user_id IN ($userMarks)")->execute($userIds);
            $pdo->prepare("DELETE FROM addresses WHERE user_id IN ($userMarks)")->execute($userIds);
            $pdo->prepare("DELETE FROM users WHERE id IN ($userMarks)")->execute($userIds);
        }
        $pdo->exec("UPDATE products p SET total_stock = (SELECT COALESCE(SUM(quantity_remaining), 0) FROM product_batches b WHERE b.product_id = p.id)");
        return;
    }

    $passwordHash = password_hash('NongSanDemo!2026#Viet', PASSWORD_DEFAULT);
    $users = [
        ['demo.customer01@example.test', 'Nguyễn Minh Anh', '0905000001', 'active', true],
        ['demo.customer02@example.test', 'Trần Quốc Bảo', '0905000002', 'active', true],
        ['demo.customer03@example.test', 'Lê Hoàng Châu', '0905000003', 'active', true],
        ['demo.customer04@example.test', 'Phạm Gia Hân', '0905000004', 'active', false],
        ['demo.customer05@example.test', 'Võ Đức Khải', '0905000005', 'active', true],
        ['demo.customer06@example.test', 'Đặng Ngọc Lan', '0905000006', 'active', false],
        ['demo.customer07@example.test', 'Bùi Thanh Nam', '0905000007', 'active', true],
        ['demo.customer08@example.test', 'Huỳnh Mai Phương', '0905000008', 'active', true],
        ['demo.customer09@example.test', 'Đỗ Nhật Quang', '0905000009', 'inactive', false],
        ['demo.customer10@example.test', 'Ngô Khánh Vy', '0905000010', 'banned', true],
    ];
    $upsertUser = $pdo->prepare(
        'INSERT INTO users (name, email, phone, password, role, status, email_verified_at)
         VALUES (:name, :email, :phone, :password, "customer", :status, :verified)
         ON DUPLICATE KEY UPDATE name=VALUES(name), phone=VALUES(phone), password=VALUES(password),
             role="customer", status=VALUES(status), email_verified_at=VALUES(email_verified_at)'
    );
    $customerIds = [];
    foreach ($users as $user) {
        $upsertUser->execute([
            'name' => $user[1], 'email' => $user[0], 'phone' => $user[2], 'password' => $passwordHash,
            'status' => $user[3], 'verified' => $user[4] ? date('Y-m-d H:i:s') : null,
        ]);
        $find = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $find->execute([$user[0]]);
        $customerIds[] = (int) $find->fetchColumn();
    }
    $customerMarks = implode(',', array_fill(0, count($customerIds), '?'));
    foreach ([
        "DELETE FROM reviews WHERE user_id IN ($customerMarks)",
        "DELETE FROM wishlists WHERE user_id IN ($customerMarks)",
        "DELETE FROM notifications WHERE user_id IN ($customerMarks) AND title LIKE '[DEMO]%'",
        "DELETE FROM carts WHERE user_id IN ($customerMarks)",
        "DELETE FROM addresses WHERE user_id IN ($customerMarks)",
    ] as $sql) {
        $pdo->prepare($sql)->execute($customerIds);
    }

    $demoAccounts = [
        ['admin@example.com', 'Quản trị viên', 'admin'],
        ['sales@example.com', 'Nhân viên bán hàng', 'staff_sales'],
        ['warehouse@example.com', 'Nhân viên kho', 'staff_warehouse'],
    ];
    foreach ($demoAccounts as [$email, $name, $role]) {
        $pdo->prepare('UPDATE users SET name = ?, password = ?, role = ?, status = "active", email_verified_at = ? WHERE email = ?')
            ->execute([$name, $passwordHash, $role, date('Y-m-d H:i:s'), $email]);
    }

    $regions = [];
    foreach (['ha-noi', 'moc-chau', 'da-lat', 'buon-ma-thuot', 'tien-giang', 'ben-tre', 'vinh-long', 'dong-thap', 'an-giang', 'binh-phuoc'] as $slug) {
        $query = $pdo->prepare('SELECT id, name FROM regions WHERE slug = ? LIMIT 1');
        $query->execute([$slug]);
        $row = $query->fetch();
        if ($row) {
            $regions[] = [(int) $row['id'], (string) $row['name']];
        }
    }
    $addressRows = [
        ['18 phố Hàng Bông', 'Phường Hàng Bông', 0], ['42 đường Lê Lợi', 'Thị trấn Mộc Châu', 1],
        ['9 đường Trần Phú', 'Phường 3', 2], ['27 đường Nguyễn Tất Thành', 'Phường Tân Lợi', 3],
        ['116 đường Ấp Bắc', 'Phường 5', 4], ['31 đường Đồng Khởi', 'Phường An Hội', 5],
        ['68 đường Phạm Thái Bường', 'Phường 4', 6], ['15 đường Nguyễn Huệ', 'Phường 1', 7],
        ['8 đường Nguyễn Trãi', 'Phường Mỹ Long', 8], ['55 đường Nguyễn Tất Thành', 'Phường Tân Xuân', 9],
    ];
    $insertAddress = $pdo->prepare(
        'INSERT INTO addresses (user_id, recipient_name, phone, region_id, address_line, ward, is_default)
         VALUES (:user_id, :name, :phone, :region_id, :line, :ward, :default)'
    );
    foreach ($customerIds as $index => $userId) {
        $addressCount = $index < 6 ? 2 : 1;
        for ($addressIndex = 0; $addressIndex < $addressCount; $addressIndex++) {
            $row = $addressRows[($index + $addressIndex) % count($addressRows)];
            $regionName = $regions ? $regions[$row[2] % count($regions)] : [null, ''];
            $insertAddress->execute([
                'user_id' => $userId, 'name' => $users[$index][1], 'phone' => $users[$index][2],
                'region_id' => $regionName[0], 'line' => $row[0], 'ward' => $row[1], 'default' => $addressIndex === 0 ? 1 : 0,
            ]);
        }
    }

    $products = $pdo->query(
        "SELECT p.id, p.name, p.sku, p.unit, p.price, p.category_id
         FROM products p
         JOIN product_batches b ON b.product_id = p.id
         WHERE p.sku LIKE 'NS-DEMO-%' AND b.batch_code LIKE 'DEMO-BATCH-%' AND b.quantity_remaining >= 3
         ORDER BY p.id"
    )->fetchAll();
    if (count($products) < 10) {
        throw new RuntimeException('Cần nạp catalog DEMO trước khi nạp dữ liệu thương mại.');
    }
    $productById = [];
    foreach ($products as $product) {
        $productById[(int) $product['id']] = $product;
    }
    $batches = [];
    $batchQuery = $pdo->query("SELECT id, product_id, quantity_remaining FROM product_batches WHERE batch_code LIKE 'DEMO-BATCH-%' ORDER BY id");
    foreach ($batchQuery->fetchAll() as $batch) {
        $batches[(int) $batch['product_id']] = ['id' => (int) $batch['id'], 'remaining' => (int) $batch['quantity_remaining']];
    }

    $now = new DateTimeImmutable('now');
    $campaigns = [
        ['DEMO-Flash Sale đang diễn ra', '-2 hours', '+5 days', 'active'],
        ['DEMO-Flash Sale cuối tuần', '+3 days', '+8 days', 'scheduled'],
        ['DEMO-Flash Sale tháng tới', '+12 days', '+15 days', 'scheduled'],
        ['DEMO-Flash Sale rau củ tháng trước', '-35 days', '-30 days', 'ended'],
        ['DEMO-Flash Sale trái cây cuối tháng', '-22 days', '-18 days', 'ended'],
    ];
    $flashItems = [];
    foreach ($campaigns as $campaignIndex => $campaign) {
        $insert = $pdo->prepare('INSERT INTO flash_sales (name, description, starts_at, ends_at, status) VALUES (?, ?, ?, ?, ?)');
        $insert->execute([
            $campaign[0], 'Ưu đãi theo mùa, số lượng có hạn và được cập nhật theo đơn mẫu.',
            $now->modify($campaign[1])->format('Y-m-d H:i:s'), $now->modify($campaign[2])->format('Y-m-d H:i:s'), $campaign[3],
        ]);
        $saleId = (int) $pdo->lastInsertId();
        $itemCount = $campaignIndex === 0 ? 5 : ($campaignIndex < 3 ? 3 : 4);
        for ($itemIndex = 0; $itemIndex < $itemCount; $itemIndex++) {
            $product = $products[($campaignIndex * 5 + $itemIndex) % count($products)];
            $quota = $campaignIndex === 0 ? ($itemIndex === 0 ? 2 : 5 + $itemIndex) : 20 + $itemIndex * 5;
            $item = $pdo->prepare('INSERT INTO flash_sale_items (flash_sale_id, product_id, sale_price, quota, sold_quantity, per_user_limit, sort_order) VALUES (?, ?, ?, ?, 0, 2, ?)');
            $item->execute([$saleId, $product['id'], max(1000, (int) $product['price'] - 5000), $quota, $itemIndex + 1]);
            $flashItems[] = ['id' => (int) $pdo->lastInsertId(), 'product_id' => (int) $product['id'], 'campaign' => $campaignIndex, 'sold' => 0];
        }
    }

    $shippingFees = [30000, 35000, 30000, 35000, 25000, 25000, 28000];
    $orderStatuses = ['pending', 'confirmed', 'processing', 'shipping', 'completed', 'cancelled', 'refunded'];
    $paymentMethods = ['cod', 'bank_transfer', 'vnpay'];
    $orderRows = [];
    for ($index = 0; $index < 36; $index++) {
        $customerIndex = $index % count($customerIds);
        $productA = $products[$index % count($products)];
        $productB = $products[($index + 7) % count($products)];
        $quantityA = ($index % 4 === 0) ? 2 : 1;
        $quantityB = ($index % 3 === 0) ? 1 : 0;
        $subtotal = (int) $productA['price'] * $quantityA + (int) $productB['price'] * $quantityB;
        $discount = $index % 5 === 0 ? min(30000, (int) floor($subtotal * 0.1)) : 0;
        $shipping = $subtotal >= 500000 ? 0 : $shippingFees[$index % count($shippingFees)];
        $status = $orderStatuses[$index % count($orderStatuses)];
        $method = $paymentMethods[$index % count($paymentMethods)];
        $paymentStatus = in_array($status, ['completed', 'shipping'], true) ? 'paid' : ($status === 'refunded' ? 'refunded' : ($method === 'cod' ? 'pending' : 'paid'));
        $orderRows[] = compact('customerIndex', 'productA', 'productB', 'quantityA', 'quantityB', 'subtotal', 'discount', 'shipping', 'status', 'method', 'paymentStatus');
    }

    $insertOrder = $pdo->prepare(
        'INSERT INTO orders (user_id, order_no, status, payment_status, recipient_name, recipient_phone, shipping_address, region_name,
         subtotal, shipping_fee, discount_amount, total_amount, voucher_code, region_id, idempotency_token, placed_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insertItem = $pdo->prepare('INSERT INTO order_items (order_id, product_id, flash_sale_item_id, batch_id, product_name, sku, unit, unit_price, quantity, line_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $insertMovement = $pdo->prepare("INSERT INTO inventory_movements (product_id, batch_id, movement_type, quantity, reference_type, reference_id, performed_by, notes, balance_after, unit_cost) VALUES (?, ?, 'out', ?, 'demo_order', ?, ?, ?, ?, ?)");
    $insertHistory = $pdo->prepare('INSERT INTO order_status_history (order_id, status, note, changed_by, created_at) VALUES (?, ?, ?, ?, ?)');
    $insertPayment = $pdo->prepare('INSERT INTO payments (order_id, method, amount, status, transaction_ref, paid_at) VALUES (?, ?, ?, ?, ?, ?)');
    $insertPurchase = $pdo->prepare('INSERT INTO flash_sale_purchases (flash_sale_item_id, user_id, order_id, quantity, status, purchased_at) VALUES (?, ?, ?, ?, "confirmed", ?)');
    $createdOrders = [];
    foreach ($orderRows as $index => $row) {
        $customer = $users[$row['customerIndex']];
        $region = $regions[$index % max(1, count($regions))] ?? [null, 'Toàn quốc'];
        $placedAt = $now->modify('-' . max(1, 59 - intdiv($index * 59, 35)) . ' days')->format('Y-m-d H:i:s');
        $orderNo = sprintf('DEMO-ORDER-%02d', $index + 1);
        $total = $row['subtotal'] + $row['shipping'] - $row['discount'];
        $voucherCode = $row['discount'] > 0 ? 'DEMO-GIAM10' : null;
        $insertOrder->execute([
            $customerIds[$row['customerIndex']], $orderNo, $row['status'], $row['paymentStatus'], $customer[1], $customer[2],
            $addressRows[$row['customerIndex'] % count($addressRows)][0] . ', ' . $region[1], $region[1],
            $row['subtotal'], $row['shipping'], $row['discount'], $total, $voucherCode, $region[0],
            hash('sha256', $orderNo), $placedAt,
        ]);
        $orderId = (int) $pdo->lastInsertId();
        $createdOrders[] = $orderId;
        $items = [[$row['productA'], $row['quantityA']], [$row['productB'], $row['quantityB']]];
        foreach ($items as $itemIndex => [$product, $quantity]) {
            if ($quantity === 0) {
                continue;
            }
            $productId = (int) $product['id'];
            if (!isset($batches[$productId]) || $batches[$productId]['remaining'] < $quantity) {
                throw new RuntimeException('Không đủ tồn kho lô DEMO cho đơn ' . $orderNo . '.');
            }
            $flashItemId = null;
            if ($itemIndex === 0 && $index % 4 === 0) {
                foreach ($flashItems as $flashItemIndex => $flashItem) {
                    if ($flashItem['product_id'] === $productId && $flashItem['campaign'] <= 4 && $flashItem['sold'] + $quantity <= 80) {
                        $flashItemId = $flashItem['id'];
                        if (!in_array($row['status'], ['cancelled', 'refunded'], true)) {
                            $flashItems[$flashItemIndex]['sold'] += $quantity;
                        }
                        break;
                    }
                }
            }
            $lineTotal = (int) $product['price'] * $quantity;
            $insertItem->execute([$orderId, $productId, $flashItemId, $batches[$productId]['id'], $product['name'], $product['sku'], $product['unit'], $product['price'], $quantity, $lineTotal]);
            $batches[$productId]['remaining'] -= $quantity;
            $insertMovement->execute([$productId, $batches[$productId]['id'], -$quantity, $orderId, 2, 'Xuất lô cho đơn dữ liệu mẫu.', $batches[$productId]['remaining'], max(1000, (int) $product['price'] * 0.6)]);
            if ($flashItemId !== null && !in_array($row['status'], ['cancelled', 'refunded'], true)) {
                $insertPurchase->execute([$flashItemId, $customerIds[$row['customerIndex']], $orderId, $quantity, $placedAt]);
            }
        }
        $historyStatuses = ['pending'];
        $statusPosition = array_search($row['status'], $orderStatuses, true);
        if ($statusPosition !== false && $statusPosition > 0) {
            $historyStatuses = array_slice($orderStatuses, 0, $statusPosition + 1);
        }
        foreach ($historyStatuses as $historyStatus) {
            $insertHistory->execute([$orderId, $historyStatus, 'Cập nhật trạng thái đơn mẫu.', 3, $placedAt]);
        }
        $paidAt = $row['paymentStatus'] === 'paid' ? $placedAt : null;
        $insertPayment->execute([$orderId, $row['method'], $total, $row['paymentStatus'] === 'refunded' ? 'refunded' : $row['paymentStatus'], 'DEMO-PAY-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT), $paidAt]);
    }

    foreach ($flashItems as $flashItem) {
        $pdo->prepare('UPDATE flash_sale_items SET sold_quantity = ? WHERE id = ?')->execute([$flashItem['sold'], $flashItem['id']]);
    }
    $voucherData = [
        ['DEMO-GIAM10', 'Giảm 10% cho đơn từ 200.000 đồng.', 'percent', 10, 200000, 30, 2, 0, '-1 day', '+30 days', 'active'],
        ['DEMO-GIAM50K', 'Giảm 50.000 đồng cho nhóm trái cây.', 'fixed', 50000, 300000, 20, 1, 0, '-1 day', '+30 days', 'active'],
        ['DEMO-HET-HAN', 'Mã mẫu đã hết hạn.', 'fixed', 20000, 0, 10, 1, 0, '-30 days', '-1 day', 'inactive'],
        ['DEMO-HET-LUOT', 'Mã mẫu đã dùng hết lượt.', 'fixed', 10000, 0, 1, 1, 1, '-1 day', '+30 days', 'active'],
    ];
    foreach ($voucherData as $voucher) {
        $pdo->prepare('INSERT INTO vouchers (code, description, discount_type, discount_value, minimum_order, usage_limit, per_user_limit, used_count, starts_at, ends_at, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$voucher[0], $voucher[1], $voucher[2], $voucher[3], $voucher[4], $voucher[5], $voucher[6], $voucher[7], $now->modify($voucher[8])->format('Y-m-d H:i:s'), $now->modify($voucher[9])->format('Y-m-d H:i:s'), $voucher[10]]);
    }
    $voucherId = (int) $pdo->query("SELECT id FROM vouchers WHERE code = 'DEMO-GIAM10'")->fetchColumn();
    $pdo->prepare('INSERT INTO voucher_usages (voucher_id, user_id, order_id, discount_amount) VALUES (?, ?, ?, ?)')
        ->execute([$voucherId, $customerIds[0], $createdOrders[0], $orderRows[0]['discount']]);

    $insertReview = $pdo->prepare('INSERT INTO reviews (user_id, product_id, order_id, rating, title, content, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    for ($index = 0; $index < 72; $index++) {
        $orderId = $createdOrders[$index % count($createdOrders)];
        $product = $products[$index % count($products)];
        $rating = 3 + ($index % 3);
        $reviewStatus = $index % 11 === 0 ? 'pending' : 'published';
        $insertReview->execute([
            $customerIds[$index % count($customerIds)], $product['id'], $orderId, $rating,
            $rating === 5 ? 'Tươi và đúng mô tả' : 'Đóng gói cẩn thận',
            $rating === 5 ? 'Sản phẩm tươi, giao nhanh, gia đình tôi rất hài lòng.' : 'Chất lượng ổn định, thông tin truy xuất rõ ràng.',
            $reviewStatus, $now->modify('-' . ($index + 1) . ' days')->format('Y-m-d H:i:s'),
        ]);
    }
    foreach (range(0, 5) as $index) {
        $pdo->prepare('INSERT IGNORE INTO wishlists (user_id, product_id) VALUES (?, ?)')->execute([$customerIds[$index], $products[($index + 3) % count($products)]['id']]);
    }
    foreach (range(0, 4) as $index) {
        $pdo->prepare('INSERT INTO carts (user_id, status) VALUES (?, "active")')->execute([$customerIds[$index]]);
        $cartId = (int) $pdo->lastInsertId();
        $product = $products[($index + 10) % count($products)];
        $pdo->prepare('INSERT INTO cart_items (cart_id, product_id, quantity, price_snapshot) VALUES (?, ?, ?, ?)')->execute([$cartId, $product['id'], $index + 1, $product['price']]);
    }
    $insertNotification = $pdo->prepare('INSERT INTO notifications (user_id, title, message, link_url, read_at) VALUES (?, ?, ?, ?, ?)');
    foreach (range(0, 7) as $index) {
        $insertNotification->execute([$index < 6 ? $customerIds[$index] : 2, '[DEMO] ' . ($index % 2 ? 'Flash Sale mới' : 'Đơn hàng đã cập nhật'), 'Thông tin mẫu để kiểm tra trạng thái đã đọc và chưa đọc.', '/don-hang', $index % 3 === 0 ? $now->format('Y-m-d H:i:s') : null]);
    }
    $contacts = [
        ['Nguyễn Hà', 'demo.contact01@example.test', 'Hỏi về nguồn gốc', 'Tôi muốn biết thêm thông tin vùng trồng.', 'new'],
        ['Trần Nam', 'demo.contact02@example.test', 'Đổi địa chỉ nhận hàng', 'Nhờ cửa hàng hỗ trợ cập nhật địa chỉ.', 'processing'],
        ['Lê Mai', 'demo.contact03@example.test', 'Tư vấn bảo quản', 'Sản phẩm nên được bảo quản như thế nào?', 'replied'],
        ['Phạm An', 'demo.contact04@example.test', 'Phản hồi giao hàng', 'Đơn hàng đã được giao đầy đủ.', 'closed'],
        ['Võ Duy', 'demo.contact05@example.test', 'Hỏi Flash Sale', 'Khi nào chương trình tiếp theo bắt đầu?', 'new'],
        ['Đỗ Vy', 'demo.contact06@example.test', 'Xuất hóa đơn', 'Tôi cần thông tin cho đơn mua hàng.', 'processing'],
    ];
    $insertContact = $pdo->prepare('INSERT INTO contacts (name, email, subject, message, status, created_at) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($contacts as $index => $contact) {
        $insertContact->execute([...$contact, $now->modify('-' . ($index + 2) . ' days')->format('Y-m-d H:i:s')]);
    }
    $pages = [
        ['demo-gioi-thieu', 'Giới thiệu Nông Sản Sạch', '<p>Nông Sản Sạch kết nối người tiêu dùng với nông trại và hợp tác xã, ưu tiên nguồn gốc rõ ràng, mùa vụ minh bạch và đóng gói phù hợp.</p>'],
        ['demo-chinh-sach', 'Chính sách mua hàng', '<p>Đơn hàng được xác nhận qua hệ thống. Khách hàng kiểm tra sản phẩm khi nhận và liên hệ trong 24 giờ nếu có vấn đề về số lượng hoặc chất lượng.</p>'],
        ['demo-huong-dan-mua-hang', 'Hướng dẫn mua hàng', '<p>Chọn sản phẩm, thêm vào giỏ, nhập địa chỉ, chọn phương thức thanh toán và theo dõi trạng thái trong tài khoản.</p>'],
        ['demo-lien-he', 'Liên hệ', '<p>Đội ngũ hỗ trợ tiếp nhận câu hỏi về sản phẩm, đơn hàng và truy xuất nguồn gốc trong giờ hành chính.</p>'],
    ];
    $insertPage = $pdo->prepare('INSERT INTO pages (title, slug, content, status, published_at) VALUES (?, ?, ?, "published", ?)');
    foreach ($pages as $page) {
        $insertPage->execute([$page[1], $page[0], $page[2], $now->format('Y-m-d H:i:s')]);
    }
    $banners = [
        ['[DEMO] Mùa rau củ tươi', 'Rau củ theo mùa, giao nhanh trong ngày.', '/img/banners/2.jpg', 'Xem rau củ', '/san-pham?category=rau-cu'],
        ['[DEMO] Trái cây miền nhiệt đới', 'Chọn trái cây từ vùng trồng có thông tin truy xuất.', '/img/banners/4.jpg', 'Khám phá trái cây', '/san-pham?category=trai-cay'],
        ['[DEMO] Ưu đãi cuối tuần', 'Mã giảm giá dành cho đơn hàng đủ điều kiện.', '/img/banners/5.jpg', 'Xem ưu đãi', '/flash-sale'],
    ];
    $insertBanner = $pdo->prepare('INSERT INTO banners (title, description, image_url, button_label, link_url, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, "active")');
    foreach ($banners as $index => $banner) {
        $insertBanner->execute([...$banner, $index + 20]);
    }
    $pdo->exec("UPDATE products p SET total_stock = (SELECT COALESCE(SUM(quantity_remaining), 0) FROM product_batches b WHERE b.product_id = p.id) WHERE p.sku LIKE 'NS-DEMO-%'");
}
