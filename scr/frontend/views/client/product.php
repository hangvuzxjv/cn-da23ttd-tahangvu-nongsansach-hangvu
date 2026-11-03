<nav aria-label="breadcrumb"><ol class="breadcrumb"><?php foreach ($breadcrumbs as $crumb): ?><li class="breadcrumb-item"><?php if ($crumb['url']): ?><a href="<?= e(url($crumb['url'])) ?>"><?= e($crumb['name']) ?></a><?php else: ?><?= e($crumb['name']) ?><?php endif; ?></li><?php endforeach; ?></ol></nav>
<?php
$fallbackImages = [
    'rau-an-la' => '/img/products/_fallback/rau-an-la.svg',
    'rau-cu-qua' => '/img/products/_fallback/rau-cu-qua.svg',
    'cu-than-cu' => '/img/products/_fallback/cu-than-cu.svg',
    'trai-cay-demo' => '/img/products/_fallback/trai-cay-demo.svg',
    'gao-ngu-coc-demo' => '/img/products/_fallback/gao-ngu-coc-demo.svg',
    'nam-hat-cong-nghiep' => '/img/products/_fallback/nam-hat-cong-nghiep.svg',
];
$defaultImage = $fallbackImages[$product['category_slug'] ?? ''] ?? '/img/product-placeholder.svg';
$mainImage = $images[0] ?? ['image_url' => $defaultImage, 'alt_text' => $product['name']];
$certificateParts = [];
foreach ([$product['certifications'] ?? '', $product['farm_certifications'] ?? ''] as $certificate) {
    foreach (preg_split('/[,;·]+/u', (string) $certificate) as $part) {
        $part = trim(preg_replace('/\s*theo hồ sơ nông trại\s*/iu', '', $part) ?? '');
        if ($part !== '' && !in_array(mb_strtolower($part, 'UTF-8'), array_map(static fn (string $value): string => mb_strtolower($value, 'UTF-8'), $certificateParts), true)) {
            $certificateParts[] = $part;
        }
    }
}
?>
<div class="product-detail-layout mb-5">
    <div class="col-lg-6">
        <div class="product-detail-media">
            <div class="product-detail-image-frame"><img id="productMainImage" class="product-detail-image" src="<?= e(asset($mainImage['image_url'] ?: $defaultImage)) ?>" alt="<?= e($mainImage['alt_text'] ?: $product['name']) ?>"></div>
            <?php if (count($images) > 1): ?><div class="product-gallery"><?php foreach ($images as $image): ?><button class="btn p-0 border product-gallery-thumb" type="button" aria-label="Xem ảnh <?= e($image['alt_text'] ?: $product['name']) ?>" data-image="<?= e(asset($image['image_url'])) ?>" data-alt="<?= e($image['alt_text'] ?: $product['name']) ?>"><img src="<?= e(asset($image['image_url'])) ?>" alt="" loading="lazy"></button><?php endforeach; ?></div><?php endif; ?>
        </div>
    </div>
    <div class="product-detail-info">
        <div class="product-detail-kicker"><a href="<?= e(url('/danh-muc/' . $product['category_slug'])) ?>"><?= e($product['category_name']) ?></a><span><?= e($product['region_name']) ?></span></div>
        <h1 class="product-detail-title"><?= e($product['name']) ?></h1>
        <?php if ((int) $reviewSummary['review_count'] > 0): ?><div class="product-detail-rating"><?= number_format((float) $reviewSummary['average_rating'], 1) ?> / 5 · <?= (int) $reviewSummary['review_count'] ?> lượt đánh giá</div><?php endif; ?>
        <p class="product-detail-summary text-secondary"><?= e($product['short_description']) ?></p>
        <?php $regularPrice = $product['sale_price'] !== null && PricingService::moneyToVnd((string) $product['sale_price']) > 0 ? $product['sale_price'] : $product['price']; ?>
        <?php if ($flashSale): ?>
            <?php
            $flashSaleEnd = new DateTimeImmutable($flashSale['ends_at'], new DateTimeZone('Asia/Ho_Chi_Minh'));
            $flashLoginUrl = url('/login?return_to=' . rawurlencode('/san-pham/' . $product['slug']));
            ?>
            <div class="product-flash-sale" data-flash-sale-root data-server-time="<?= e($flashSaleServerTime) ?>"
                 data-progress-url="<?= e(url('/flash-sale/status')) ?>" data-has-active="1">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <strong class="text-danger">Flash Sale<?= flash_sale_display_name($flashSale['campaign_name']) !== '' ? ' · ' . e(flash_sale_display_name($flashSale['campaign_name'])) : '' ?></strong>
                    <span class="small">Kết thúc sau <strong data-countdown data-countdown-target="<?= e($flashSaleEnd->format(DATE_ATOM)) ?>">Đang đồng bộ giờ máy chủ…</strong></span>
                </div>
                <div class="mt-2" data-flash-item="<?= (int) $flashSale['flash_sale_item_id'] ?>"
                     data-quota="<?= (int) $flashSale['quota'] ?>" data-sold="<?= (int) $flashSale['sold_quantity'] ?>">
                    <del class="text-secondary"><?= number_format((float) $regularPrice, 0, ',', '.') ?> ₫</del>
                    <div class="h3 text-danger mb-1"><?= number_format((float) $flashSale['sale_price'], 0, ',', '.') ?> ₫ <span class="fs-6 text-secondary">/ <?= e($product['unit']) ?></span></div>
                    <div class="small text-secondary">Còn <span data-remaining><?= number_format((int) $flashSale['remaining']) ?></span>/<?= (int) $flashSale['quota'] ?> suất Flash Sale</div>
                    <div class="small text-danger" data-flash-poll-message role="status" aria-live="polite"></div>
                    <div class="mt-3" data-flash-action>
                        <?php if ((int) $flashSale['remaining'] < 1): ?>
                            <button class="btn btn-secondary btn-lg" type="button" disabled>Đã hết suất</button>
                        <?php elseif ((int) $product['actual_stock'] < 1): ?>
                            <button class="btn btn-secondary btn-lg" type="button" disabled>Hết tồn kho</button>
                        <?php elseif ((int) $flashSale['user_remaining'] < 1): ?>
                            <button class="btn btn-secondary btn-lg" type="button" disabled>Bạn đã đạt giới hạn mua</button>
                        <?php elseif (!$flashSaleLoggedIn): ?>
                            <a class="btn btn-danger btn-lg" href="<?= e($flashLoginUrl) ?>">Đăng nhập để mua Flash Sale</a>
                        <?php else: ?>
                            <form data-cart-add="<?= e(url('/gio-hang/them')) ?>" data-login-url="<?= e($flashLoginUrl) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                <div class="input-group product-buy-controls">
                                    <input class="form-control" type="number" name="quantity" data-max-buy="<?= (int) $flashSale['max_buy_quantity'] ?>" value="1" min="1" max="<?= (int) $flashSale['max_buy_quantity'] ?>" aria-label="Số lượng sản phẩm">
                                    <span class="input-group-text"><?= e($product['unit']) ?></span>
                                    <button class="btn btn-danger" type="submit">Thêm Flash Sale vào giỏ</button>
                                </div>
                                <div data-cart-message aria-live="polite"></div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="product-detail-price"><?php if ($product['sale_price'] !== null && PricingService::moneyToVnd((string) $product['sale_price']) > 0): ?><del><?= number_format((float) $product['price'], 0, ',', '.') ?> ₫</del><?php endif; ?><strong><?= number_format((float) $regularPrice, 0, ',', '.') ?> ₫</strong><span>/ <?= e($product['unit']) ?></span></div>
        <?php endif; ?>
        <p class="product-detail-stock <?= $stockStatus === 'Hết hàng' ? 'is-empty' : ($stockStatus === 'Sắp hết hàng' ? 'is-low' : '') ?>"><?= e($stockStatus) ?><?php if ($stockStatus !== 'Còn hàng'): ?> · <?= (int) $product['actual_stock'] ?> <?= e($product['unit']) ?><?php endif; ?></p>
        <dl class="product-detail-specs">
            <dt class="col-sm-4">Vùng miền</dt><dd class="col-sm-8"><a href="<?= e(url('/vung-mien/' . $product['region_slug'])) ?>"><?= e($product['parent_region_name'] ? $product['parent_region_name'] . ' · ' : '') ?><?= e($product['region_name']) ?></a></dd>
            <dt class="col-sm-4">Nông trại</dt><dd class="col-sm-8"><?= e($product['farm_name']) ?></dd>
            <?php if ($certificateParts): ?><dt>Chứng nhận</dt><dd><?= e(implode(' · ', $certificateParts)) ?></dd><?php endif; ?>
            <?php if ($product['suggested_shelf_life_days']): ?><dt class="col-sm-4">Hạn dùng gợi ý</dt><dd class="col-sm-8"><?= (int) $product['suggested_shelf_life_days'] ?> ngày</dd><?php endif; ?>
            <dt>Đơn vị</dt><dd><?= e($product['unit']) ?></dd>
            <?php if (!empty($traceBatches[0]['batch_code'])): ?><dt>Mã lô mới nhất</dt><dd><?= e($traceBatches[0]['batch_code']) ?></dd><?php endif; ?>
        </dl>
        <?php if (($product['season'] ?? null) || ($product['nutrition'] ?? null) || ($product['preparation'] ?? null) || ($product['tags'] ?? null)): ?>
            <div class="product-metadata">
                <?php if ($product['season'] ?? null): ?><p><strong>Mùa vụ:</strong> <?= e($product['season']) ?></p><?php endif; ?>
                <?php if ($product['nutrition'] ?? null): ?><p><strong>Dinh dưỡng:</strong> <?= e($product['nutrition']) ?></p><?php endif; ?>
                <?php if ($product['preparation'] ?? null): ?><p><strong>Sơ chế và sử dụng:</strong> <?= e($product['preparation']) ?></p><?php endif; ?>
            </div>
        <?php endif; ?>
        <?php
        $wishlistProductId = (int) $product['id'];
        $wishlistProductName = $product['name'];
        $wishlistProductSlug = $product['slug'];
        $wishlistButtonClass = 'product-detail-wishlist-inline';
        require FRONTEND_PATH . '/views/client/wishlist-button.php';
        unset($wishlistButtonClass);
        ?>
        <?php if (!$flashSale && (int) $product['actual_stock'] > 0): ?>
            <form class="product-detail-buy" data-cart-add="<?= e(url('/gio-hang/them')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                <div class="input-group product-buy-controls">
                    <input class="form-control" type="number" name="quantity" value="1" min="1" max="<?= (int) $product['actual_stock'] ?>" aria-label="Số lượng sản phẩm">
                    <span class="input-group-text"><?= e($product['unit']) ?></span>
                    <button class="btn btn-success" type="submit">Thêm vào giỏ</button>
                </div>
                <div data-cart-message aria-live="polite"></div>
            </form>
        <?php elseif (!$flashSale): ?>
            <button class="btn btn-secondary btn-lg product-detail-buy" type="button" disabled>Hết hàng</button>
        <?php endif; ?>
    </div>
