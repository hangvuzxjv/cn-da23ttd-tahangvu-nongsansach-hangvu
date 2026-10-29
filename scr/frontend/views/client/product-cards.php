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
                    <?php $available = (int) ($item['actual_stock'] ?? 0); $threshold = (int) ($item['min_stock'] ?? 5); ?>
                    <p class="small mb-2">Tồn khả dụng:
                        <strong class="<?= $available === 0 ? 'text-danger' : ($available <= $threshold ? 'text-warning' : 'text-success') ?>">
                            <?= $available === 0 ? 'Hết hàng' : ($available <= $threshold ? 'Sắp hết hàng' : 'Còn hàng') ?>
                        </strong>
                    </p>
                    <div class="mt-auto">
                        <?php if ($item['sale_price'] !== null): ?><del class="small text-secondary"><?= number_format((float) $item['price'], 0, ',', '.') ?> ₫</del><div class="fw-bold text-danger"><?= number_format((float) $item['sale_price'], 0, ',', '.') ?> ₫</div>
                        <?php else: ?><div class="fw-bold text-success"><?= number_format((float) $item['price'], 0, ',', '.') ?> ₫</div><?php endif; ?>
                        <?php if (isset($item['unit'])): ?><span class="small text-secondary">/ <?= e($item['unit']) ?></span><?php endif; ?>
                        <?php if ((int) ($item['actual_stock'] ?? 0) > 0): ?>
                            <form class="mt-2" data-cart-add="<?= e(url('/gio-hang/them')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>">
                                <div class="input-group input-group-sm">
                                    <input class="form-control" type="number" name="quantity" value="1" min="1" max="<?= (int) $item['actual_stock'] ?>" aria-label="Số lượng <?= e($item['name']) ?>">
                                    <button class="btn btn-success" type="submit">Thêm giỏ</button>
                                </div>
                                <div data-cart-message aria-live="polite"></div>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-sm btn-outline-secondary mt-2" type="button" disabled>Hết hàng</button>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        </div>
    <?php endforeach; ?>
    <?php if (!$products): ?><div class="col-12"><p class="alert alert-light border">Chưa có sản phẩm phù hợp.</p></div><?php endif; ?>
</div>
