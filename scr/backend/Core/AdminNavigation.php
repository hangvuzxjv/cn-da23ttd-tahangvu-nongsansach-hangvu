<?php

declare(strict_types=1);

final class AdminNavigation
{
    private static ?array $cachedGroups = null;

    public static function groups(): array
    {
        if (self::$cachedGroups !== null) {
            return self::$cachedGroups;
        }

        $user = Auth::user();
        $permissions = require BACKEND_PATH . '/config/permissions.php';
        $rolePermissions = $user === null ? [] : ($permissions[$user['role']] ?? []);
        $can = static fn (string $permission): bool => in_array('*', $rolePermissions, true)
            || in_array($permission, $rolePermissions, true);
        $groups = [
            [
                'label' => 'Tổng quan',
                'items' => [
                    ['permission' => 'dashboard.view', 'label' => 'Bảng điều khiển', 'url' => '/admin'],
                ],
            ],
            [
                'label' => 'Bán hàng',
                'items' => [
                    ['permission' => 'orders.view', 'label' => 'Đơn hàng', 'url' => '/admin/orders'],
                    ['permission' => 'orders.view', 'label' => 'Thanh toán', 'url' => '/admin/payments'],
                    ['permission' => 'vouchers.view', 'label' => 'Voucher', 'url' => '/admin/vouchers'],
                    ['permission' => 'shipping.view', 'label' => 'Phí vận chuyển', 'url' => '/admin/shipping'],
                    ['permission' => 'reviews.view', 'label' => 'Đánh giá', 'url' => '/admin/reviews'],
                ],
            ],
            [
                'label' => 'Kho và nguồn gốc',
                'items' => [
                    ['permission' => 'inventory.view', 'label' => 'Kho hàng', 'url' => '/admin/inventory'],
                    ['permission' => 'inventory.view', 'label' => 'Tồn kho', 'url' => '/admin/inventory/stock'],
                    ['permission' => 'inventory.view', 'label' => 'Phiếu nhập', 'url' => '/admin/inventory/receipts'],
                    ['permission' => 'inventory.view', 'label' => 'Lịch sử kho', 'url' => '/admin/inventory/movements'],
                    ['permission' => 'inventory.view', 'label' => 'Kiểm kê', 'url' => '/admin/inventory/stocktakes'],
                    ['permission' => 'batches.view', 'label' => 'Lô hàng', 'url' => '/admin/batches'],
                    ['permission' => 'farms.view', 'label' => 'Nông trại', 'url' => '/admin/farms'],
                ],
            ],
            [
                'label' => 'Danh mục',
                'items' => [
                    ['permission' => 'regions.view', 'label' => 'Vùng miền', 'url' => '/admin/regions'],
                    ['permission' => 'categories.view', 'label' => 'Danh mục', 'url' => '/admin/categories'],
                    ['permission' => 'products.view', 'label' => 'Sản phẩm', 'url' => '/admin/products'],
                ],
            ],
            [
                'label' => 'Flash Sale',
                'items' => [
                    ['permission' => 'flash_sales.view', 'label' => 'Flash Sale', 'url' => '/admin/flash-sales'],
                ],
            ],
            [
                'label' => 'Khách hàng và Người dùng',
                'items' => [
                    ['permission' => 'customers.view', 'label' => 'Khách hàng', 'url' => '/admin/customers'],
                    ['permission' => 'users.view', 'label' => 'Người dùng', 'url' => '/admin/users'],
                ],
            ],
            [
                'label' => 'Báo cáo',
                'items' => [
                    ['permission' => 'reports.view', 'label' => 'Báo cáo', 'url' => '/admin/reports'],
                ],
            ],
            [
                'label' => 'Nội dung và liên hệ',
                'items' => [
                    ['permission' => 'content.view', 'label' => 'Nội dung', 'url' => '/admin/content/pages'],
                    ['permission' => 'contacts.view', 'label' => 'Liên hệ', 'url' => '/admin/contacts'],
                    ['permission' => 'mail.view', 'label' => 'Email / Hộp thư chờ', 'url' => '/admin/mail'],
                ],
            ],
        ];

        $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
        if (defined('BASE_URL') && BASE_URL !== '' && str_starts_with($requestPath, BASE_URL . '/')) {
            $requestPath = substr($requestPath, strlen(BASE_URL));
        }
        $items = [];
        foreach ($groups as &$group) {
            $group['items'] = array_values(array_filter(
                $group['items'],
                static fn (array $item): bool => $can($item['permission'])
            ));
            foreach ($group['items'] as $item) {
                $items[] = $item;
            }
        }
        unset($group);

        $activeUrl = '';
        foreach ($items as $item) {
            $candidatePaths = [$item['url']];
            if ($item['url'] === '/admin/batches') {
                $candidatePaths[] = '/admin/labels';
            }
            foreach ($candidatePaths as $candidatePath) {
                $matches = $requestPath === $candidatePath
                    || ($candidatePath !== '/admin' && str_starts_with($requestPath, $candidatePath . '/'));
                if ($matches && strlen($candidatePath) > strlen($activeUrl)) {
                    $activeUrl = $item['url'];
                }
            }
        }

        foreach ($groups as &$group) {
            $group['items'] = array_map(
                static function (array $item) use ($activeUrl): array {
                    $item['active'] = $item['url'] === $activeUrl;
                    return $item;
                },
                $group['items']
            );
        }
        unset($group);

        self::$cachedGroups = array_values(array_filter(
            $groups,
            static fn (array $group): bool => $group['items'] !== []
        ));
        return self::$cachedGroups;
    }

    public static function quickLinks(): array
    {
        $links = [];
        foreach (self::groups() as $group) {
            foreach ($group['items'] as $item) {
                if ($item['url'] !== '/admin') {
                    $links[] = ['label' => $item['label'], 'url' => $item['url'], 'group' => $group['label']];
                }
            }
        }

        return $links;
    }
}
