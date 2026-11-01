<?php if ($banners): ?>
    <div id="homeBanners" class="carousel slide mb-5" data-bs-ride="carousel">
        <div class="carousel-inner rounded-3 overflow-hidden">
            <?php foreach ($banners as $index => $banner): ?><div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                <?php if ($banner['link_url']): ?><a href="<?= e($banner['link_url']) ?>"><?php endif; ?>
                <img class="d-block w-100 home-banner" src="<?= e(asset($banner['image_url'])) ?>" alt="<?= e($banner['title']) ?>" <?= $index > 0 ? 'loading="lazy"' : '' ?>>
                <div class="carousel-caption d-none d-md-block"><h1><?= e($banner['title']) ?></h1></div>
                <?php if ($banner['link_url']): ?></a><?php endif; ?>
            </div><?php endforeach; ?>
        </div>
        <?php if (count($banners) > 1): ?><button class="carousel-control-prev" type="button" data-bs-target="#homeBanners" data-bs-slide="prev"><span class="carousel-control-prev-icon" aria-hidden="true"></span><span class="visually-hidden">Trước</span></button><button class="carousel-control-next" type="button" data-bs-target="#homeBanners" data-bs-slide="next"><span class="carousel-control-next-icon" aria-hidden="true"></span><span class="visually-hidden">Tiếp</span></button><?php endif; ?>
    </div>
<?php else: ?>
    <section class="p-4 p-md-5 mb-5 rounded-3 home-hero">
        <div class="col-lg-7 py-4"><h1 class="display-5 fw-bold">Nông sản sạch, an tâm mỗi ngày</h1><p class="fs-5">Khám phá sản vật theo mùa từ các vùng miền Việt Nam, minh bạch nguồn gốc từ nông trại.</p><a class="btn btn-success btn-lg" href="<?= e(url('/san-pham')) ?>">Khám phá sản phẩm</a></div>
    </section>
<?php endif; ?>

<section class="card card-body border-success mb-5">
    <h2 class="h5">Tra cứu nguồn gốc nông sản</h2>
    <form class="row g-2" method="get" action="<?= e(url('/truy-xuat')) ?>">
        <div class="col"><label class="visually-hidden" for="traceLookupHome">Mã lô hàng</label><input class="form-control" id="traceLookupHome" name="batch_code" placeholder="Nhập mã lô in trên nhãn" required></div>
        <div class="col-auto"><button class="btn btn-success" type="submit">Tra cứu</button></div>
    </form>
</section>

<section class="mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h3 mb-0">Khám phá vùng miền</h2><a href="<?= e(url('/san-pham')) ?>">Xem tất cả</a></div>
    <div class="row row-cols-1 row-cols-md-3 g-3"><?php foreach ($regions as $region): ?>
        <div class="col"><a class="region-card d-block rounded-3 p-4 text-decoration-none" href="<?= e(url('/vung-mien/' . $region['slug'])) ?>"><h3 class="h4"><?= e($region['name']) ?></h3><p class="mb-0"><?= e($region['description'] ?: 'Khám phá đặc sản từ ' . $region['name']) ?></p></a></div>
    <?php endforeach; ?></div>
</section>

