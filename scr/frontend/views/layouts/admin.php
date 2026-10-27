<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Quản trị') ?> - Nông Sản Sạch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(asset('/css/app.css')) ?>" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="<?= e(url('/admin')) ?>">Quản trị Nông Sản Sạch</a>
        <div class="d-flex gap-2 align-items-center">
            <a class="btn btn-outline-light btn-sm" href="<?= e(url('/')) ?>">Trang chủ</a>
            <form method="post" action="<?= e(url('/logout')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-outline-light btn-sm" type="submit">Đăng xuất</button>
            </form>
        </div>
    </div>
</nav>
<div class="container-fluid py-4">
    <div class="row g-4">
        <aside class="col-12 col-md-3 col-lg-2">
            <nav class="nav nav-pills flex-column bg-white border rounded p-2" aria-label="Menu quản trị">
                <?php foreach (($menu ?? []) as $item): ?>
                    <a class="nav-link" href="<?= e(url($item['url'])) ?>"><?= e($item['label']) ?></a>
                <?php endforeach; ?>
            </nav>
        </aside>
        <main class="col-12 col-md-9 col-lg-10">
            <?= $content ?? '' ?>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