</div>
<section class="product-detail-section mb-5"><h2 class="h4">Mô tả</h2><div class="product-description"><?= nl2br(e($product['description'] ?: '')) ?></div>
    <?php if ($product['storage_instructions']): ?><h3 class="h5 mt-4">Hướng dẫn bảo quản</h3><p><?= nl2br(e($product['storage_instructions'])) ?></p><?php endif; ?>
</section>
<section class="product-detail-section mb-5"><h2 class="h4">Truy xuất nguồn gốc</h2>
    <?php if ($traceBatches): ?>
        <p class="text-secondary">Các lô còn hàng, sắp xếp theo hạn dùng gần nhất để ưu tiên xuất lô cũ trước.</p>
        <div class="table-responsive"><table class="table table-bordered align-middle">
            <thead><tr><th>Mã lô</th><th>Ngày thu hoạch</th><th>Hạn dùng</th><th>Còn</th><th></th></tr></thead>
            <tbody><?php foreach ($traceBatches as $batch): ?><tr>
                <td><?= e($batch['batch_code']) ?></td>
                <td><?= e($batch['harvested_at']) ?></td>
                <td><?= e($batch['expires_at'] ?: 'Không có hạn') ?></td>
                <td><?= (int) $batch['quantity_remaining'] ?></td>
                <td>
                    <div class="trace-batch-actions">
                        <img class="trace-batch-qr border rounded p-1" src="<?= e(url('/qr/' . rawurlencode($batch['batch_code']))) ?>" alt="Mã QR truy xuất <?= e($batch['batch_code']) ?>" width="64" height="64">
                        <a class="btn btn-sm btn-outline-success" href="<?= e(url('/truy-xuat/' . rawurlencode($batch['batch_code']))) ?>">Xem nguồn gốc</a>
                    </div>
                </td>
            </tr><?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</section>
