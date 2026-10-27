<div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3">
    <?php foreach ($products as $item): ?>
        <div class="col">
            <article class="card h-100 shadow-sm">
                <a href="<?= e(url('/san-pham/' . $item['slug'])) ?>" class="catalog-card-image">
                    <img src="<?= e(asset($item['image_url'] ?: '/img/product-placeholder.svg')) ?>" class="card-img-top" alt="<?= e($item['name']) ?>" loading="lazy">
                </a>
                <div class="card-body d-flex flex-column">
                    <h3 class="h6"><a class="link-dark text-decoration-none" href="<?= e(url('/san-pham/' . $item['slug'])) ?>"><?= e($item['name']) ?></a></h3>
                    <?php if (!empty($item['short_description'])): ?><p class="small text-secondary flex-grow-1"><?= e($item['short_description']) ?></p><?php endif; ?>
                    <div class="mt-auto">
                        <?php if ($item['sale_price'] !== null): ?><del class="small text-secondary"><?= number_format((float) $item['price'], 0, ',', '.') ?> ₫</del><div class="fw-bold text-danger"><?= number_format((float) $item['sale_price'], 0, ',', '.') ?> ₫</div>
                        <?php else: ?><div class="fw-bold text-success"><?= number_format((float) $item['price'], 0, ',', '.') ?> ₫</div><?php endif; ?>
                        <?php if (isset($item['unit'])): ?><span class="small text-secondary">/ <?= e($item['unit']) ?></span><?php endif; ?>
                    </div>
                </div>
            </article>
        </div>
    <?php endforeach; ?>
    <?php if (!$products): ?><div class="col-12"><p class="alert alert-light border">Chưa có sản phẩm phù hợp.</p></div><?php endif; ?>
</div>
