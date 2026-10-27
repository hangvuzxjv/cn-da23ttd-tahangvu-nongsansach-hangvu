<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Nông Sản Sạch') ?></title>
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
                <?php if (Auth::check()): ?>
                    <a class="nav-link" href="<?= e(url('/profile')) ?>">Hồ sơ</a>
                    <a class="nav-link" href="<?= e(url('/addresses')) ?>">Sổ địa chỉ</a>
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
</body>
</html>