<section class="product-detail-section mb-5" id="reviews">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="h4 mb-1">Đánh giá sản phẩm</h2>
            <div class="text-secondary" aria-label="Điểm trung bình <?= number_format((float) $reviewSummary['average_rating'], 1) ?> trên 5 sao">
                <strong>Điểm trung bình <?= number_format((float) $reviewSummary['average_rating'], 1) ?>/5</strong>
                <span class="text-secondary">(<?= (int) $reviewSummary['review_count'] ?> đánh giá đã duyệt)</span>
            </div>
        </div>
    </div>
    <?php if ($reviewSummary['review_count'] > 0): ?>
        <div class="row g-2 mb-4" aria-label="Phân bố số lượt đánh giá theo sao">
            <?php for ($stars = 5; $stars >= 1; $stars--): ?>
                <?php $count = (int) $reviewSummary['distribution'][$stars]; $percent = (int) round($count * 100 / $reviewSummary['review_count']); ?>
                <div class="col-12 col-sm-6 col-lg-4 d-flex align-items-center gap-2 small">
                    <span class="text-nowrap"><?= $stars ?> sao</span>
                    <div class="progress flex-grow-1" role="progressbar" aria-label="<?= $stars ?> sao" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar bg-warning" data-progress="<?= $percent ?>"></div>
                    </div>
                    <span class="text-secondary"><?= $count ?></span>
                </div>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
    <?php if ($canReview): ?>
        <form class="card card-body mb-4" action="<?= e(url('/danh-gia')) ?>" method="post"
              data-review-submit data-login-url="<?= e($reviewLoginUrl) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
            <h3 class="h6"><?= $myReview ? 'Sửa đánh giá của bạn' : 'Chia sẻ trải nghiệm của bạn' ?></h3>
            <?php if ($myReview && $myReview['status'] !== 'published'): ?>
                <p class="small text-secondary">Đánh giá hiện ở trạng thái <?= $myReview['status'] === 'hidden' ? 'đã ẩn' : 'chờ duyệt' ?>; nội dung sửa sẽ được duyệt lại.</p>
            <?php endif; ?>
            <div class="mb-3">
                <label class="form-label" for="reviewRating">Số sao</label>
                <select class="form-select" id="reviewRating" name="rating" required>
                    <?php for ($stars = 5; $stars >= 1; $stars--): ?>
                        <option value="<?= $stars ?>" <?= $myReview && (int) $myReview['rating'] === $stars ? 'selected' : '' ?>><?= $stars ?> sao</option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="reviewContent">Nội dung</label>
                <textarea class="form-control" id="reviewContent" name="content" rows="4" maxlength="2000" required><?= e($myReview['content'] ?? '') ?></textarea>
                <div class="form-text">Tối đa 2000 ký tự. Chỉ tài khoản có đơn hàng đã giao sản phẩm này mới được đánh giá.</div>
            </div>
            <div data-review-message role="status" aria-live="polite"></div>
            <div><button class="btn btn-success" type="submit"><?= $myReview ? 'Cập nhật đánh giá' : 'Gửi đánh giá' ?></button></div>
        </form>
    <?php elseif (!$flashSaleLoggedIn): ?>
        <p><a class="btn btn-outline-success btn-sm" href="<?= e($reviewLoginUrl) ?>">Đăng nhập để đánh giá</a></p>
    <?php else: ?>
        <p class="small text-secondary">Bạn cần có đơn hàng đã giao sản phẩm này mới được đánh giá.</p>
    <?php endif; ?>
    <?php if ($reviews): ?>
        <div class="vstack gap-3">
            <?php foreach ($reviews as $review): ?>
                <article class="border-bottom pb-3">
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <strong><?= e($review['customer_name']) ?></strong>
                        <time class="small text-secondary" datetime="<?= e(date(DATE_ATOM, strtotime($review['created_at']))) ?>"><?= e($review['created_at']) ?></time>
                    </div>
                    <div class="small text-secondary">Đánh giá <?= (int) $review['rating'] ?>/5</div>
                    <?php if ($review['title']): ?><h3 class="h6 mt-1"><?= e($review['title']) ?></h3><?php endif; ?>
                    <p class="mb-0"><?= nl2br(e($review['content'])) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if ($reviewsPages > 1): ?>
            <nav class="mt-4" aria-label="Phân trang đánh giá"><ul class="pagination">
                <?php for ($number = 1; $number <= $reviewsPages; $number++): ?>
                    <li class="page-item <?= $number === $reviewsPage ? 'active' : '' ?>">
                        <a class="page-link" href="<?= e(url('/san-pham/' . $product['slug'] . '?reviews_page=' . $number . '#reviews')) ?>"><?= $number ?></a>
                    </li>
                <?php endfor; ?>
            </ul></nav>
        <?php endif; ?>
    <?php else: ?>
        <p class="text-secondary">Chưa có đánh giá được duyệt cho sản phẩm này.</p>
    <?php endif; ?>
</section>
<?php if ($recentProducts): ?>
    <section class="mb-5">
        <h2 class="h4 mb-3">Sản phẩm bạn đã xem gần đây</h2>
        <?php $products = $recentProducts; require FRONTEND_PATH . '/views/client/product-cards.php'; ?>
    </section>
<?php endif; ?>
<section><h2 class="h4 mb-3">Sản phẩm cùng nhóm</h2><?php $products = $related; require FRONTEND_PATH . '/views/client/product-cards.php'; ?></section>
