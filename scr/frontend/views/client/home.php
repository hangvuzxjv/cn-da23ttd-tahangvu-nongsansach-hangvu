<?php
$banners = array_values(array_filter(
    $banners,
    static fn (array $banner): bool => (string) ($banner['image_url'] ?? '') !== '/img/banner-fresh.svg'
));
?>
<?php if ($banners): ?>
    <div id="homeBanners" class="home-banner-slider mb-5" data-home-banner-slider data-interval="6000">
        <div class="home-banner-slider__track">
            <?php foreach ($banners as $index => $banner): ?><article class="home-banner-slide<?= $index === 0 ? ' is-active' : '' ?>" data-home-banner-slide aria-hidden="<?= $index === 0 ? 'false' : 'true' ?>">
                <img class="home-banner" src="<?= e(asset($banner['image_url'] ?: '/img/product-placeholder.svg')) ?>" alt="<?= e($banner['title']) ?>" <?= $index > 0 ? 'loading="lazy"' : 'fetchpriority="high"' ?>>
                <div class="home-banner-caption"><h1><?= e($banner['title']) ?></h1><?php if (!empty($banner['description'])): ?><p><?= e($banner['description']) ?></p><?php endif; ?><?php if (!empty($banner['button_label']) && !empty($banner['link_url'])): ?><a class="btn btn-success" href="<?= e($banner['link_url']) ?>"><?= e($banner['button_label']) ?></a><?php endif; ?></div>
            </article><?php endforeach; ?>
        </div>
        <?php if (count($banners) > 1): ?>
            <div class="home-banner-slider__controls" aria-label="Điều khiển banner">
                <button class="home-banner-slider__arrow home-banner-slider__arrow--prev" type="button" data-home-banner-prev aria-label="Banner trước"></button>
                <div class="home-banner-slider__dots"><?php foreach ($banners as $index => $banner): ?><button type="button" data-home-banner-dot="<?= $index ?>" aria-label="Chuyển đến banner <?= $index + 1 ?>" class="<?= $index === 0 ? 'is-active' : '' ?>"></button><?php endforeach; ?></div>
                <button class="home-banner-slider__arrow home-banner-slider__arrow--next" type="button" data-home-banner-next aria-label="Banner tiếp theo"></button>
            </div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <section class="p-4 p-md-5 mb-5 rounded-3 home-hero">
        <div class="col-lg-7 py-4 home-hero__content"><p class="home-hero__eyebrow">Tươi ngon · Theo mùa · Rõ nguồn gốc</p><h1 class="display-5 fw-bold">Nông sản sạch, an tâm mỗi ngày</h1><p class="fs-5">Khám phá sản vật theo mùa từ các vùng miền Việt Nam, minh bạch nguồn gốc từ nông trại.</p><a class="btn btn-success btn-lg" href="<?= e(url('/san-pham')) ?>">Khám phá sản phẩm</a></div>
    </section>
<?php endif; ?>

<section class="card card-body border-success mb-5">
    <h2 class="h5">Tìm sản phẩm và truy xuất nguồn gốc</h2>
    <form class="product-search row g-2" method="get" action="<?= e(url('/tim-kiem')) ?>" data-product-search
          data-suggestions-url="<?= e(url('/san-pham/goi-y')) ?>">
        <div class="col position-relative">
            <label class="visually-hidden" for="traceLookupHome">Tên sản phẩm hoặc mã lô</label>
            <input class="form-control" id="traceLookupHome" name="q" placeholder="Nhập tên sản phẩm hoặc mã lô in trên nhãn"
                   maxlength="100" required autocomplete="off" role="combobox" aria-autocomplete="list"
                   aria-expanded="false" aria-controls="productSearchSuggestions" aria-haspopup="listbox" data-product-search-input>
            <div class="product-search__suggestions" id="productSearchSuggestions" role="listbox" hidden data-product-search-list></div>
            <div class="visually-hidden" aria-live="polite" data-product-search-status></div>
        </div>
        <div class="col-auto"><button class="btn btn-success" type="submit">Tìm kiếm</button></div>
    </form>
</section>

