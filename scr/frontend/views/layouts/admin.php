<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Quản trị') ?> - Nông Sản Sạch</title>
    <meta name="description" content="Khu vực quản trị Nông Sản Sạch.">
    <meta name="theme-color" content="#176b3a">
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('/favicon.svg')) ?>">
    <link href="<?= e(asset('/css/vendor/bootstrap.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('/css/app.css')) ?>" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="<?= e(url('/admin')) ?>">Quản trị Nông Sản Sạch</a>
        <button class="navbar-toggler d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavigation" aria-controls="adminNavigation" aria-expanded="false" aria-label="Mở menu quản trị">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="d-flex gap-2 align-items-center admin-topbar-actions">
            <a class="btn btn-outline-light btn-sm" href="<?= e(url('/')) ?>">Trang chủ</a>
            <form method="post" action="<?= e(url('/logout')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-outline-light btn-sm" type="submit">Đăng xuất</button>
            </form>
        </div>
    </div>
</nav>
<div class="container-fluid py-4 admin-shell">
    <div class="row g-4">
        <aside class="col-12 col-md-3 col-lg-2 admin-sidebar">
            <nav class="collapse nav nav-pills flex-column bg-white border rounded p-2 admin-navigation" id="adminNavigation" aria-label="Menu quản trị">
                <?php foreach (($menu ?? []) as $item): ?>
                    <a class="nav-link" href="<?= e(url($item['url'])) ?>"><?= e($item['label']) ?></a>
                <?php endforeach; ?>
            </nav>
        </aside>
        <main class="col-12 col-md-9 col-lg-10 admin-content">
            <?= $content ?? '' ?>
        </main>
    </div>
</div>
<script src="<?= e(asset('/js/vendor/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('/js/reviews-admin.js')) ?>"></script>
<script src="<?= e(asset('/js/mail-admin.js')) ?>"></script>
</body>
</html>
