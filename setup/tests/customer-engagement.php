<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

function engagementAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function engagementCreateUser(PDO $database): int
{
    $email = 'engagement-' . bin2hex(random_bytes(8)) . '@example.test';
    $database->prepare(
        "INSERT INTO users (name, email, phone, password, role, status)
         VALUES ('Engagement Test', :email, '0900000000', :password, 'customer', 'active')"
    )->execute([
        'email' => $email,
        'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
    ]);
    return (int) $database->lastInsertId();
}

function engagementCreateOrder(PDO $database, int $userId, int $productId, string $status): int
{
    $productQuery = $database->prepare('SELECT name, sku, unit, price FROM products WHERE id = :id');
    $productQuery->execute(['id' => $productId]);
    $product = $productQuery->fetch();
    $orderNo = 'ENGAGEMENT-' . strtoupper(bin2hex(random_bytes(8)));
    $database->prepare(
        'INSERT INTO orders
            (user_id, order_no, status, payment_status, recipient_name, recipient_phone,
             shipping_address, subtotal, total_amount)
         VALUES (:user_id, :order_no, :status, \'paid\', \'Engagement Test\',
                 \'0900000000\', \'Test address\', :subtotal, :total)'
    )->execute([
        'user_id' => $userId,
        'order_no' => $orderNo,
        'status' => $status,
        'subtotal' => $product['price'],
        'total' => $product['price'],
    ]);
    $orderId = (int) $database->lastInsertId();
    $database->prepare(
        'INSERT INTO order_items
            (order_id, product_id, product_name, sku, unit, unit_price, quantity, line_total)
         VALUES (:order_id, :product_id, :name, :sku, :unit, :unit_price, 1, :line_total)'
    )->execute([
        'order_id' => $orderId,
        'product_id' => $productId,
        'name' => $product['name'],
        'sku' => $product['sku'],
        'unit' => $product['unit'],
        'unit_price' => $product['price'],
        'line_total' => $product['price'],
    ]);
    return $orderId;
}

$database = inventoryTestDatabase();
$userIds = [];
$orderIds = [];
$reviewIds = [];
$productId = (int) $database->query(
    "SELECT id FROM products WHERE id = 4 AND status = 'active' AND deleted_at IS NULL"
)->fetchColumn();
if ($productId < 1) {
    throw new RuntimeException('Customer engagement tests require active seeded product 4.');
}

try {
    $service = new CustomerEngagementService($database);
    $userOne = engagementCreateUser($database);
    $userIds[] = $userOne;
    $pendingOrder = engagementCreateOrder($database, $userOne, $productId, 'pending');
    $orderIds[] = $pendingOrder;
    try {
        $service->saveReview($userOne, $productId, 5, 'Chưa nhận hàng');
        throw new RuntimeException('A customer without a completed order must not review.');
    } catch (InvalidArgumentException $exception) {
        engagementAssert(
            str_contains($exception->getMessage(), 'đã giao'),
            'Unpurchased review rejection should explain the completed-order requirement.'
        );
    }
    engagementAssert(
        (int) $database->query("SELECT COUNT(*) FROM reviews WHERE user_id = {$userOne} AND product_id = {$productId}")->fetchColumn() === 0,
        'An unverified review attempt must not create a review record.'
    );

    $completedOrderOne = engagementCreateOrder($database, $userOne, $productId, 'completed');
    $orderIds[] = $completedOrderOne;
    $savedOne = $service->saveReview($userOne, $productId, 5, "  Sản phẩm   rất tốt.\nĐóng gói cẩn thận.  ");
    $reviewIds[] = $savedOne['id'];
    engagementAssert($savedOne['created'] && $savedOne['status'] === 'pending', 'A verified customer review should be created pending moderation.');
    $databaseReview = $database->prepare('SELECT content FROM reviews WHERE id = :id');
    $databaseReview->execute(['id' => $savedOne['id']]);
    engagementAssert(
        $databaseReview->fetchColumn() === 'Sản phẩm rất tốt. Đóng gói cẩn thận.',
        'Review content should be trimmed and whitespace-normalized.'
    );

    $updatedOne = $service->saveReview($userOne, $productId, 3, 'Đã cập nhật đánh giá.');
    engagementAssert(!$updatedOne['created'] && $updatedOne['id'] === $savedOne['id'], 'A customer should update their existing product review.');
    $reviewIds[] = $updatedOne['id'];
    engagementAssert(
        $service->reviewSummary($productId)['review_count'] === 0,
        'Reviews pending moderation must not contribute to public summaries.'
    );
    for ($attempt = 0; $attempt < 3; $attempt++) {
        $service->saveReview($userOne, $productId, 3, 'Đã cập nhật đánh giá.');
    }
    try {
        $service->saveReview($userOne, $productId, 3, 'Gửi quá giới hạn.');
        throw new RuntimeException('Review rate limit should reject the sixth request in an hour.');
    } catch (HttpException $exception) {
        engagementAssert($exception->status() === 429, 'Review spam throttling should return HTTP 429.');
    }
    $reviewCount = $database->prepare(
        'SELECT COUNT(*) FROM reviews WHERE user_id = :user_id AND product_id = :product_id'
    );
    $reviewCount->execute(['user_id' => $userOne, 'product_id' => $productId]);
    engagementAssert((int) $reviewCount->fetchColumn() === 1, 'A customer must have at most one review per product.');

    $userTwo = engagementCreateUser($database);
    $userIds[] = $userTwo;
    $completedOrderTwo = engagementCreateOrder($database, $userTwo, $productId, 'completed');
    $orderIds[] = $completedOrderTwo;
    $savedTwo = $service->saveReview($userTwo, $productId, 5, 'Chất lượng tốt.');
    $reviewIds[] = $savedTwo['id'];
    $adminId = (int) $database->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1")->fetchColumn();
    if ($adminId < 1) {
        throw new RuntimeException('Customer engagement tests require the seeded admin account.');
    }
    $service->setReviewStatus($savedOne['id'], $adminId, 'published');
    $service->setReviewStatus($savedTwo['id'], $adminId, 'published');
    $adminList = $service->adminReviewList([
        'q' => 'Engagement Test',
        'product_id' => $productId,
        'status' => 'published',
    ]);
    engagementAssert(
        $adminList['total'] === 2 && count($adminList['reviews']) === 2,
        'Admin review search should filter by customer text, product, and status.'
    );
    $summary = $service->reviewSummary($productId);
    engagementAssert(
        $summary['review_count'] === 2 && $summary['average_rating'] === 4.0
            && $summary['distribution'][3] === 1 && $summary['distribution'][5] === 1,
        'Published review average and star distribution should be accurate.'
    );

    $service->setReviewStatus($savedOne['id'], $adminId, 'hidden');
    $summary = $service->reviewSummary($productId);
    $published = $service->publishedReviews($productId);
    engagementAssert(
        $summary['review_count'] === 1 && $summary['average_rating'] === 5.0
            && count($published['reviews']) === 1
            && (int) $published['reviews'][0]['id'] === $savedTwo['id'],
        'Hidden reviews must not appear publicly or contribute to the average.'
    );
    $auditCount = $database->prepare(
        "SELECT COUNT(*) FROM audit_logs
         WHERE user_id = :user_id AND entity_type = 'review' AND entity_id = :review_id
           AND action = 'review_status_changed'"
    );
    $auditCount->execute(['user_id' => $adminId, 'review_id' => $savedOne['id']]);
    engagementAssert((int) $auditCount->fetchColumn() === 2, 'Admin review moderation actions should be audited.');

    engagementAssert($service->toggleWishlist($userOne, $productId), 'Wishlist toggle should add the product.');
    engagementAssert(
        in_array($productId, $service->wishlistProductIds($userOne), true),
        'The added product should be present in the customer wishlist.'
    );
    $favorite = $service->wishlistProducts($userOne)[0] ?? null;
    engagementAssert(
        $favorite !== null && (int) $favorite['id'] === $productId
            && (int) $favorite['current_price'] > 0
            && array_key_exists('actual_stock', $favorite),
        'Wishlist product data should include current pricing and actual stock.'
    );
    engagementAssert(!$service->toggleWishlist($userOne, $productId), 'Wishlist toggle should remove the product.');
    engagementAssert(
        !in_array($productId, $service->wishlistProductIds($userOne), true),
        'The removed product should no longer appear in the customer wishlist.'
    );
    $service->deleteReview($savedTwo['id'], $adminId);
    engagementAssert(
        $database->query('SELECT COUNT(*) FROM reviews WHERE id = ' . (int) $savedTwo['id'])->fetchColumn() == 0,
        'Admin should be able to delete a review.'
    );

    echo "PASS: verified purchase required; review create/edit uniqueness; normalized content; per-user spam limit; published average and distribution; hidden review exclusion; wishlist add/remove." . PHP_EOL;
} finally {
    if ($reviewIds !== []) {
        $placeholders = implode(',', array_fill(0, count(array_unique($reviewIds)), '?'));
        $database->prepare('DELETE FROM reviews WHERE id IN (' . $placeholders . ')')
            ->execute(array_values(array_unique($reviewIds)));
    }
    if ($userIds !== []) {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $database->prepare('DELETE FROM orders WHERE user_id IN (' . $placeholders . ')')
            ->execute($userIds);
        $database->prepare('DELETE FROM users WHERE id IN (' . $placeholders . ')')
            ->execute($userIds);
    }
}
