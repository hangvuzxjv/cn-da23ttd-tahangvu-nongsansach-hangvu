<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Nông Sản Sạch') ?> - Nông Sản Sạch</title>
    <meta name="description" content="<?= e($metaDescription ?? 'Nông sản sạch theo mùa, minh bạch nguồn gốc từ các vùng miền Việt Nam.') ?>">
    <meta name="theme-color" content="#176b3a">
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
    <meta name="app-base-url" content="<?= e(url('/')) ?>">
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('/favicon.svg')) ?>">
    <link href="<?= e(asset('/css/vendor/bootstrap.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('/css/app.css')) ?>" rel="stylesheet">
</head>
<body class="client-site">
<nav class="navbar navbar-expand-lg navbar-dark bg-success client-navbar">
    <div class="container">
        <a class="navbar-brand" href="<?= e(url('/')) ?>">Nông Sản Sạch</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#clientNavigation" aria-controls="clientNavigation" aria-expanded="false" aria-label="Mở menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <?php
        $navUser = Auth::user();
        $navCart = (new CartService())->summary($navUser ? (int) $navUser['id'] : null);
        $navCartCount = array_sum(array_map(static fn (array $item): int => (int) $item['quantity'], $navCart['items']));
        $navAccountCounts = ['orders' => 0, 'vouchers' => 0, 'support' => 0];
        $navUserName = trim((string) ($navUser['name'] ?? ''));
        $navUserInitial = $navUserName !== ''
            ? mb_strtoupper(mb_substr($navUserName, 0, 1, 'UTF-8'), 'UTF-8')
            : '?';
        if ($navUser) {
            $navAccountCountQuery = Database::connection()->prepare(
                "SELECT
                    (SELECT COUNT(*) FROM orders
                     WHERE user_id = :orders_user_id AND status IN ('confirmed', 'processing', 'shipping')) AS order_count,
                    (SELECT COUNT(*)
                     FROM user_vouchers uv
                     JOIN vouchers v ON v.id = uv.voucher_id
                     WHERE uv.user_id = :voucher_user_id
                       AND uv.used_order_id IS NULL AND uv.used_at IS NULL
                       AND COALESCE(uv.expires_at, v.ends_at) >= CURRENT_TIMESTAMP
                       AND v.status = 'active' AND v.starts_at <= CURRENT_TIMESTAMP AND v.ends_at >= CURRENT_TIMESTAMP
                       AND (v.usage_limit IS NULL OR v.used_count < v.usage_limit)
                       AND (SELECT COUNT(*) FROM voucher_usages vu
                            WHERE vu.voucher_id = v.id AND vu.user_id = uv.user_id AND vu.status = 'used') < v.per_user_limit
                    ) AS voucher_count,
                    (SELECT COUNT(DISTINCT link_url) FROM notifications
                     WHERE user_id = :support_user_id AND read_at IS NULL
                       AND title = 'Phiếu hỗ trợ đã có phản hồi') AS support_count"
            );
            $navUserId = (int) $navUser['id'];
            $navAccountCountQuery->execute([
                'orders_user_id' => $navUserId,
                'voucher_user_id' => $navUserId,
                'support_user_id' => $navUserId,
            ]);
            $navAccountCounts = array_map('intval', $navAccountCountQuery->fetch() ?: $navAccountCounts);
        }
        ?>
        <div class="collapse navbar-collapse client-navigation" id="clientNavigation">
            <div class="navbar-nav ms-auto align-items-lg-center">
                <a class="nav-link" href="<?= e(url('/')) ?>">Trang chủ</a>
                <a class="nav-link" href="<?= e(url('/san-pham')) ?>">Sản phẩm</a>
                <a class="nav-link" href="<?= e(url('/flash-sale')) ?>">Flash Sale</a>
                <a class="nav-link client-cart-link" href="<?= e(url('/gio-hang')) ?>">
                    <svg class="client-nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.2 11.1a2 2 0 0 0 2 1.6h8.5a2 2 0 0 0 1.9-1.4L22 8H6"></path><circle cx="10" cy="20" r="1"></circle><circle cx="18" cy="20" r="1"></circle></svg>
                    <span>Giỏ hàng</span><span class="badge bg-light text-success" id="miniCartCount"><?= $navCartCount ?></span>
                </a>
                <?php if ($navUser): ?>
                    <div class="nav-item dropdown client-notification-dropdown">
                        <button class="nav-link btn btn-link dropdown-toggle" id="notificationMenu" type="button"
                                data-bs-toggle="dropdown" aria-expanded="false">
                            <svg class="client-nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path><path d="M10 21h4"></path></svg>
                            <span>Thông báo</span><span class="badge bg-warning text-dark d-none" id="notificationCount">0</span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end notification-menu" aria-labelledby="notificationMenu"
                             id="notificationList">
                            <div class="notification-menu__header">
                                <strong>Thông báo</strong>
                                <button class="btn btn-sm btn-link" type="button" id="markAllNotificationsRead">Đánh dấu đã đọc tất cả</button>
                            </div>
                            <div class="notification-menu__items" data-notification-items>
                                <span class="dropdown-item-text text-muted">Đang tải thông báo…</span>
                            </div>
                            <a class="notification-menu__all" href="<?= e(url('/notifications?view=all')) ?>">Xem tất cả</a>
                            <div class="notification-menu__message small" data-notification-message role="status" aria-live="polite"></div>
                        </div>
                    </div>
                <?php else: ?>
                    <a class="nav-link client-notification-link" href="<?= e(url('/login')) ?>">
                        <svg class="client-nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path><path d="M10 21h4"></path></svg>
                        <span>Thông báo</span>
                    </a>
                <?php endif; ?>
                <div class="nav-item dropdown client-account-dropdown">
                    <button class="nav-link btn btn-link dropdown-toggle" id="accountMenu" type="button"
                            data-bs-toggle="dropdown" aria-expanded="false">Tài khoản</button>
                    <div class="dropdown-menu dropdown-menu-end client-account-menu" aria-labelledby="accountMenu">
                        <?php if ($navUser): ?>
                            <a class="client-account-profile" href="<?= e(url('/profile')) ?>">
                                <span class="client-account-profile__avatar" aria-hidden="true"><?= e($navUserInitial) ?></span>
                                <span class="client-account-profile__details">
                                    <strong><?= e($navUserName !== '' ? $navUserName : 'Tài khoản') ?></strong>
                                    <small><?= e((string) ($navUser['email'] ?? '')) ?></small>
                                </span>
                            </a>
                            <section class="client-account-group" aria-labelledby="accountShoppingHeading">
                                <h2 class="client-account-group__heading" id="accountShoppingHeading">Mua sắm</h2>
                                <a class="client-account-item" href="<?= e(url('/tai-khoan/don-hang')) ?>">
                                    <svg class="client-account-item__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12l2 3v15H4V6l2-3Z"></path><path d="M8 9h8M8 13h8M8 17h5"></path></svg>
                                    <span>Đơn hàng</span>
                                    <?php if ($navAccountCounts['orders'] > 0): ?><span class="client-account-badge"><?= $navAccountCounts['orders'] ?></span><?php endif; ?>
                                </a>
                                <a class="client-account-item" href="<?= e(url('/tai-khoan/yeu-thich')) ?>">
                                    <svg class="client-account-item__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 8.7c0 5.2-8.8 11-8.8 11s-8.8-5.8-8.8-11A4.7 4.7 0 0 1 12 6.2a4.7 4.7 0 0 1 8.8 2.5Z"></path></svg>
                                    <span>Yêu thích</span>
                                </a>
                            </section>
                            <section class="client-account-group" aria-labelledby="accountOffersHeading">
                                <h2 class="client-account-group__heading" id="accountOffersHeading">Ưu đãi</h2>
                                <a class="client-account-item" href="<?= e(url('/voucher-cua-toi')) ?>">
                                    <svg class="client-account-item__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7V4h18v3a3 3 0 0 0 0 6v7H3v-7a3 3 0 0 0 0-6Z"></path><path d="M13 8v1m0 3v1m0 3v1"></path></svg>
                                    <span>Voucher của tôi</span>
                                    <?php if ($navAccountCounts['vouchers'] > 0): ?><span class="client-account-badge"><?= $navAccountCounts['vouchers'] ?></span><?php endif; ?>
                                </a>
                                <a class="client-account-item" href="<?= e(url('/kho-voucher')) ?>">
                                    <svg class="client-account-item__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.5 5.1 5.6.8-4 3.9 1 5.5-5.1-2.7-5.1 2.7 1-5.5-4-3.9 5.6-.8L12 3Z"></path></svg>
                                    <span>Kho voucher</span>
                                </a>
                            </section>
                            <section class="client-account-group" aria-labelledby="accountPersonalHeading">
                                <h2 class="client-account-group__heading" id="accountPersonalHeading">Thông tin cá nhân</h2>
                                <a class="client-account-item" href="<?= e(url('/profile')) ?>">
                                    <svg class="client-account-item__icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3.5"></circle><path d="M4.5 20a7.5 7.5 0 0 1 15 0"></path></svg>
                                    <span>Hồ sơ</span>
                                </a>
                                <a class="client-account-item" href="<?= e(url('/addresses')) ?>">
                                    <svg class="client-account-item__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0Z"></path><circle cx="12" cy="10" r="2"></circle></svg>
                                    <span>Sổ địa chỉ</span>
                                </a>
                                <?php if (Auth::can('dashboard.view')): ?>
                                    <a class="client-account-item" href="<?= e(url('/admin')) ?>">
                                        <svg class="client-account-item__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V10l8-6 8 6v10h-6v-6h-4v6H4Z"></path></svg>
                                        <span>Quản trị</span>
                                    </a>
                                <?php endif; ?>
                            </section>
                            <section class="client-account-group" aria-labelledby="accountSupportHeading">
                                <h2 class="client-account-group__heading" id="accountSupportHeading">Hỗ trợ</h2>
                                <?php if (Auth::can('support.access')): ?>
                                    <a class="client-account-item" href="<?= e(url('/ho-tro')) ?>">
                                        <svg class="client-account-item__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 13v-2a8 8 0 0 1 16 0v2"></path><path d="M4 12H3v5h4v-5H4Zm16 0h1v5h-4v-5h3ZM17 19a5 5 0 0 1-5 2"></path></svg>
                                        <span>Yêu cầu hỗ trợ</span>
                                        <?php if ($navAccountCounts['support'] > 0): ?><span class="client-account-badge"><?= $navAccountCounts['support'] ?></span><?php endif; ?>
                                    </a>
                                <?php endif; ?>
                                <a class="client-account-item" href="<?= e(url('/cau-hoi-thuong-gap')) ?>">
                                    <svg class="client-account-item__icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M9.5 9a2.6 2.6 0 1 1 4.5 1.8c-1.1 1.1-2 1.3-2 3M12 17.5v.1"></path></svg>
                                    <span>Câu hỏi thường gặp</span>
                                </a>
                                <a class="client-account-item" href="<?= e(url('/lien-he')) ?>">
                                    <svg class="client-account-item__icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m4 7 8 6 8-6"></path></svg>
                                    <span>Liên hệ</span>
                                </a>
                            </section>
                            <form method="post" action="<?= e(url('/logout')) ?>">
                                <?= csrf_field() ?>
                                <button class="client-account-item client-account-logout" type="submit">
                                    <svg class="client-account-item__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 4H5v16h5M14 8l4 4-4 4m4-4H9"></path></svg>
                                    <span>Đăng xuất</span>
                                </button>
                            </form>
                        <?php else: ?>
                            <section class="client-account-group" aria-labelledby="guestOffersHeading">
                                <h2 class="client-account-group__heading" id="guestOffersHeading">Ưu đãi</h2>
                                <a class="client-account-item" href="<?= e(url('/login?return_to=' . rawurlencode('/voucher-cua-toi'))) ?>">
                                    <svg class="client-account-item__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7V4h18v3a3 3 0 0 0 0 6v7H3v-7a3 3 0 0 0 0-6Z"></path><path d="M13 8v1m0 3v1m0 3v1"></path></svg>
                                    <span>Voucher của tôi</span>
                                </a>
                                <a class="client-account-item" href="<?= e(url('/kho-voucher')) ?>">
                                    <svg class="client-account-item__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.5 5.1 5.6.8-4 3.9 1 5.5-5.1-2.7-5.1 2.7 1-5.5-4-3.9 5.6-.8L12 3Z"></path></svg>
                                    <span>Kho voucher</span>
                                </a>
                            </section>
                            <section class="client-account-group" aria-labelledby="guestSupportHeading">
                                <h2 class="client-account-group__heading" id="guestSupportHeading">Hỗ trợ</h2>
                                <a class="client-account-item" href="<?= e(url('/ho-tro')) ?>">
                                    <svg class="client-account-item__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 13v-2a8 8 0 0 1 16 0v2"></path><path d="M4 12H3v5h4v-5H4Zm16 0h1v5h-4v-5h3ZM17 19a5 5 0 0 1-5 2"></path></svg>
                                    <span>Yêu cầu hỗ trợ</span>
                                </a>
                                <a class="client-account-item" href="<?= e(url('/cau-hoi-thuong-gap')) ?>">
                                    <svg class="client-account-item__icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M9.5 9a2.6 2.6 0 1 1 4.5 1.8c-1.1 1.1-2 1.3-2 3M12 17.5v.1"></path></svg>
                                    <span>Câu hỏi thường gặp</span>
                                </a>
                                <a class="client-account-item" href="<?= e(url('/lien-he')) ?>">
                                    <svg class="client-account-item__icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m4 7 8 6 8-6"></path></svg>
                                    <span>Liên hệ</span>
                                </a>
                            </section>
                            <div class="client-account-group client-account-group--guest-actions">
                                <a class="client-account-item" href="<?= e(url('/login')) ?>">
                                    <svg class="client-account-item__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 4h5v16h-5M10 8l-4 4 4 4m-4-4h10"></path></svg>
                                    <span>Đăng nhập</span>
                                </a>
                                <a class="client-account-item" href="<?= e(url('/register')) ?>">
                                    <svg class="client-account-item__icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"></circle><path d="M3.5 20a5.5 5.5 0 0 1 11 0M18 8v6m-3-3h6"></path></svg>
                                    <span>Đăng ký</span>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>
