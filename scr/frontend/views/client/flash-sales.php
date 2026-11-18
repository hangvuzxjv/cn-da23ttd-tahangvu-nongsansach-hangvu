<?php
$loginReturn = (string) ($_SERVER['REQUEST_URI'] ?? '/flash-sale');
$loginUrl = url('/login?return_to=' . rawurlencode($loginReturn));
$campaignGroups = ['active' => [], 'upcoming' => [], 'ended' => []];
foreach ($campaigns as $campaign) {
    $state = $campaign['campaign_status'] === 'scheduled'
        ? 'upcoming'
        : (string) $campaign['campaign_status'];
    if (isset($campaignGroups[$state])) {
        $campaignGroups[$state][] = $campaign;
    }
}
$activeCampaignCount = count($campaignGroups['active']);
$upcomingCampaignCount = count($campaignGroups['upcoming']);
$endedCampaignCount = count($campaignGroups['ended']);
$hasActiveCampaign = $activeCampaignCount > 0;
$defaultFilter = $hasActiveCampaign
    ? 'active'
    : ($upcomingCampaignCount > 0 ? 'upcoming' : ($endedCampaignCount > 0 ? 'ended' : 'active'));
$heroCampaign = $campaignGroups['active'][0] ?? $campaignGroups['upcoming'][0] ?? null;
$heroIsActive = $heroCampaign !== null && $heroCampaign['campaign_status'] === 'active';
$timezone = new DateTimeZone('Asia/Ho_Chi_Minh');
$serverNow = new DateTimeImmutable($serverTime, $timezone);
?>
<header class="page-heading flash-sale-heading">
    <nav aria-label="Điều hướng">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Trang chủ</a></li>
            <li class="breadcrumb-item active" aria-current="page">Flash Sale</li>
        </ol>
    </nav>
    <section class="flash-sale-banner">
        <div class="flash-sale-banner__copy">
            <h1 class="h2 mb-1"><span aria-hidden="true">⚡</span> Flash Sale</h1>
            <p class="mb-0">Ưu đãi giới hạn suất, cập nhật nhanh theo tồn kho thực tế.</p>
        </div>
        <?php if ($heroCampaign !== null): ?>
            <?php
            $heroTarget = new DateTimeImmutable(
                $heroIsActive ? $heroCampaign['ends_at'] : $heroCampaign['starts_at'],
                $timezone
            );
            ?>
            <div class="flash-sale-banner__timer">
                <div class="small mb-1"><?= $heroIsActive ? 'Kết thúc sau' : 'Bắt đầu sau' ?></div>
                <div class="flash-sale-countdown" data-countdown-target="<?= e($heroTarget->format(DATE_ATOM)) ?>"
                     aria-label="<?= $heroIsActive ? 'Thời gian đến khi kết thúc' : 'Thời gian đến khi bắt đầu' ?>">
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
        <?php endif; ?>
    </section>
</header>

