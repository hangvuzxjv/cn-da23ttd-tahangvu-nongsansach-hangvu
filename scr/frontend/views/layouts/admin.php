<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Quản trị') ?> - Nông Sản Sạch</title>
    <meta name="description" content="Khu vực quản trị Nông Sản Sạch.">
    <meta name="theme-color" content="#176b3a">
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
    <meta name="app-base-url" content="<?= e(url('/')) ?>">
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('/favicon.svg')) ?>">
    <link href="<?= e(asset('/css/vendor/bootstrap.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('/css/app.css')) ?>" rel="stylesheet">
</head>
<body class="bg-light">
<?php
$adminHeaderUser = Auth::user() ?? [];
$adminRoleNames = [
    'admin' => 'Quản trị viên',
    'staff_sales' => 'Nhân viên bán hàng',
    'staff_warehouse' => 'Nhân viên kho',
];
$adminHeaderName = (string) ($adminHeaderUser['name'] ?? '');
$adminAvatarInitial = $adminHeaderName !== '' ? mb_strtoupper(mb_substr($adminHeaderName, 0, 1, 'UTF-8'), 'UTF-8') : '?';
?>
<header class="admin-topbar">
    <div class="admin-topbar__inner">
        <button class="btn btn-outline-light admin-menu-toggle" type="button" data-admin-nav-toggle aria-controls="adminNavigation" aria-expanded="false" aria-label="Mở menu">
            <span aria-hidden="true">☰</span>
        </button>
        <a class="admin-topbar__brand" href="<?= e(url('/admin')) ?>">
            <svg class="admin-topbar__leaf" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M20.5 3.5C12 3.5 5.2 5.6 4 11.4c-.7 3.2 1.6 5.6 4.5 5.6 5.4 0 9.9-5.3 12-13.5ZM3.5 21c2.5-5.2 6.4-8.8 12-11.2" />
            </svg>
            <span class="admin-topbar__brand-name">Nông Sản Sạch<span class="admin-topbar__brand-divider"> · </span><span class="admin-topbar__brand-section">Quản trị</span></span>
        </a>
        <div class="admin-global-search" role="search" data-admin-search>
            <label class="visually-hidden" for="adminSearchInput">Tìm nhanh trong quản trị</label>
            <input id="adminSearchInput" class="form-control admin-global-search__input" type="search" maxlength="100" placeholder="Tìm đơn hàng, khách hàng, sản phẩm..." autocomplete="off" role="combobox" aria-autocomplete="list" aria-haspopup="listbox" aria-controls="adminSearchResults" aria-expanded="false" data-admin-search-input>
            <button class="btn btn-outline-light admin-global-search__toggle" type="button" aria-label="Mở tìm kiếm" aria-expanded="false" aria-controls="adminSearchInput" data-admin-search-toggle>
                <svg class="admin-global-search__icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"></circle><path d="m16 16 5 5"></path></svg>
                <span class="admin-global-search__close" aria-hidden="true">×</span>
            </button>
            <div class="admin-search-results" id="adminSearchResults" role="listbox" aria-label="Kết quả tìm kiếm" hidden>
                <div class="admin-search-results__items" data-admin-search-results></div>
            </div>
        </div>
        <div class="admin-topbar-actions">
            <div class="admin-header-dropdown admin-notification-dropdown">
                <button class="btn btn-outline-light admin-notification-toggle" type="button" data-admin-menu-toggle aria-controls="notificationList" aria-expanded="false" aria-label="Thông báo">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path><path d="M10 21h4"></path></svg>
                    <span class="badge rounded-pill bg-warning text-dark d-none" id="notificationCount">0</span>
                </button>
                <div class="dropdown-menu dropdown-menu-end notification-menu" id="notificationList" data-notification-limit="5" aria-label="Thông báo mới nhất" hidden>
                    <div class="notification-menu__header"><strong>Thông báo</strong></div>
                    <div class="notification-menu__items" data-notification-items>
                        <span class="dropdown-item-text text-muted">Đang tải thông báo…</span>
                    </div>
                    <a class="notification-menu__all" href="<?= e(url('/notifications?view=all')) ?>">Xem tất cả</a>
                    <div class="notification-menu__message small" data-notification-message role="status" aria-live="polite"></div>
                </div>
            </div>
            <a class="btn btn-outline-light btn-sm admin-topbar__store-link" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">Xem trang bán hàng</a>
            <div class="admin-header-dropdown admin-account-dropdown">
                <button class="btn btn-outline-light admin-account-toggle" type="button" data-admin-menu-toggle aria-controls="adminAccountMenu" aria-expanded="false">
                    <span class="admin-avatar" aria-hidden="true"><?= e($adminAvatarInitial) ?></span>
                    <span class="admin-account-label">
                        <strong><?= e($adminHeaderName) ?></strong>
                        <small><?= e($adminRoleNames[$adminHeaderUser['role'] ?? ''] ?? 'Người dùng') ?></small>
                    </span>
                    <span class="admin-account-chevron" aria-hidden="true">▾</span>
                </button>
                <div class="dropdown-menu dropdown-menu-end admin-account-menu" id="adminAccountMenu" hidden>
                    <a class="dropdown-item" href="<?= e(url('/profile')) ?>">Hồ sơ</a>
                    <a class="dropdown-item" href="<?= e(url('/profile')) ?>">Đổi mật khẩu</a>
                    <a class="dropdown-item admin-account-store-link" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">Xem trang bán hàng</a>
                    <div class="dropdown-divider"></div>
                    <form method="post" action="<?= e(url('/logout')) ?>">
                        <?= csrf_field() ?>
                        <button class="dropdown-item" type="submit">Đăng xuất</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
<div class="container-fluid py-4 admin-shell">
    <aside class="admin-sidebar">
        <nav class="collapse admin-navigation" id="adminNavigation" aria-label="Menu quản trị">
            <?php foreach (AdminNavigation::groups() as $group): ?>
                <section class="admin-navigation__group">
                    <h2><?= e($group['label']) ?></h2>
                    <div class="nav nav-pills flex-column">
                        <?php foreach ($group['items'] as $item): ?>
                            <a class="nav-link<?= $item['active'] ? ' active' : '' ?>" href="<?= e(url($item['url'])) ?>"<?= $item['active'] ? ' aria-current="page"' : '' ?>>
                                <span><?= e($item['label']) ?></span>
                                <?php if (isset($item['badge'])): ?><span class="badge rounded-pill bg-danger ms-auto"><?= (int) $item['badge'] ?></span><?php endif; ?>
                            </a>
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
<script src="<?= e(asset('/js/admin-header.js')) ?>"></script>
<script src="<?= e(asset('/js/admin-search.js')) ?>"></script>
<script src="<?= e(asset('/js/notifications.js')) ?>"></script>
<script src="<?= e(asset('/js/reviews-admin.js')) ?>"></script>
<script src="<?= e(asset('/js/mail-admin.js')) ?>"></script>
<?php if (!empty($loadInventoryStockScript)): ?><script src="<?= e(asset('/js/inventory-stock.js')) ?>"></script><?php endif; ?>
<?php if (!empty($loadInventoryReceiptsScript)): ?><script src="<?= e(asset('/js/inventory-receipts.js')) ?>"></script><?php endif; ?>
<?php if (!empty($loadInventoryStocktakeScript)): ?><script src="<?= e(asset('/js/inventory-stocktakes.js')) ?>"></script><?php endif; ?>
</body>
</html>
