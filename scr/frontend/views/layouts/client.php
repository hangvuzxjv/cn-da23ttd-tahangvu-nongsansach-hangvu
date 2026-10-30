<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Nông Sản Sạch') ?> - Nông Sản Sạch</title>
    <meta name="description" content="<?= e($metaDescription ?? 'Nông sản sạch theo mùa, minh bạch nguồn gốc từ các vùng miền Việt Nam.') ?>">
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('/favicon.svg')) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
<main class="container py-5">
    <?= $content ?? '' ?>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('/js/cart.js')) ?>"></script>
</body>
</html>