<section class="mb-5">
    <div class="page-section-heading"><h2 class="h3 mb-0">Danh mục nông sản</h2><a href="<?= e(url('/san-pham')) ?>">Xem tất cả</a></div>
    <div class="category-grid">
        <?php foreach ($categories as $category): ?>
            <a class="category-tile" href="<?= e(url('/danh-muc/' . $category['slug'])) ?>">
                <span><strong><?= e($category['name']) ?></strong><small><?= e($category['description'] ?: 'Khám phá sản phẩm theo mùa') ?></small></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="mb-5">
    <div class="page-section-heading"><h2 class="h3 mb-0">Khám phá vùng miền</h2><a href="<?= e(url('/san-pham')) ?>">Xem tất cả</a></div>
    <div class="row row-cols-1 row-cols-md-3 g-3"><?php foreach ($regions as $region): ?>
        <div class="col"><a class="region-card d-block rounded-3 p-4 text-decoration-none" href="<?= e(url('/vung-mien/' . $region['slug'])) ?>"><h3 class="h4"><?= e($region['name']) ?></h3><p class="mb-0"><?= e($region['description'] ?: 'Khám phá đặc sản từ ' . $region['name']) ?></p></a></div>
    <?php endforeach; ?></div>
</section>

<section class="home-voucher-panel mb-5" aria-labelledby="homeVoucherHeading">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <p class="page-eyebrow mb-1">Ưu đãi đang áp dụng</p>
            <h2 class="h3 mb-0" id="homeVoucherHeading">Voucher dành cho bạn</h2>
        </div>
        <a class="btn btn-outline-success" href="<?= e(url('/kho-voucher')) ?>">Xem kho voucher</a>
    </div>
    <?php if ($publicVouchers): ?>
        <div class="row row-cols-1 row-cols-md-3 g-3">
            <?php foreach ($publicVouchers as $voucher): ?>
                <div class="col">
                    <article class="home-voucher-card h-100">
                        <span class="voucher-code"><?= e($voucher['code']) ?></span>
                        <strong class="voucher-discount d-block mt-2"><?= e(VoucherWalletService::formatDiscount($voucher)) ?></strong>
                        <p class="small mb-1"><?= e($voucher['description']) ?></p>
                        <span class="small text-secondary">
                            <?= (int) $voucher['minimum_order'] > 0
                                ? 'Đơn từ ' . number_format(PricingService::moneyToVnd((string) $voucher['minimum_order']), 0, ',', '.') . ' đ'
                                : 'Không yêu cầu giá trị đơn tối thiểu' ?>
                        </span>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="text-secondary mb-0">Chưa có voucher công khai đang chạy. Hãy quay lại Kho voucher để xem ưu đãi mới.</p>
    <?php endif; ?>
    <?php if ($usableVoucherCount > 0): ?>
        <p class="small mt-3 mb-0">Bạn có <strong><?= (int) $usableVoucherCount ?></strong> voucher có thể dùng —
            <a href="<?= e(url('/voucher-cua-toi')) ?>">xem Voucher của tôi</a>.
        </p>
    <?php endif; ?>
</section>