<?php if ($flashSaleCampaign): ?>
    <?php
    $saleActive = $flashSaleCampaign['campaign_status'] === 'active';
    $saleTarget = new DateTimeImmutable(
        $saleActive ? $flashSaleCampaign['ends_at'] : $flashSaleCampaign['starts_at'],
        new DateTimeZone('Asia/Ho_Chi_Minh')
    );
    $loginReturn = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $saleLoginUrl = url('/login?return_to=' . rawurlencode($loginReturn));
    ?>
    <section class="mb-5" data-flash-sale-root data-server-time="<?= e($flashSaleServerTime) ?>"
             data-progress-url="<?= e(url('/flash-sale/status')) ?>" data-has-active="<?= $saleActive ? '1' : '0' ?>">
        <div class="small text-danger mb-2" data-flash-poll-message role="status" aria-live="polite"></div>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h2 class="h3 mb-1"><a class="link-dark text-decoration-none" href="<?= e(url('/flash-sale')) ?>">Flash Sale · <?= e($flashSaleCampaign['name']) ?></a></h2>
                <span class="text-secondary"><?= $saleActive ? 'Kết thúc sau' : 'Bắt đầu sau' ?>:
                    <strong class="text-danger" data-countdown data-countdown-target="<?= e($saleTarget->format(DATE_ATOM)) ?>">Đang đồng bộ giờ máy chủ…</strong>
                </span>
            </div>
            <a class="btn btn-outline-success" href="<?= e(url('/flash-sale')) ?>">Xem tất cả Flash Sale</a>
        </div>
        <?php if (!$saleActive): ?><div class="alert alert-info py-2">Chương trình chưa bắt đầu; các sản phẩm chưa thể mua theo giá Flash Sale.</div><?php endif; ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3">
            <?php foreach (array_slice($flashSaleCampaign['items'], 0, 8) as $sale): ?>
                <?php
                $discountPercent = (int) $sale['normal_price'] > 0
                    ? (int) round(((int) $sale['normal_price'] - (int) $sale['sale_price']) * 100 / (int) $sale['normal_price'])
                    : 0;
                $soldPercent = (int) $sale['quota'] > 0
                    ? min(100, (int) round((int) $sale['sold_quantity'] * 100 / (int) $sale['quota']))
                    : 0;
                $canBuy = $saleActive && (int) $sale['max_buy_quantity'] > 0;
                $unavailableLabel = (int) $sale['remaining'] < 1 ? 'Đã hết suất'
                    : ((int) $sale['user_remaining'] < 1 ? 'Bạn đã đạt giới hạn mua'
                        : ((int) $sale['available_stock'] < 1 ? 'Hết tồn kho' : 'Chưa thể mua'));
                ?>
                <div class="col" data-flash-item="<?= (int) $sale['flash_sale_item_id'] ?>"
                     data-quota="<?= (int) $sale['quota'] ?>" data-sold="<?= (int) $sale['sold_quantity'] ?>">
                    <article class="card h-100 border-danger shadow-sm">
                        <a class="catalog-card-image" href="<?= e(url('/san-pham/' . $sale['product_slug'])) ?>">
                            <img class="card-img-top" src="<?= e(asset($sale['image_url'])) ?>" alt="<?= e($sale['product_name']) ?>" loading="lazy">
                        </a>
                        <div class="card-body d-flex flex-column">
                            <div class="small text-danger"><?= e($flashSaleCampaign['name']) ?></div>
                            <h3 class="h6"><a class="link-dark" href="<?= e(url('/san-pham/' . $sale['product_slug'])) ?>"><?= e($sale['product_name']) ?></a></h3>
                            <div class="mb-2"><del class="small text-secondary"><?= number_format((float) $sale['normal_price'], 0, ',', '.') ?> ₫</del>
                                <span class="badge text-bg-danger"><?= $discountPercent ?>% giảm</span>
                                <div class="fw-bold fs-5 text-danger"><?= number_format((float) $sale['sale_price'], 0, ',', '.') ?> ₫ <span class="small text-secondary">/ <?= e($sale['unit']) ?></span></div>
                            </div>
                            <div class="small text-secondary">Đã bán <span data-sold-count><?= (int) $sale['sold_quantity'] ?></span>/<?= (int) $sale['quota'] ?></div>
                            <div class="progress mt-1 mb-3" role="progressbar" aria-label="Tiến độ Flash Sale" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $soldPercent ?>">
                                <div class="progress-bar bg-danger" data-sold-progress style="width: <?= $soldPercent ?>%"></div>
                            </div>
                            <div class="mt-auto" data-flash-action>
                                <?php if (!$saleActive): ?>
                                    <button class="btn btn-secondary w-100" type="button" disabled>Chưa bắt đầu</button>
                                <?php elseif (!$canBuy): ?>
                                    <button class="btn btn-secondary w-100" type="button" disabled><?= e($unavailableLabel) ?></button>
                                <?php elseif (!$flashSaleLoggedIn): ?>
                                    <a class="btn btn-danger w-100" href="<?= e($saleLoginUrl) ?>">Đăng nhập để mua Flash Sale</a>
                                <?php else: ?>
                                    <form data-cart-add="<?= e(url('/gio-hang/them')) ?>" data-login-url="<?= e($saleLoginUrl) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="product_id" value="<?= (int) $sale['product_id'] ?>">
                                        <div class="input-group input-group-sm">
                                            <input class="form-control" type="number" name="quantity" data-max-buy="<?= (int) $sale['max_buy_quantity'] ?>" value="1" min="1" max="<?= (int) $sale['max_buy_quantity'] ?>" aria-label="Số lượng <?= e($sale['product_name']) ?>">
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
<?php endif; ?>

<section class="mb-5"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h3 mb-0">Sản phẩm nổi bật</h2><a href="<?= e(url('/san-pham')) ?>">Xem cửa hàng</a></div><?php $products = $featured; require FRONTEND_PATH . '/views/client/product-cards.php'; ?></section>
<section><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h3 mb-0">Sản phẩm mới</h2><a href="<?= e(url('/san-pham?sort=newest')) ?>">Xem tất cả</a></div><?php $products = $newest; require FRONTEND_PATH . '/views/client/product-cards.php'; ?></section>
