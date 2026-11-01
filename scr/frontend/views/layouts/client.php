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
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container">
        <a class="navbar-brand" href="<?= e(url('/')) ?>">Nông Sản Sạch</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#clientNavigation" aria-controls="clientNavigation" aria-expanded="false" aria-label="Mở menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="clientNavigation">
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="<?= e(url('/')) ?>">Trang chủ</a>
                <a class="nav-link" href="<?= e(url('/san-pham')) ?>">Sản phẩm</a>
                <a class="nav-link" href="<?= e(url('/flash-sale')) ?>">Flash Sale</a>
                <?php $navUser = Auth::user(); $navCart = (new CartService())->summary($navUser ? (int) $navUser['id'] : null); $navCartCount = array_sum(array_map(static fn (array $item): int => (int) $item['quantity'], $navCart['items'])); ?>
                <a class="nav-link" href="<?= e(url('/gio-hang')) ?>">Giỏ hàng <span class="badge bg-light text-success" id="miniCartCount"><?= $navCartCount ?></span> · <span id="miniCartTotal"><?= number_format($navCart['subtotal'], 0, ',', '.') ?> ₫</span></a>
                <form class="d-flex align-items-center gap-1 my-2 my-lg-0 me-lg-2" method="get" action="<?= e(url('/truy-xuat')) ?>">
                    <label class="visually-hidden" for="traceLookupNav">Mã lô cần truy xuất</label>
                    <input class="form-control form-control-sm" id="traceLookupNav" name="batch_code" placeholder="Mã lô" required>
                    <button class="btn btn-sm btn-outline-light text-nowrap" type="submit">Tra cứu</button>
                </form>
                <?php if ($navUser): ?>
                    <a class="nav-link" href="<?= e(url('/profile')) ?>">Hồ sơ</a>
                    <a class="nav-link" href="<?= e(url('/addresses')) ?>">Sổ địa chỉ</a>
                    <a class="nav-link" href="<?= e(url('/tai-khoan/don-hang')) ?>">Đơn hàng</a>
                    <a class="nav-link" href="<?= e(url('/tai-khoan/yeu-thich')) ?>">Yêu thích</a>
                    <div class="nav-item dropdown">
                        <button class="nav-link btn btn-link dropdown-toggle" id="notificationMenu" type="button"
                                data-bs-toggle="dropdown" aria-expanded="false">
                            Thông báo <span class="badge bg-warning text-dark d-none" id="notificationCount">0</span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end p-2" aria-labelledby="notificationMenu"
                             id="notificationList">
                            <span class="dropdown-item-text text-muted">Đang tải thông báo…</span>
                        </div>
                    </div>
                    <?php if (Auth::can('dashboard.view')): ?><a class="nav-link" href="<?= e(url('/admin')) ?>">Quản trị</a><?php endif; ?>
                    <form class="d-inline" method="post" action="<?= e(url('/logout')) ?>">
                        <?= csrf_field() ?>
                        <button class="nav-link btn btn-link" type="submit">Đăng xuất</button>
                    </form>
                <?php else: ?>
                    <a class="nav-link" href="<?= e(url('/login')) ?>">Đăng nhập</a>
                    <a class="nav-link" href="<?= e(url('/register')) ?>">Đăng ký</a>
                <?php endif; ?>
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
<main class="container py-5">
    <?= $content ?? '' ?>
</main>
<footer class="border-top bg-light py-4">
    <div class="container">
        <div class="row g-3">
            <div class="col-12 col-md-6"><strong class="text-success">Nông Sản Sạch</strong><p class="small text-secondary mb-0 mt-2">Nông sản theo mùa, minh bạch nguồn gốc từ các vùng miền Việt Nam.</p></div>
            <div class="col-12 col-md-6"><h2 class="h6">Thông tin cửa hàng</h2><ul class="list-unstyled small mb-0">
                <?php foreach ($footerPages as $footerPage): ?><li><a href="<?= e(url('/' . $footerPage['slug'])) ?>"><?= e($footerPage['title']) ?></a></li><?php endforeach; ?>
                <li><a href="<?= e(url('/lien-he')) ?>">Liên hệ</a></li>
                <li><a href="<?= e(url('/sitemap.xml')) ?>">Sơ đồ trang</a></li>
            </ul></div>
        </div>
    </div>
</footer>
<script src="<?= e(asset('/js/vendor/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('/js/cart.js')) ?>"></script>
<script src="<?= e(asset('/js/flash-sale.js')) ?>"></script>
<script src="<?= e(asset('/js/customer-engagement.js')) ?>"></script>
<?php if ($navUser): ?><script src="<?= e(asset('/js/notifications.js')) ?>"></script><?php endif; ?>
</body>
</html>