<div data-flash-sale-root data-server-time="<?= e($serverTime) ?>"
     data-progress-url="<?= e(url('/flash-sale/status')) ?>"
     data-has-active="<?= $hasActiveCampaign ? '1' : '0' ?>"
     data-default-filter="<?= e($defaultFilter) ?>">
    <div class="flash-sale-toolbar">
        <div class="flash-sale-filters" role="group" aria-label="Lọc đợt Flash Sale">
            <button class="btn flash-sale-filter" type="button" data-campaign-filter="active" aria-pressed="false">
                Đang diễn ra <span class="flash-sale-filter__count"><?= $activeCampaignCount ?></span>
            </button>
            <button class="btn flash-sale-filter" type="button" data-campaign-filter="upcoming" aria-pressed="false">
                Sắp diễn ra <span class="flash-sale-filter__count"><?= $upcomingCampaignCount ?></span>
            </button>
            <button class="btn flash-sale-filter" type="button" data-campaign-filter="ended" aria-pressed="false">
                Đã kết thúc <span class="flash-sale-filter__count"><?= $endedCampaignCount ?></span>
            </button>
        </div>
        <label class="flash-sale-sort">
            <span>Sắp xếp</span>
            <select class="form-select" data-flash-sort aria-label="Sắp xếp sản phẩm Flash Sale">
                <option value="discount">Giảm nhiều nhất</option>
                <option value="remaining">Sắp hết suất</option>
                <option value="price">Giá thấp đến cao</option>
            </select>
        </label>
    </div>
    <div class="small text-danger mb-2" data-flash-poll-message role="status" aria-live="polite"></div>
    <section class="flash-sale-empty" data-flash-filter-empty hidden>
        <p class="mb-3">Hiện chưa có chương trình thuộc trạng thái này.</p>
        <a class="btn btn-outline-success" href="<?= e(url('/san-pham')) ?>">Xem sản phẩm</a>
    </section>

    <?php foreach ($campaignGroups as $campaignState => $stateCampaigns): ?>
        <?php foreach ($stateCampaigns as $campaign): ?>
            <?php
            $active = $campaignState === 'active';
            $upcoming = $campaignState === 'upcoming';
            $ended = $campaignState === 'ended';
            $target = $upcoming
                ? new DateTimeImmutable($campaign['starts_at'], $timezone)
                : null;
            $endsAt = new DateTimeImmutable($campaign['ends_at'], $timezone);
            $endDate = $endsAt->format('Y-m-d');
            $today = $serverNow->format('Y-m-d');
            $tomorrow = $serverNow->modify('+1 day')->format('Y-m-d');
            $endTimeLabel = $endDate === $today
                ? 'Kết thúc lúc ' . $endsAt->format('H:i') . ' hôm nay'
                : ($endDate === $tomorrow
                    ? 'Kết thúc lúc ' . $endsAt->format('H:i') . ' ngày mai'
                    : 'Kết thúc lúc ' . $endsAt->format('H:i d/m/Y'));
            ?>
            <section class="flash-sale-campaign flash-sale-campaign--<?= e($campaignState) ?>"
                     data-flash-campaign data-campaign-state="<?= e($campaignState) ?>">
                <div class="flash-sale-campaign__heading">
                    <div class="flash-sale-campaign__title">
                        <span class="badge <?= $active ? 'text-bg-danger' : ($upcoming ? 'text-bg-warning' : 'text-bg-secondary') ?>">
                            <?= $active ? 'Đang diễn ra' : ($upcoming ? 'Sắp diễn ra' : 'Đã kết thúc') ?>
                        </span>
                        <h2><?= e($campaign['name']) ?></h2>
                        <?php if ($active): ?>
                            <p><?= e($endTimeLabel) ?></p>
                        <?php elseif ($upcoming): ?>
                            <p>Chương trình sắp bắt đầu; sản phẩm chưa thể mua.</p>
                        <?php else: ?>
                            <p>Chương trình đã kết thúc.</p>
                        <?php endif; ?>
                    </div>
                    <?php if ($upcoming && $target !== null): ?>
                        <div class="flash-sale-countdown-wrap">
                            <div class="small mb-1">Bắt đầu sau</div>
                            <div class="flash-sale-countdown" data-countdown-target="<?= e($target->format(DATE_ATOM)) ?>"
                                 aria-label="Thời gian đến khi bắt đầu">
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
                    <?php endif; ?>
                </div>
                <div class="flash-sale-product-grid" data-flash-grid aria-label="Sản phẩm trong đợt Flash Sale">
                    <?php foreach ($campaign['items'] as $item): ?>
                        <?php
                        $discountPercent = (int) $item['normal_price'] > 0
                            ? max(0, (int) round(((int) $item['normal_price'] - (int) $item['sale_price']) * 100 / (int) $item['normal_price']))
                            : 0;
                        $savingAmount = max(0, (int) round((float) $item['normal_price'] - (float) $item['sale_price']));
                        $soldPercent = (int) $item['quota'] > 0
                            ? min(100, (int) round((int) $item['sold_quantity'] * 100 / (int) $item['quota']))
                            : 0;
                        $soldOut = (int) $item['remaining'] < 1;
                        $saleStatusKey = $soldOut ? 'sold-out'
                            : ((int) $item['sold_quantity'] === 0 ? 'new'
                                : ((int) $item['quota'] > 0 && (int) $item['remaining'] / (int) $item['quota'] <= .2 ? 'low' : ''));
                        $saleStatusLabel = $soldOut ? 'Đã hết suất'
                            : ($saleStatusKey === 'new' ? 'Mới mở bán'
                                : ($saleStatusKey === 'low' ? '🔥 Sắp hết' : ''));
                        $progressTone = $soldPercent >= 80 ? 'red' : ($soldPercent >= 50 ? 'orange' : 'green');
                        $canBuy = $active && (int) $item['max_buy_quantity'] > 0;
                        $disabledText = (int) $item['remaining'] < 1 ? 'Đã hết suất'
                            : ((int) $item['user_remaining'] < 1 ? 'Bạn đã đạt giới hạn mua'
                                : ((int) $item['available_stock'] < 1 ? 'Hết tồn kho' : 'Chưa thể mua'));
                        ?>
                        <div class="flash-sale-product-item<?= $upcoming ? ' flash-sale-product-item--preview' : '' ?>"
                             data-flash-item="<?= (int) $item['flash_sale_item_id'] ?>"
                             data-quota="<?= (int) $item['quota'] ?>"
                             data-sold="<?= (int) $item['sold_quantity'] ?>"
                             data-sort-discount="<?= $discountPercent ?>"
                             data-sort-remaining="<?= (int) $item['remaining'] ?>"
                             data-sort-price="<?= (float) $item['sale_price'] ?>"
                             data-sort-sold-out="<?= $soldOut ? '1' : '0' ?>">
                            <article class="card h-100 shadow-sm border-<?= $active ? 'danger' : 'secondary' ?> position-relative product-card product-card--flash-sale<?= $soldOut ? ' product-card--flash-sale-sold-out' : '' ?><?= $ended ? ' product-card--flash-sale-ended' : '' ?>">
                                <?php
                                $wishlistProductId = (int) $item['product_id'];
                                $wishlistProductName = $item['product_name'];
                                $wishlistProductSlug = $item['product_slug'];
                                require FRONTEND_PATH . '/views/client/wishlist-button.php';
                                ?>
                                <a class="catalog-card-image" href="<?= e(url('/san-pham/' . $item['product_slug'])) ?>">
                                    <?php if ($discountPercent > 0): ?><span class="flash-sale-discount-badge">-<?= $discountPercent ?>%</span><?php endif; ?>
                                    <?php if ($ended): ?><span class="flash-sale-ended-badge">Đã kết thúc</span><?php endif; ?>
                                    <img class="card-img-top" src="<?= e(asset($item['image_url'] ?: '/img/product-placeholder.svg')) ?>" alt="<?= e($item['product_name']) ?>" loading="lazy">
                                </a>
                                <div class="card-body d-flex flex-column">
                                    <h3 class="h6 product-card__title flash-sale-product-name"><a class="link-dark text-decoration-none" href="<?= e(url('/san-pham/' . $item['product_slug'])) ?>"><?= e($item['product_name']) ?></a></h3>
                                    <div class="product-card__meta">
                                        <?php if (!empty($item['region_name'])): ?><span class="product-card__region"><?= e($item['region_name']) ?></span><?php endif; ?>
                                        <span class="product-card__rating product-card__rating--empty">Flash Sale</span>
                                    </div>
                                    <div class="flash-sale-price-block">
                                        <div class="flash-sale-price-line">
                                            <span class="flash-sale-price"><?= number_format((float) $item['sale_price'], 0, ',', '.') ?> ₫</span>
                                            <span class="flash-sale-unit">/ <?= e($item['unit']) ?></span>
                                        </div>
                                        <del class="flash-sale-original-price"><?= number_format((float) $item['normal_price'], 0, ',', '.') ?> ₫</del>
                                        <?php if ($savingAmount >= 1000): ?><div class="flash-sale-saving">Tiết kiệm <?= number_format($savingAmount, 0, ',', '.') ?> ₫</div><?php endif; ?>
                                    </div>
                                    <div class="flash-sale-stock-row">
                                        <span class="flash-sale-stock-count">Đã bán <span data-sold-count><?= (int) $item['sold_quantity'] ?></span>/<?= (int) $item['quota'] ?></span>
                                        <span class="flash-sale-stock-status" data-flash-sale-status data-status="<?= e($saleStatusKey) ?>"<?= $saleStatusKey === '' ? ' hidden' : '' ?>><?= e($saleStatusLabel) ?></span>
                                    </div>
                                    <div class="progress flash-sale-progress mt-1 mb-3" role="progressbar" aria-label="Tiến độ Flash Sale" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $soldPercent ?>">
                                        <div class="progress-bar flash-sale-progress__bar flash-sale-progress--<?= e($progressTone) ?>" data-sold-progress data-progress="<?= $soldPercent ?>" style="width: <?= $soldPercent ?>%"></div>
                                    </div>
                                    <div class="mt-auto" data-flash-action>
                                        <?php if ($upcoming): ?>
                                            <button class="btn btn-secondary w-100" type="button" disabled>Chưa bắt đầu</button>
                                        <?php elseif ($ended): ?>
                                            <button class="btn btn-secondary w-100" type="button" disabled>Đã kết thúc</button>
                                        <?php elseif (!$canBuy): ?>
                                            <button class="btn btn-secondary w-100" type="button" disabled><?= e($disabledText) ?></button>
                                        <?php elseif (!$loggedIn): ?>
                                            <a class="btn btn-danger w-100 flash-sale-buy-button" href="<?= e($loginUrl) ?>">Đăng nhập để mua Flash Sale</a>
                                        <?php else: ?>
                                            <form data-cart-add="<?= e(url('/gio-hang/them')) ?>" data-login-url="<?= e($loginUrl) ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="product_id" value="<?= (int) $item['product_id'] ?>">
                                                <div class="flash-sale-buy-controls">
                                                    <div class="flash-sale-quantity" data-flash-quantity-controls>
                                                        <button class="flash-sale-quantity__button" type="button" data-flash-quantity-step="-1" aria-label="Giảm số lượng" disabled>−</button>
                                                        <input class="form-control" type="number" name="quantity" data-max-buy="<?= (int) $item['max_buy_quantity'] ?>" value="1" min="1" max="<?= (int) $item['max_buy_quantity'] ?>" step="1" aria-label="Số lượng <?= e($item['product_name']) ?>">
                                                        <button class="flash-sale-quantity__button" type="button" data-flash-quantity-step="1" aria-label="Tăng số lượng"<?= (int) $item['max_buy_quantity'] <= 1 ? ' disabled' : '' ?>>+</button>
                                                    </div>
                                                    <button class="btn btn-danger flash-sale-buy-button" type="submit">Mua ngay</button>
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
    <?php endforeach; ?>
</div>

<aside class="flash-sale-footer-note">
    <p>Giá Flash Sale và số suất được xác nhận lại trên máy chủ khi thêm vào giỏ và khi đặt hàng.</p>
    <a href="<?= e(url('/san-pham')) ?>">Xem tất cả sản phẩm</a>
</aside>
