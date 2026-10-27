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

<section class="mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h3 mb-0">Khám phá vùng miền</h2><a href="<?= e(url('/san-pham')) ?>">Xem tất cả</a></div>
    <div class="row row-cols-1 row-cols-md-3 g-3"><?php foreach ($regions as $region): ?>
        <div class="col"><a class="region-card d-block rounded-3 p-4 text-decoration-none" href="<?= e(url('/vung-mien/' . $region['slug'])) ?>"><h3 class="h4"><?= e($region['name']) ?></h3><p class="mb-0"><?= e($region['description'] ?: 'Khám phá đặc sản từ ' . $region['name']) ?></p></a></div>
    <?php endforeach; ?></div>
</section>

<?php if ($flashSales): ?><section class="mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h3 mb-0">Flash Sale đang diễn ra</h2><span class="badge text-bg-danger">Có giới hạn</span></div>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3"><?php foreach ($flashSales as $sale): ?>
        <div class="col"><article class="card h-100 border-danger"><a class="catalog-card-image" href="<?= e(url('/san-pham/' . $sale['product_slug'])) ?>"><img class="card-img-top" src="<?= e(asset($sale['image_url'])) ?>" alt="<?= e($sale['product_name']) ?>" loading="lazy"></a><div class="card-body"><div class="small text-danger"><?= e($sale['name']) ?></div><h3 class="h6"><a class="link-dark" href="<?= e(url('/san-pham/' . $sale['product_slug'])) ?>"><?= e($sale['product_name']) ?></a></h3><strong class="text-danger"><?= number_format((float) $sale['sale_price'], 0, ',', '.') ?> ₫</strong><div class="small text-secondary mt-2">Đã bán <?= (int) $sale['sold_quantity'] ?>/<?= (int) $sale['quota'] ?> · Kết thúc <?= e($sale['ends_at']) ?></div></div></article></div>
    <?php endforeach; ?></div>
</section><?php endif; ?>

<section class="mb-5"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h3 mb-0">Sản phẩm nổi bật</h2><a href="<?= e(url('/san-pham')) ?>">Xem cửa hàng</a></div><?php $products = $featured; require FRONTEND_PATH . '/views/client/product-cards.php'; ?></section>
<section><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h3 mb-0">Sản phẩm mới</h2><a href="<?= e(url('/san-pham?sort=newest')) ?>">Xem tất cả</a></div><?php $products = $newest; require FRONTEND_PATH . '/views/client/product-cards.php'; ?></section>
