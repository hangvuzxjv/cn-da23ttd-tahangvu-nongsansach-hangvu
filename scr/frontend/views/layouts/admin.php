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
<nav class="navbar navbar-dark admin-topbar">
    <div class="container">
        <a class="navbar-brand" href="<?= e(url('/admin')) ?>">Quản trị Nông Sản Sạch</a>
        <button class="btn btn-outline-light admin-menu-toggle d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavigation" aria-controls="adminNavigation" aria-expanded="false">
            Mở menu
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
    <aside class="admin-sidebar">
        <nav class="collapse admin-navigation" id="adminNavigation" aria-label="Menu quản trị">
            <?php foreach (AdminNavigation::groups() as $group): ?>
                <section class="admin-navigation__group">
                    <h2><?= e($group['label']) ?></h2>
                    <div class="nav nav-pills flex-column">
                        <?php foreach ($group['items'] as $item): ?>
                            <a class="nav-link<?= $item['active'] ? ' active' : '' ?>" href="<?= e(url($item['url'])) ?>"<?= $item['active'] ? ' aria-current="page"' : '' ?>><?= e($item['label']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </nav>
    </aside>
    <main class="admin-content">
        <div class="admin-page-surface">
            <?= $content ?? '' ?>
        </div>
    </main>
</div>
<script src="<?= e(asset('/js/vendor/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('/js/reviews-admin.js')) ?>"></script>
<script src="<?= e(asset('/js/mail-admin.js')) ?>"></script>
</body>
</html>
