<div class="row row-cols-2 row-cols-lg-3 row-cols-xl-4 g-3 product-card-grid">
    <?php foreach ($products as $item): ?>
        <div class="col">
            <?php
            $fallbackImages = [
                'rau-an-la' => '/img/products/_fallback/rau-an-la.svg',
                'rau-cu-qua' => '/img/products/_fallback/rau-cu-qua.svg',
                'cu-than-cu' => '/img/products/_fallback/cu-than-cu.svg',
                'trai-cay-demo' => '/img/products/_fallback/trai-cay-demo.svg',
                'gao-ngu-coc-demo' => '/img/products/_fallback/gao-ngu-coc-demo.svg',
                'nam-hat-cong-nghiep' => '/img/products/_fallback/nam-hat-cong-nghiep.svg',
            ];
            $imageUrl = $item['image_url'] ?: ($fallbackImages[$item['category_slug'] ?? ''] ?? '/img/product-placeholder.svg');
            $hasDiscount = !empty($item['flash_sale_item_id']) || ($item['sale_price'] !== null && PricingService::moneyToVnd((string) $item['sale_price']) > 0);
            $available = (int) ($item['actual_stock'] ?? 0);
            ?>
            <article class="card h-100 shadow-sm position-relative product-card<?= $available === 0 ? ' product-card--sold-out' : '' ?>">
                <?php
                $wishlistProductId = (int) $item['id'];
                $wishlistProductName = $item['name'];
                $wishlistProductSlug = $item['slug'];
                require FRONTEND_PATH . '/views/client/wishlist-button.php';
                ?>
                <a href="<?= e(url('/san-pham/' . $item['slug'])) ?>" class="catalog-card-image">
                    <?php if ($hasDiscount): ?><span class="product-card__discount">Ưu đãi</span><?php endif; ?>
                    <img src="<?= e(asset($imageUrl)) ?>" class="card-img-top" alt="<?= e($item['name']) ?>" loading="lazy">
                </a>
                <div class="card-body d-flex flex-column">
                    <h3 class="h6 product-card__title"><a class="link-dark text-decoration-none" href="<?= e(url('/san-pham/' . $item['slug'])) ?>"><?= e($item['name']) ?></a></h3>
                    <?php $threshold = (int) ($item['min_stock'] ?? 5); ?>
                    <div class="product-card__meta">
                        <?php if (!empty($item['region_name'])): ?><span class="product-card__region"><?= e($item['region_name']) ?></span><?php endif; ?>
                        <?php if ((int) ($item['review_count'] ?? 0) > 0): ?><span class="product-card__rating" aria-label="Đánh giá <?= number_format((float) $item['average_rating'], 1) ?> trên 5"><?= number_format((float) $item['average_rating'], 1) ?> / 5 · <?= (int) $item['review_count'] ?> lượt</span><?php endif; ?>
                    </div>
                    <div class="product-card__purchase">
                        <div class="product-card__price-row">
                        <?php if (!empty($item['flash_sale_item_id'])): ?>
                            <del class="small text-secondary product-card__old-price"><?= number_format((int) $item['original_price'], 0, ',', '.') ?> ₫</del>
                            <span class="fw-bold text-danger product-card__price"><?= number_format((int) $item['current_price'], 0, ',', '.') ?> ₫</span>
                        <?php elseif ($item['sale_price'] !== null): ?><del class="small text-secondary product-card__old-price"><?= number_format((float) $item['price'], 0, ',', '.') ?> ₫</del><span class="fw-bold text-danger product-card__price"><?= number_format((float) $item['sale_price'], 0, ',', '.') ?> ₫</span>
                        <?php else: ?><span class="fw-bold text-success product-card__price"><?= number_format((float) $item['price'], 0, ',', '.') ?> ₫</span><?php endif; ?>
                        <?php if (isset($item['unit'])): ?><span class="small text-secondary product-card__unit">/ <?= e($item['unit']) ?></span><?php endif; ?>
                        <?php if ($available === 0): ?><span class="product-card__stock-status product-card__stock-status--empty">Hết hàng</span><?php elseif ($available <= $threshold): ?><span class="product-card__stock-status product-card__stock-status--low">Sắp hết</span><?php endif; ?>
                        </div>
                        <?php if ((int) ($item['actual_stock'] ?? 0) > 0): ?>
                            <form class="product-card__action" data-cart-add="<?= e(url('/gio-hang/them')) ?>" data-login-url="<?= e(url('/login?return_to=' . rawurlencode('/san-pham/' . $item['slug']))) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button class="btn btn-success" type="submit">Thêm giỏ</button>
                                <div data-cart-message aria-live="polite"></div>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-sm btn-outline-secondary product-card__action product-card__action--disabled" type="button" disabled>Hết hàng</button>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        </div>
    <?php endforeach; ?>
    <?php if (!$products): ?><div class="col-12"><p class="alert alert-light border">Chưa có sản phẩm phù hợp.</p></div><?php endif; ?>
</div>