<?php if ($mailLink = flash('mail_link')): ?>
    <div class="container pt-3"><div class="alert alert-info mb-0">Liên kết email thử nghiệm (debug): <a href="<?= e($mailLink) ?>">Mở liên kết</a></div></div>
<?php endif; ?>
<?php
$footerSlugs = StaticPageService::publishedSlugs();
$footerPageQuery = Database::connection()->prepare(
    "SELECT title, slug FROM pages WHERE status = 'published' AND slug IN (" . implode(',', array_fill(0, count($footerSlugs), '?')) . ')'
);
$footerPageQuery->execute($footerSlugs);
$footerPages = $footerPageQuery->fetchAll();
?>
<main class="container py-5 client-main">
    <?= $content ?? '' ?>
</main>
<footer class="client-footer">
    <div class="container">
        <div class="row g-4 client-footer__columns">
            <div class="col-12 col-sm-6 col-lg-3"><h2>Về cửa hàng</h2><p>Nông sản theo mùa, minh bạch nguồn gốc từ các vùng miền Việt Nam.</p></div>
            <div class="col-12 col-sm-6 col-lg-3"><h2>Liên kết nhanh</h2><ul>
                <li><a href="<?= e(url('/san-pham')) ?>">Sản phẩm</a></li>
                <li><a href="<?= e(url('/flash-sale')) ?>">Flash Sale</a></li>
                <li><a href="<?= e(url('/voucher-cua-toi')) ?>">Voucher của tôi</a></li>
                <li><a href="<?= e(url('/kho-voucher')) ?>">Kho voucher</a></li>
                <li><a href="<?= e(url('/gio-hang')) ?>">Giỏ hàng</a></li>
                <li><a href="<?= e(url('/cau-hoi-thuong-gap')) ?>">Câu hỏi thường gặp</a></li>
                <li><a href="<?= e(url('/ho-tro')) ?>">Hỗ trợ</a></li>
                <li><a href="<?= e(url('/sitemap.xml')) ?>">Sơ đồ trang</a></li>
            </ul></div>
            <div class="col-12 col-sm-6 col-lg-3"><h2>Chính sách</h2><ul>
                <?php foreach ($footerPages as $footerPage): ?><li><a href="<?= e(url('/' . $footerPage['slug'])) ?>"><?= e($footerPage['title']) ?></a></li><?php endforeach; ?>
            </ul></div>
            <div class="col-12 col-sm-6 col-lg-3"><h2>Liên hệ</h2><ul>
                <li><a href="<?= e(url('/lien-he')) ?>">Gửi liên hệ</a></li>
                <li><a href="<?= e(url('/truy-xuat')) ?>">Tra cứu nguồn gốc</a></li>
            </ul></div>
        </div>
        <div class="client-footer__copyright">© <?= date('Y') ?> Nông Sản Sạch</div>
    </div>
</footer>
<script src="<?= e(asset('/js/vendor/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('/js/ui.js')) ?>"></script>
<script src="<?= e(asset('/js/cart.js')) ?>"></script>
<script src="<?= e(asset('/js/flash-sale.js')) ?>"></script>
<script src="<?= e(asset('/js/home-banners.js')) ?>"></script>
<script src="<?= e(asset('/js/customer-engagement.js')) ?>"></script>
<?php if ($navUser): ?><script src="<?= e(asset('/js/notifications.js')) ?>"></script><?php endif; ?>
<?php if (!empty($loadFaqSearch)): ?><script src="<?= e(asset('/js/faq-search.js')) ?>" defer></script><?php endif; ?>
<?php if (!empty($loadVoucherWallet)): ?><script src="<?= e(asset('/js/voucher-wallet.js')) ?>"></script><?php endif; ?>
<?php if (!empty($loadProductSearch)): ?><script src="<?= e(asset('/js/product-search.js')) ?>" defer></script><?php endif; ?>
<?= (new TawkChatService())->embedScript() ?>
</body>
</html>
