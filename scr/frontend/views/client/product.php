<nav aria-label="breadcrumb"><ol class="breadcrumb"><?php foreach ($breadcrumbs as $crumb): ?><li class="breadcrumb-item"><?php if ($crumb['url']): ?><a href="<?= e(url($crumb['url'])) ?>"><?= e($crumb['name']) ?></a><?php else: ?><?= e($crumb['name']) ?><?php endif; ?></li><?php endforeach; ?></ol></nav>
<div class="row g-4 mb-5">
    <div class="col-lg-6">
        <?php $mainImage = $images[0] ?? ['image_url' => '/img/product-placeholder.svg', 'alt_text' => $product['name']]; ?>
        <img id="productMainImage" class="img-fluid rounded border w-100 product-detail-image" src="<?= e(asset($mainImage['image_url'])) ?>" alt="<?= e($mainImage['alt_text'] ?: $product['name']) ?>">
        <?php if (count($images) > 1): ?><div class="d-flex gap-2 mt-2 overflow-auto"><?php foreach ($images as $image): ?><button class="btn p-0 border product-gallery-thumb" type="button" data-image="<?= e(asset($image['image_url'])) ?>" data-alt="<?= e($image['alt_text'] ?: $product['name']) ?>"><img src="<?= e(asset($image['image_url'])) ?>" alt="" loading="lazy"></button><?php endforeach; ?></div><?php endif; ?>
    </div>
    <div class="col-lg-6">
        <div class="text-success mb-2"><a href="<?= e(url('/danh-muc/' . $product['category_slug'])) ?>"><?= e($product['category_name']) ?></a></div>
        <h1 class="h2"><?= e($product['name']) ?></h1>
        <p class="text-secondary"><?= e($product['short_description']) ?></p>
        <p class="mb-1"><?php if ($product['sale_price'] !== null): ?><del class="text-secondary"><?= number_format((float) $product['price'], 0, ',', '.') ?> ₫</del><?php endif; ?></p>
        <div class="h3 text-danger"><?= number_format((float) ($product['sale_price'] ?? $product['price']), 0, ',', '.') ?> ₫ <span class="fs-6 text-secondary">/ <?= e($product['unit']) ?></span></div>
        <p>Tình trạng: <strong class="<?= $stockStatus === 'Hết hàng' ? 'text-danger' : ($stockStatus === 'Sắp hết hàng' ? 'text-warning' : 'text-success') ?>"><?= e($stockStatus) ?></strong> (<?= (int) $product['actual_stock'] ?>)</p>
        <dl class="row">
            <dt class="col-sm-4">Vùng miền</dt><dd class="col-sm-8"><a href="<?= e(url('/vung-mien/' . $product['region_slug'])) ?>"><?= e($product['parent_region_name'] ? $product['parent_region_name'] . ' · ' : '') ?><?= e($product['region_name']) ?></a></dd>
            <dt class="col-sm-4">Nông trại</dt><dd class="col-sm-8"><?= e($product['farm_name']) ?></dd>
            <?php if ($product['certifications'] || $product['farm_certifications']): ?><dt class="col-sm-4">Chứng nhận</dt><dd class="col-sm-8"><?= e(implode(' · ', array_filter([$product['certifications'], $product['farm_certifications']]))) ?></dd><?php endif; ?>
            <?php if ($product['suggested_shelf_life_days']): ?><dt class="col-sm-4">Hạn dùng gợi ý</dt><dd class="col-sm-8"><?= (int) $product['suggested_shelf_life_days'] ?> ngày</dd><?php endif; ?>
        </dl>
        <button class="btn btn-success btn-lg" type="button" disabled title="Chức năng giỏ hàng sẽ được triển khai ở bước tiếp theo">Thêm vào giỏ</button>
        <p class="small text-secondary mt-2">Chức năng đặt hàng đang được hoàn thiện.</p>
    </div>
</div>
<section class="mb-5"><h2 class="h4">Mô tả sản phẩm</h2><div class="product-description"><?= nl2br(e($product['description'] ?: 'Thông tin sản phẩm đang được cập nhật.')) ?></div>
    <?php if ($product['storage_instructions']): ?><h3 class="h5 mt-4">Hướng dẫn bảo quản</h3><p><?= nl2br(e($product['storage_instructions'])) ?></p><?php endif; ?>
</section>
<section class="mb-5"><h2 class="h4">Truy xuất nguồn gốc</h2><p class="text-secondary">Thông tin truy xuất theo lô hàng sẽ được hiển thị tại đây.</p></section>
<section class="mb-5"><h2 class="h4">Đánh giá</h2><p class="text-secondary">Đánh giá sản phẩm sẽ được hiển thị tại đây.</p></section>
<section><h2 class="h4 mb-3">Sản phẩm liên quan</h2><?php $products = $related; require FRONTEND_PATH . '/views/client/product-cards.php'; ?></section>
<script>
document.querySelectorAll('[data-image]').forEach((button) => button.addEventListener('click', () => {
    const main = document.getElementById('productMainImage');
    main.src = button.dataset.image;
    main.alt = button.dataset.alt;
}));
</script>
