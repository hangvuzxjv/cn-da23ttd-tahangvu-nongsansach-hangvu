<div class="row row-cols-2 row-cols-sm-3 row-cols-lg-4 g-3 product-card-grid">
    <?php foreach ($products as $item): ?>
        <div class="col">
            <?php
            $imageUrl = $item['image_url'] ?: '/public/images/no-image.svg';
            $hasFlashSale = !empty($item['flash_sale_item_id']);
            $regularPrice = PricingService::moneyToVnd((string) $item['price']);
            $salePrice = $item['sale_price'] !== null
                ? PricingService::moneyToVnd((string) $item['sale_price'])
                : 0;
            $hasSalePrice = $salePrice > 0 && $salePrice < $regularPrice;
            $available = (int) ($item['actual_stock'] ?? 0);
            $saleUnit = trim((string) ($item['sale_unit'] ?? ''));
            $unitLabel = ProductUnitFormatter::shortLabel(
                $saleUnit !== '' ? $saleUnit : (string) $item['unit'],
                $saleUnit !== '' && $item['pack_size'] !== null ? (float) $item['pack_size'] : null,
                $saleUnit !== '' ? ($item['pack_size_unit'] ?? null) : null
            );
            $displayPrice = $hasFlashSale
                ? $item['current_price']
                : ($hasSalePrice ? $item['sale_price'] : $item['price']);
            $displayPriceVnd = PricingService::moneyToVnd((string) $displayPrice);
            $originalPrice = $hasFlashSale
                ? ($item['original_price'] ?? $item['price'])
                : ($hasSalePrice ? $item['price'] : null);
            $originalPriceVnd = $originalPrice !== null
                ? PricingService::moneyToVnd((string) $originalPrice)
                : 0;
            $hasDiscount = $originalPriceVnd > $displayPriceVnd;
            $discountPercent = $hasDiscount
                ? (int) round(($originalPriceVnd - $displayPriceVnd) * 100 / $originalPriceVnd)
                : 0;
            $referencePrice = $saleUnit !== ''
                ? ProductUnitFormatter::referencePriceLabel(
                    $displayPrice,
                    $saleUnit,
                    $item['pack_size'] !== null ? (float) $item['pack_size'] : null,
                    $item['pack_size_unit'] ?? null
                )
                : null;
            $displayPriceParts = explode(' / ', ProductUnitFormatter::priceLabel($displayPrice, $unitLabel), 2);
            $oldPriceParts = $originalPrice !== null
                ? explode(' / ', ProductUnitFormatter::priceLabel($originalPrice, $unitLabel), 2)
                : [];
            $name = (string) $item['name'];
            $isCombo = stripos($name, 'combo') !== false;
            $regionName = trim((string) ($item['region_name'] ?? ''));
            $description = trim((string) ($item['short_description'] ?? ''));
            $namePrefix = $name . ' - ';
            if ($description !== '' && strncasecmp($description, $namePrefix, strlen($namePrefix)) === 0) {
                $description = trim(substr($description, strlen($namePrefix)));
            }
            if ($regionName !== '' && preg_match('/\A\s*xuất xứ\s*:/iu', $description) !== 1) {
                $description = 'Xuất xứ: ' . $regionName . ($description !== '' ? '. ' . $description : '');
            }
            ?>
            <article class="card h-100 shadow-sm position-relative product-card product-card--catalog<?= $available === 0 ? ' product-card--sold-out' : '' ?>">
                <?php
                $wishlistProductId = (int) $item['id'];
                $wishlistProductName = $item['name'];
                $wishlistProductSlug = $item['slug'];
                require FRONTEND_PATH . '/views/client/wishlist-button.php';
                ?>
                <a href="<?= e(url('/san-pham/' . $item['slug'])) ?>" class="catalog-card-image">
                    <span class="product-card__badges">
                        <?php if ($isCombo): ?>
                            <span class="product-card__type-badge product-card__type-badge--combo">Combo</span>
                        <?php elseif ($regionName !== ''): ?>
                            <span class="product-card__type-badge product-card__type-badge--origin" title="<?= e($regionName) ?>"><?= e($regionName) ?></span>
                        <?php endif; ?>
                        <?php if ($discountPercent > 0): ?><span class="product-card__discount-percent">-<?= $discountPercent ?>%</span><?php endif; ?>
                    </span>
                    <img src="<?= e(asset($imageUrl)) ?>"<?= product_image_fallback_attributes() ?> class="card-img-top" alt="<?= e($item['name']) ?>" loading="lazy">
                </a>
                <div class="card-body d-flex flex-column">
                    <h3 class="h6 product-card__title"><a class="link-dark text-decoration-none" href="<?= e(url('/san-pham/' . $item['slug'])) ?>"><?= e($item['name']) ?></a></h3>
                    <p class="product-card__description"><?= e($description !== '' ? $description : 'Nông sản sạch, được tuyển chọn theo mùa.') ?></p>
                    <?php $threshold = (int) ($item['min_stock'] ?? 5); ?>
                    <div class="product-card__purchase">
                        <div class="product-card__price-content">
                            <div class="product-card__price-row">
                                <?php if ($oldPriceParts !== [] && $hasDiscount): ?><del class="small text-secondary product-card__old-price"><?= e($oldPriceParts[0]) ?></del><?php endif; ?>
                                <span class="fw-bold product-card__price"><?= e($displayPriceParts[0]) ?></span>
                                <span class="small text-secondary product-card__unit">/ <?= e($unitLabel) ?></span>
                            </div>
                            <?php if ($referencePrice !== null): ?><div class="small text-secondary product-card__reference-price"><?= e($referencePrice) ?></div><?php endif; ?>
                            <?php if ($available === 0): ?><span class="product-card__stock-status product-card__stock-status--empty">Hết hàng</span><?php elseif ($available <= $threshold): ?><span class="product-card__stock-status product-card__stock-status--low">Sắp hết</span><?php endif; ?>
                        </div>
                        <?php if ((int) ($item['actual_stock'] ?? 0) > 0): ?>
                            <form class="product-card__action" data-cart-add="<?= e(url('/gio-hang/them')) ?>" data-login-url="<?= e(url('/login?return_to=' . rawurlencode('/san-pham/' . $item['slug']))) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button class="btn btn-success" type="submit" aria-label="Thêm <?= e($item['name']) ?> vào giỏ" title="Thêm vào giỏ">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h2l2.2 11.1a2 2 0 0 0 2 1.6h8.6a2 2 0 0 0 2-1.6L21 8H6"></path><circle cx="10" cy="20" r="1.5"></circle><circle cx="18" cy="20" r="1.5"></circle></svg>
                                </button>
                                <div class="product-card__cart-message" data-cart-message aria-live="polite"></div>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary product-card__action product-card__action--disabled" type="button" aria-label="<?= e($item['name']) ?> đã hết hàng" title="Hết hàng" disabled>
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h2l2.2 11.1a2 2 0 0 0 2 1.6h8.6a2 2 0 0 0 2-1.6L21 8H6"></path><circle cx="10" cy="20" r="1.5"></circle><circle cx="18" cy="20" r="1.5"></circle></svg>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        </div>
    <?php endforeach; ?>
    <?php if (!$products): ?><div class="col-12"><p class="alert alert-light border">Chưa có sản phẩm phù hợp.</p></div><?php endif; ?>
</div>
