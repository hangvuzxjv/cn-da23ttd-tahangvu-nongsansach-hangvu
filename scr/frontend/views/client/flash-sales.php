<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
    <div>
        <h1 class="h2 mb-1">Flash Sale</h1>
        <p class="text-secondary mb-0">Ưu đãi có giới hạn suất; giá và tồn kho được xác nhận lại khi đặt hàng.</p>
    </div>
    <a class="btn btn-outline-success" href="<?= e(url('/san-pham')) ?>">Xem tất cả sản phẩm</a>
</div>
<?php if (!$campaigns): ?>
    <div class="alert alert-light border">Hiện chưa có chương trình Flash Sale đang diễn ra hoặc sắp bắt đầu.</div>
<?php endif; ?>
<?php
$loginReturn = (string) ($_SERVER['REQUEST_URI'] ?? '/flash-sale');
$loginUrl = url('/login?return_to=' . rawurlencode($loginReturn));
$hasActiveCampaign = (bool) array_filter(
    $campaigns,
    static fn (array $campaign): bool => $campaign['campaign_status'] === 'active'
);
?>
<div data-flash-sale-root data-server-time="<?= e($serverTime) ?>"
     data-progress-url="<?= e(url('/flash-sale/status')) ?>" data-has-active="<?= $hasActiveCampaign ? '1' : '0' ?>">
<div class="small text-danger mb-3" data-flash-poll-message role="status" aria-live="polite"></div>
<?php foreach ($campaigns as $campaign): ?>
    <?php
    $active = $campaign['campaign_status'] === 'active';
    $target = new DateTimeImmutable(
        $active ? $campaign['ends_at'] : $campaign['starts_at'],
        new DateTimeZone('Asia/Ho_Chi_Minh')
    );
    ?>
    <section class="mb-5">
        <div class="card border-<?= $active ? 'danger' : 'success' ?> mb-3">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <span class="badge text-bg-<?= $active ? 'danger' : 'success' ?>"><?= $active ? 'Đang diễn ra' : 'Sắp diễn ra' ?></span>
                    <h2 class="h4 mt-2 mb-1"><?= e($campaign['name']) ?></h2>
                    <?php if ($campaign['description']): ?><p class="text-secondary mb-0"><?= e($campaign['description']) ?></p><?php endif; ?>
                </div>
                <div class="text-md-end">
                    <div class="small text-secondary"><?= $active ? 'Kết thúc sau' : 'Bắt đầu sau' ?></div>
                    <strong class="fs-5 text-danger" data-countdown data-countdown-target="<?= e($target->format(DATE_ATOM)) ?>">Đang đồng bộ giờ máy chủ…</strong>
                </div>
            </div>
        </div>
        <?php if (!$active): ?><div class="alert alert-info py-2">Chương trình chưa bắt đầu; nút mua sẽ mở khi đến giờ theo máy chủ.</div><?php endif; ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 g-3">
            <?php foreach ($campaign['items'] as $item): ?>
                <?php
                $discountPercent = (int) $item['normal_price'] > 0
                    ? (int) round(((int) $item['normal_price'] - (int) $item['sale_price']) * 100 / (int) $item['normal_price'])
                    : 0;
                $soldPercent = (int) $item['quota'] > 0
                    ? min(100, (int) round((int) $item['sold_quantity'] * 100 / (int) $item['quota']))
                    : 0;
                $canBuy = $active && (int) $item['max_buy_quantity'] > 0;
                $disabledText = (int) $item['remaining'] < 1 ? 'Đã hết suất'
                    : ((int) $item['user_remaining'] < 1 ? 'Bạn đã đạt giới hạn mua'
                        : ((int) $item['available_stock'] < 1 ? 'Hết tồn kho' : 'Chưa thể mua'));
                ?>
                <div class="col" data-flash-item="<?= (int) $item['flash_sale_item_id'] ?>"
                     data-quota="<?= (int) $item['quota'] ?>" data-sold="<?= (int) $item['sold_quantity'] ?>">
                    <article class="card h-100 shadow-sm border-<?= $active ? 'danger' : 'secondary' ?> position-relative">
                        <?php
                        $wishlistProductId = (int) $item['product_id'];
                        $wishlistProductName = $item['product_name'];
                        $wishlistProductSlug = $item['product_slug'];
                        require FRONTEND_PATH . '/views/client/wishlist-button.php';
                        ?>
                        <a class="catalog-card-image" href="<?= e(url('/san-pham/' . $item['product_slug'])) ?>">
                            <img class="card-img-top" src="<?= e(asset($item['image_url'])) ?>" alt="<?= e($item['product_name']) ?>" loading="lazy">
                        </a>
                        <div class="card-body d-flex flex-column">
                            <h3 class="h6"><a class="link-dark text-decoration-none" href="<?= e(url('/san-pham/' . $item['product_slug'])) ?>"><?= e($item['product_name']) ?></a></h3>
                            <div class="mb-2"><del class="small text-secondary"><?= number_format((float) $item['normal_price'], 0, ',', '.') ?> ₫</del>
                                <span class="badge text-bg-danger"><?= $discountPercent ?>% giảm</span>
                                <div class="fw-bold fs-5 text-danger"><?= number_format((float) $item['sale_price'], 0, ',', '.') ?> ₫ <span class="small text-secondary">/ <?= e($item['unit']) ?></span></div>
                            </div>
                            <div class="small text-secondary">Đã bán <span data-sold-count><?= (int) $item['sold_quantity'] ?></span>/<?= (int) $item['quota'] ?></div>
                            <div class="progress mt-1 mb-3" role="progressbar" aria-label="Tiến độ Flash Sale" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $soldPercent ?>">
                                <div class="progress-bar bg-danger" data-sold-progress style="width: <?= $soldPercent ?>%"></div>
                            </div>
                            <div class="mt-auto" data-flash-action>
                                <?php if (!$active): ?>
                                    <button class="btn btn-secondary w-100" type="button" disabled>Chưa bắt đầu</button>
                                <?php elseif (!$canBuy): ?>
                                    <button class="btn btn-secondary w-100" type="button" disabled><?= e($disabledText) ?></button>
                                <?php elseif (!$loggedIn): ?>
                                    <a class="btn btn-danger w-100" href="<?= e($loginUrl) ?>">Đăng nhập để mua Flash Sale</a>
                                <?php else: ?>
                                    <form data-cart-add="<?= e(url('/gio-hang/them')) ?>" data-login-url="<?= e($loginUrl) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="product_id" value="<?= (int) $item['product_id'] ?>">
                                        <div class="input-group input-group-sm">
                                            <input class="form-control" type="number" name="quantity" data-max-buy="<?= (int) $item['max_buy_quantity'] ?>" value="1" min="1" max="<?= (int) $item['max_buy_quantity'] ?>" aria-label="Số lượng <?= e($item['product_name']) ?>">
                                            <button class="btn btn-danger" type="submit">Thêm giỏ</button>
                                        </div>
                                        <div data-cart-message aria-live="polite"></div>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>
</div>
<p class="small text-secondary">Giá Flash Sale và số suất được xác nhận lại trên máy chủ khi thêm vào giỏ và khi đặt hàng.</p>
