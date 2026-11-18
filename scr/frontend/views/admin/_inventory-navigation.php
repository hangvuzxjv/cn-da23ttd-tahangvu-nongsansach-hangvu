<?php
$inventoryNavigationPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
if (defined('BASE_URL') && BASE_URL !== '' && str_starts_with($inventoryNavigationPath, BASE_URL . '/')) {
    $inventoryNavigationPath = substr($inventoryNavigationPath, strlen(BASE_URL));
}
$canViewInventory = Auth::can('inventory.view');
$canViewBatches = Auth::can('batches.view');
$isInventoryStockPage = $inventoryNavigationPath === '/admin/inventory/stock'
    || $inventoryNavigationPath === '/admin/inventory/stock.csv'
    || str_starts_with($inventoryNavigationPath, '/admin/inventory/stock/');
$isBatchPage = str_starts_with($inventoryNavigationPath, '/admin/batches')
    || $inventoryNavigationPath === '/admin/labels';
$isStocktakePage = str_starts_with($inventoryNavigationPath, '/admin/inventory/stocktakes');
$isMovementPage = str_starts_with($inventoryNavigationPath, '/admin/inventory/movements');
$inventoryNavigationSection = '';
if ($inventoryNavigationPath === '/admin/inventory') {
    $inventoryNavigationSection = 'overview';
} elseif (str_starts_with($inventoryNavigationPath, '/admin/inventory/receipts')) {
    $inventoryNavigationSection = 'receipts';
} elseif ($isStocktakePage || $isMovementPage) {
    $inventoryNavigationSection = 'history';
} elseif ($isInventoryStockPage || $isBatchPage) {
    $inventoryNavigationSection = 'stock';
}
$inventoryPrimaryTabs = [
    ['key' => 'overview', 'label' => 'Tổng quan kho', 'url' => '/admin/inventory', 'available' => $canViewInventory],
    ['key' => 'receipts', 'label' => 'Nhập hàng', 'url' => '/admin/inventory/receipts', 'available' => $canViewInventory],
    [
        'key' => 'stock',
        'label' => 'Hàng trong kho',
        'url' => $canViewInventory ? '/admin/inventory/stock' : '/admin/batches',
        'available' => $canViewInventory || $canViewBatches,
    ],
    ['key' => 'history', 'label' => 'Kiểm kê và nhật ký', 'url' => '/admin/inventory/stocktakes', 'available' => $canViewInventory],
];
?>
<nav class="inventory-admin-navigation mb-4" aria-label="Điều hướng kho và nguồn gốc">
    <div class="nav nav-tabs inventory-admin-navigation__tabs">
        <?php foreach ($inventoryPrimaryTabs as $tab): ?>
            <?php if (!$tab['available']) { continue; } ?>
            <a class="nav-link<?= $inventoryNavigationSection === $tab['key'] ? ' active' : '' ?>"
               href="<?= e(url($tab['url'])) ?>"
               <?= $inventoryNavigationSection === $tab['key'] ? 'aria-current="page"' : '' ?>><?= e($tab['label']) ?></a>
        <?php endforeach; ?>
    </div>
    <?php if ($inventoryNavigationSection === 'stock'): ?>
        <div class="nav nav-pills inventory-admin-navigation__subtabs" aria-label="Tab hàng trong kho">
            <?php if ($canViewInventory): ?>
                <a class="nav-link<?= $isInventoryStockPage ? ' active' : '' ?>"
                   href="<?= e(url('/admin/inventory/stock')) ?>"
                   <?= $isInventoryStockPage ? 'aria-current="page"' : '' ?>>Tồn kho</a>
            <?php endif; ?>
            <?php if ($canViewBatches): ?>
                <a class="nav-link<?= $isBatchPage ? ' active' : '' ?>"
                   href="<?= e(url('/admin/batches')) ?>"
                   <?= $isBatchPage ? 'aria-current="page"' : '' ?>>Danh sách lô</a>
            <?php endif; ?>
        </div>
    <?php elseif ($inventoryNavigationSection === 'history'): ?>
        <div class="nav nav-pills inventory-admin-navigation__subtabs" aria-label="Tab kiểm kê và nhật ký">
            <a class="nav-link<?= $isStocktakePage ? ' active' : '' ?>"
               href="<?= e(url('/admin/inventory/stocktakes')) ?>"
               <?= $isStocktakePage ? 'aria-current="page"' : '' ?>>Kiểm kê</a>
            <a class="nav-link<?= $isMovementPage ? ' active' : '' ?>"
               href="<?= e(url('/admin/inventory/movements')) ?>"
               <?= $isMovementPage ? 'aria-current="page"' : '' ?>>Nhật ký kho</a>
        </div>
    <?php endif; ?>
</nav>
