<?php

declare(strict_types=1);

final class AdminController extends Controller
{
    private const SECTIONS = [
        'orders' => ['permission' => 'orders.view', 'title' => 'Đơn hàng'],
        'vouchers' => ['permission' => 'vouchers.view', 'title' => 'Voucher'],
        'reviews' => ['permission' => 'reviews.view', 'title' => 'Đánh giá'],
        'customers' => ['permission' => 'customers.view', 'title' => 'Khách hàng'],
        'inventory' => ['permission' => 'inventory.view', 'title' => 'Kho hàng'],
        'batches' => ['permission' => 'batches.view', 'title' => 'Lô hàng'],
        'products' => ['permission' => 'products.view', 'title' => 'Sản phẩm'],
    ];

    public function index(): void
    {
        $this->view('admin/dashboard', [
            'title' => 'Quản trị',
            'user' => Auth::user(),
            'menu' => $this->menu(),
        ], 'admin');
    }

    public function section(string $section): void
    {
        if (!isset(self::SECTIONS[$section])) {
            http_response_code(404);
            View::renderError(404);
            return;
        }
        $this->view('admin/section', [
            'title' => self::SECTIONS[$section]['title'],
            'sectionTitle' => self::SECTIONS[$section]['title'],
            'user' => Auth::user(),
            'menu' => $this->menu(),
        ], 'admin');
    }

    public function users(): void
    {
        $query = trim((string) ($_GET['q'] ?? ''));
        $role = (string) ($_GET['role'] ?? '');
        $validRoles = ['customer', 'admin', 'staff_sales', 'staff_warehouse'];
        $conditions = [];
        $parameters = [];
        if ($query !== '') {
            $conditions[] = '(name LIKE :name_query OR email LIKE :email_query OR phone LIKE :phone_query)';
            $search = '%' . $query . '%';
            $parameters['name_query'] = $search;
            $parameters['email_query'] = $search;
            $parameters['phone_query'] = $search;
        }
        if (in_array($role, $validRoles, true)) {
            $conditions[] = 'role = :role';
            $parameters['role'] = $role;
        } else {
            $role = '';
        }
        $sql = 'SELECT id, name, email, phone, role, status, created_at FROM users'
            . ($conditions ? ' WHERE ' . implode(' AND ', $conditions) : '')
            . ' ORDER BY id DESC LIMIT 200';
        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);
        $this->view('admin/users', [
            'title' => 'Quản lý người dùng',
            'users' => $statement->fetchAll(),
            'query' => $query,
            'role' => $role,
            'roles' => $validRoles,
            'currentUserId' => (int) Auth::user()['id'],
            'canManage' => Auth::can('users.manage'),
            'menu' => $this->menu(),
            'success' => flash('success'),
            'error' => flash('error'),
        ], 'admin');
    }

    public function customers(): void
    {
        $statement = Database::connection()->query(
            "SELECT id, name, email, phone, role, status, created_at FROM users
             WHERE role = 'customer' ORDER BY id DESC LIMIT 200"
        );
        $this->view('admin/users', [
            'title' => 'Khách hàng',
            'users' => $statement->fetchAll(),
            'query' => '',
            'role' => 'customer',
            'roles' => ['customer'],
            'currentUserId' => (int) Auth::user()['id'],
            'canManage' => false,
            'menu' => $this->menu(),
            'success' => null,
            'error' => null,
        ], 'admin');
    }

    public function updateUser(string $id): void
    {
        $actor = Auth::user();
        $targetId = (int) $id;
        if ($targetId === (int) $actor['id']) {
            flash('error', 'Bạn không thể tự khóa tài khoản hoặc tự đổi vai trò của mình.');
            redirect('/admin/users');
        }
        $roles = ['customer', 'admin', 'staff_sales', 'staff_warehouse'];
        $statuses = ['active', 'inactive', 'banned'];
        $role = (string) ($_POST['role'] ?? '');
        $status = (string) ($_POST['status'] ?? '');
        if (!in_array($role, $roles, true) || !in_array($status, $statuses, true)) {
            flash('error', 'Vai trò hoặc trạng thái không hợp lệ.');
            redirect('/admin/users');
        }
        $database = Database::connection();
        $statement = $database->prepare('SELECT role, status FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $targetId]);
        $before = $statement->fetch();
        if (!$before) {
            flash('error', 'Không tìm thấy người dùng.');
            redirect('/admin/users');
        }
        $database->beginTransaction();
        try {
            $database->prepare('UPDATE users SET role = :role, status = :status WHERE id = :id')
                ->execute(['role' => $role, 'status' => $status, 'id' => $targetId]);
            if ($before['role'] !== $role) {
                AuditLog::record((int) $actor['id'], 'user_role_changed', 'user', $targetId, $before['role'] . ' -> ' . $role);
            }
            if ($before['status'] !== $status) {
                AuditLog::record((int) $actor['id'], $status === 'active' ? 'user_unlocked' : 'user_locked', 'user', $targetId, $before['status'] . ' -> ' . $status);
            }
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
        flash('success', 'Đã cập nhật vai trò và trạng thái người dùng.');
        redirect('/admin/users');
    }

    private function menu(): array
    {
        $items = [
            ['permission' => 'dashboard.view', 'label' => 'Bảng điều khiển', 'url' => '/admin'],
            ['permission' => 'orders.view', 'label' => 'Đơn hàng', 'url' => '/admin/orders'],
            ['permission' => 'vouchers.view', 'label' => 'Voucher', 'url' => '/admin/vouchers'],
            ['permission' => 'reviews.view', 'label' => 'Đánh giá', 'url' => '/admin/reviews'],
            ['permission' => 'customers.view', 'label' => 'Khách hàng', 'url' => '/admin/customers'],
            ['permission' => 'inventory.view', 'label' => 'Kho hàng', 'url' => '/admin/inventory'],
            ['permission' => 'batches.view', 'label' => 'Lô hàng', 'url' => '/admin/batches'],
            ['permission' => 'regions.view', 'label' => 'Vùng miền', 'url' => '/admin/regions'],
            ['permission' => 'categories.view', 'label' => 'Danh mục', 'url' => '/admin/categories'],
            ['permission' => 'products.view', 'label' => 'Sản phẩm', 'url' => '/admin/products'],
            ['permission' => 'users.view', 'label' => 'Người dùng', 'url' => '/admin/users'],
        ];
        return array_values(array_filter($items, static fn (array $item): bool => Auth::can($item['permission'])));
    }
}
