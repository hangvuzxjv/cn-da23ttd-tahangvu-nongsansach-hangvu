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
                <form class="client-trace-search" method="get" action="<?= e(url('/truy-xuat')) ?>">
                    <label class="visually-hidden" for="traceLookupNav">Mã lô cần truy xuất</label>
                    <input class="form-control form-control-sm" id="traceLookupNav" name="batch_code" placeholder="Mã lô" required>
                    <button class="btn btn-sm btn-outline-light" type="submit">Tra cứu</button>
                </form>
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
                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="accountMenu">
                        <?php if ($navUser): ?>
                            <a class="dropdown-item" href="<?= e(url('/profile')) ?>">Hồ sơ</a>
                            <a class="dropdown-item" href="<?= e(url('/addresses')) ?>">Sổ địa chỉ</a>
                            <a class="dropdown-item" href="<?= e(url('/tai-khoan/don-hang')) ?>">Đơn hàng</a>
                            <a class="dropdown-item" href="<?= e(url('/tai-khoan/yeu-thich')) ?>">Yêu thích</a>
                            <?php if (Auth::can('dashboard.view')): ?><a class="dropdown-item" href="<?= e(url('/admin')) ?>">Quản trị</a><?php endif; ?>
                            <div class="dropdown-divider"></div>
                            <form method="post" action="<?= e(url('/logout')) ?>">
                                <?= csrf_field() ?>
                                <button class="dropdown-item" type="submit">Đăng xuất</button>
                            </form>
                        <?php else: ?>
                            <a class="dropdown-item" href="<?= e(url('/login')) ?>">Đăng nhập</a>
                            <a class="dropdown-item" href="<?= e(url('/register')) ?>">Đăng ký</a>
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
                <li><a href="<?= e(url('/gio-hang')) ?>">Giỏ hàng</a></li>
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
<script src="<?= e(asset('/js/customer-engagement.js')) ?>"></script>
<?php if ($navUser): ?><script src="<?= e(asset('/js/notifications.js')) ?>"></script><?php endif; ?>
</body>
</html>