<?php if ($flashSaleCampaign): ?>
    <?php
    $saleActive = $flashSaleCampaign['campaign_status'] === 'active';
    $saleTarget = new DateTimeImmutable(
        $saleActive ? $flashSaleCampaign['ends_at'] : $flashSaleCampaign['starts_at'],
        new DateTimeZone('Asia/Ho_Chi_Minh')
    );
    $saleDisplayName = flash_sale_display_name($flashSaleCampaign['name']);
    $loginReturn = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $saleLoginUrl = url('/login?return_to=' . rawurlencode($loginReturn));
    ?>
    <section class="mb-5 home-flash-sale<?= $saleActive ? '' : ' home-flash-sale--upcoming' ?>" data-flash-sale-root data-server-time="<?= e($flashSaleServerTime) ?>"
             data-progress-url="<?= e(url('/flash-sale/status')) ?>" data-has-active="<?= $saleActive ? '1' : '0' ?>">
        <?php if ($saleActive): ?>
            <div class="home-flash-sale__header">
                <div class="home-flash-sale__title">
                    <h2 class="h3 mb-0"><a href="<?= e(url('/flash-sale')) ?>"><span aria-hidden="true">⚡</span> Flash Sale</a></h2>
                    <?php if ($saleDisplayName !== ''): ?><span class="home-flash-sale__campaign-name"><?= e($saleDisplayName) ?></span><?php endif; ?>
                </div>
                <div class="home-flash-sale__timer">
                    <span class="home-flash-sale__timer-label">Kết thúc sau</span>
                    <div class="flash-sale-countdown home-flash-sale__countdown" data-countdown-target="<?= e($saleTarget->format(DATE_ATOM)) ?>" aria-label="Thời gian đến khi kết thúc">
                        <span class="flash-sale-countdown__days" data-countdown-days hidden></span>
                        <div class="flash-sale-countdown__unit">
                            <span class="flash-sale-countdown__value" data-countdown-hours>--</span>
                            <span class="flash-sale-countdown__label">Giờ</span>
                        </div>
                        <span class="flash-sale-countdown__separator" aria-hidden="true">:</span>
                        <div class="flash-sale-countdown__unit">
                            <span class="flash-sale-countdown__value" data-countdown-minutes>--</span>
                            <span class="flash-sale-countdown__label">Phút</span>
                        </div>
                        <span class="flash-sale-countdown__separator" aria-hidden="true">:</span>
                        <div class="flash-sale-countdown__unit">
                            <span class="flash-sale-countdown__value" data-countdown-seconds>--</span>
                            <span class="flash-sale-countdown__label">Giây</span>
                        </div>
                    </div>
                </div>
                <a class="home-flash-sale__all-button" href="<?= e(url('/flash-sale')) ?>">Xem tất cả</a>
            </div>
            <div class="small home-flash-sale__poll-message" data-flash-poll-message role="status" aria-live="polite"></div>
            <div class="home-flash-sale__carousel">
                <div class="home-flash-sale__carousel-controls" data-flash-carousel-controls hidden>
                    <button class="home-flash-sale__arrow" type="button" data-flash-carousel-prev aria-label="Cuộn sản phẩm sang trái">←</button>
                    <button class="home-flash-sale__arrow" type="button" data-flash-carousel-next aria-label="Cuộn sản phẩm sang phải">→</button>
                </div>
                <div class="home-flash-sale__track" data-flash-carousel role="region" aria-label="Sản phẩm Flash Sale">
                <?php foreach (array_slice($flashSaleCampaign['items'], 0, 8) as $sale): ?>
                    <?php
                    $discountPercent = (int) $sale['normal_price'] > 0
                        ? max(0, (int) round(((int) $sale['normal_price'] - (int) $sale['sale_price']) * 100 / (int) $sale['normal_price']))
                        : 0;
                    $savingAmount = max(0, (int) round((float) $sale['normal_price'] - (float) $sale['sale_price']));
                    $soldPercent = (int) $sale['quota'] > 0
                        ? min(100, (int) round((int) $sale['sold_quantity'] * 100 / (int) $sale['quota']))
                        : 0;
                    $soldOut = (int) $sale['remaining'] < 1;
                    $saleStatusKey = $soldOut ? 'sold-out'
                        : ((int) $sale['sold_quantity'] === 0 ? 'new'
                            : ((int) $sale['quota'] > 0 && (int) $sale['remaining'] / (int) $sale['quota'] <= .2 ? 'low' : ''));
                    $saleStatusLabel = $soldOut ? 'Đã hết suất'
                        : ($saleStatusKey === 'new' ? 'Mới mở bán'
                            : ($saleStatusKey === 'low' ? '🔥 Sắp hết' : ''));
                    $progressTone = $soldPercent >= 80 ? 'red' : ($soldPercent >= 50 ? 'orange' : 'green');
                    $canBuy = $saleActive && (int) $sale['max_buy_quantity'] > 0;
                    $unavailableLabel = (int) $sale['remaining'] < 1 ? 'Đã hết suất'
                        : ((int) $sale['user_remaining'] < 1 ? 'Bạn đã đạt giới hạn mua'
                            : ((int) $sale['available_stock'] < 1 ? 'Hết tồn kho' : 'Chưa thể mua'));
                    ?>
                    <div class="flash-sale-product-item home-flash-sale__carousel-item" data-flash-carousel-item data-flash-item="<?= (int) $sale['flash_sale_item_id'] ?>"
                         data-quota="<?= (int) $sale['quota'] ?>" data-sold="<?= (int) $sale['sold_quantity'] ?>">
                        <article class="card h-100 border-danger shadow-sm position-relative product-card product-card--flash-sale<?= $soldOut ? ' product-card--flash-sale-sold-out' : '' ?>">
                            <?php
                            $wishlistProductId = (int) $sale['product_id'];
                            $wishlistProductName = $sale['product_name'];
                            $wishlistProductSlug = $sale['product_slug'];
                            require FRONTEND_PATH . '/views/client/wishlist-button.php';
                            ?>
                            <a class="catalog-card-image" href="<?= e(url('/san-pham/' . $sale['product_slug'])) ?>">
                                <?php if ($discountPercent > 0): ?><span class="flash-sale-discount-badge">-<?= $discountPercent ?>%</span><?php endif; ?>
                                <img class="card-img-top" src="<?= e(asset($sale['image_url'] ?: '/img/product-placeholder.svg')) ?>" alt="<?= e($sale['product_name']) ?>" loading="lazy">
                            </a>
                            <div class="card-body d-flex flex-column">
                                <h3 class="h6 product-card__title flash-sale-product-name"><a class="link-dark text-decoration-none" href="<?= e(url('/san-pham/' . $sale['product_slug'])) ?>"><?= e($sale['product_name']) ?></a></h3>
                                <div class="product-card__meta">
                                    <?php if (!empty($sale['region_name'])): ?><span class="product-card__region"><?= e($sale['region_name']) ?></span><?php endif; ?>
                                    <span class="product-card__rating product-card__rating--empty">Flash Sale</span>
                                </div>
                                <div class="flash-sale-price-block">
                                    <div class="flash-sale-price-line">
                                        <span class="flash-sale-price"><?= number_format((float) $sale['sale_price'], 0, ',', '.') ?> ₫</span>
                                        <span class="flash-sale-unit">/ <?= e($sale['unit']) ?></span>
                                    </div>
                                    <del class="flash-sale-original-price"><?= number_format((float) $sale['normal_price'], 0, ',', '.') ?> ₫</del>
                                    <?php if ($savingAmount >= 1000): ?><div class="flash-sale-saving">Tiết kiệm <?= number_format($savingAmount, 0, ',', '.') ?> ₫</div><?php endif; ?>
                                </div>
                                <div class="flash-sale-stock-row">
                                    <span class="flash-sale-stock-count">Đã bán <span data-sold-count><?= (int) $sale['sold_quantity'] ?></span>/<?= (int) $sale['quota'] ?></span>
                                    <span class="flash-sale-stock-status" data-flash-sale-status data-status="<?= e($saleStatusKey) ?>"<?= $saleStatusKey === '' ? ' hidden' : '' ?>><?= e($saleStatusLabel) ?></span>
                                </div>
                                <div class="progress flash-sale-progress mt-1 mb-3" role="progressbar" aria-label="Tiến độ Flash Sale" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $soldPercent ?>">
                                    <div class="progress-bar flash-sale-progress__bar flash-sale-progress--<?= e($progressTone) ?>" data-sold-progress data-progress="<?= $soldPercent ?>" style="width: <?= $soldPercent ?>%"></div>
                                </div>
                                <div class="mt-auto" data-flash-action>
                                    <?php if (!$canBuy): ?>
                                        <button class="btn btn-secondary w-100" type="button" disabled><?= e($unavailableLabel) ?></button>
                                    <?php elseif (!$flashSaleLoggedIn): ?>
                                        <a class="btn btn-danger w-100 flash-sale-buy-button" href="<?= e($saleLoginUrl) ?>">Đăng nhập để mua Flash Sale</a>
                                    <?php else: ?>
                                        <form data-cart-add="<?= e(url('/gio-hang/them')) ?>" data-login-url="<?= e($saleLoginUrl) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="product_id" value="<?= (int) $sale['product_id'] ?>">
                                            <input type="hidden" name="quantity" data-max-buy="<?= (int) $sale['max_buy_quantity'] ?>" value="1">
                                            <button class="btn btn-danger flash-sale-buy-button" type="submit">Mua ngay</button>
                                            <div data-cart-message aria-live="polite"></div>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
                    <?php if (count($flashSaleCampaign['items']) >= 5): ?>
                        <a class="home-flash-sale__all-card home-flash-sale__carousel-item" data-flash-carousel-item href="<?= e(url('/flash-sale')) ?>">
                            <span>Xem tất cả Flash Sale</span>
                            <span aria-hidden="true">→</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="home-flash-sale__upcoming">
                <div class="home-flash-sale__upcoming-info">
                    <div class="home-flash-sale__title">
                        <h2 class="h3 mb-0"><a href="<?= e(url('/flash-sale')) ?>"><span aria-hidden="true">⚡</span> Flash Sale</a></h2>
                        <?php if ($saleDisplayName !== ''): ?><span class="home-flash-sale__campaign-name"><?= e($saleDisplayName) ?></span><?php endif; ?>
                    </div>
                    <p class="home-flash-sale__upcoming-caption">Chương trình sắp bắt đầu</p>
                </div>
                <div class="home-flash-sale__timer">
                    <span class="home-flash-sale__timer-label">Bắt đầu sau</span>
                    <div class="flash-sale-countdown home-flash-sale__countdown" data-countdown-target="<?= e($saleTarget->format(DATE_ATOM)) ?>" aria-label="Thời gian đến khi bắt đầu">
                        <span class="flash-sale-countdown__days" data-countdown-days hidden></span>
                        <div class="flash-sale-countdown__unit">
                            <span class="flash-sale-countdown__value" data-countdown-hours>--</span>
                            <span class="flash-sale-countdown__label">Giờ</span>
                        </div>
                        <span class="flash-sale-countdown__separator" aria-hidden="true">:</span>
                        <div class="flash-sale-countdown__unit">
                            <span class="flash-sale-countdown__value" data-countdown-minutes>--</span>
                            <span class="flash-sale-countdown__label">Phút</span>
                        </div>
                        <span class="flash-sale-countdown__separator" aria-hidden="true">:</span>
                        <div class="flash-sale-countdown__unit">
                            <span class="flash-sale-countdown__value" data-countdown-seconds>--</span>
                            <span class="flash-sale-countdown__label">Giây</span>
                        </div>
                    </div>
                </div>
                <div class="home-flash-sale__upcoming-products" aria-label="Sản phẩm sắp có Flash Sale">
                    <?php foreach (array_slice($flashSaleCampaign['items'], 0, 4) as $preview): ?>
                        <a class="home-flash-sale__preview" href="<?= e(url('/san-pham/' . $preview['product_slug'])) ?>" title="<?= e($preview['product_name']) ?>" aria-label="<?= e($preview['product_name']) ?>">
                            <img src="<?= e(asset($preview['image_url'] ?: '/img/product-placeholder.svg')) ?>" alt="" loading="lazy">
                        </a>
                    <?php endforeach; ?>
                </div>
                <a class="home-flash-sale__all-button" href="<?= e(url('/flash-sale')) ?>">Xem chi tiết</a>
            </div>
            <div class="small home-flash-sale__poll-message" data-flash-poll-message role="status" aria-live="polite"></div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<section class="mb-5"><div class="page-section-heading"><h2 class="h3 mb-0">Sản phẩm nổi bật</h2><a href="<?= e(url('/san-pham')) ?>">Xem cửa hàng</a></div><?php $products = $featured; require FRONTEND_PATH . '/views/client/product-cards.php'; ?></section>
<section class="mb-5"><div class="page-section-heading"><h2 class="h3 mb-0">Sản phẩm mới</h2><a href="<?= e(url('/san-pham?sort=newest')) ?>">Xem tất cả</a></div><?php $products = $newest; require FRONTEND_PATH . '/views/client/product-cards.php'; ?></section>
<section class="mb-5"><div class="page-section-heading"><h2 class="h3 mb-0">Sản phẩm bán chạy</h2><a href="<?= e(url('/san-pham?sort=best_selling')) ?>">Xem tất cả</a></div><?php $products = $bestselling; require FRONTEND_PATH . '/views/client/product-cards.php'; ?></section>
<?php if ($recentProducts): ?>
    <section class="mt-5">
        <div class="page-section-heading"><h2 class="h3 mb-0">Sản phẩm bạn đã xem gần đây</h2></div>
        <?php $products = $recentProducts; require FRONTEND_PATH . '/views/client/product-cards.php'; ?>
    </section>
<?php endif; ?>
<section class="trust-panel">
    <div class="page-section-heading"><div><p class="page-eyebrow">An tâm từ nông trại đến bàn ăn</p><h2 class="h3 mb-0">Vì sao chọn Nông Sản Sạch?</h2></div></div>
    <div class="trust-grid">
        <article class="trust-card"><h3 class="h5">Nguồn gốc rõ ràng</h3><p class="mb-0">Mỗi lô hàng có thông tin nông trại, ngày thu hoạch và hành trình truy xuất.</p></article>
        <article class="trust-card"><h3 class="h5">Giao hàng tận nơi</h3><p class="mb-0">Phí vận chuyển được tính minh bạch theo địa chỉ trước khi bạn xác nhận đơn.</p></article>
        <article class="trust-card"><h3 class="h5">Thanh toán linh hoạt</h3><p class="mb-0">Chọn COD, chuyển khoản VietQR hoặc thanh toán trực tuyến qua VNPay.</p></article>
    </div>
</section>
