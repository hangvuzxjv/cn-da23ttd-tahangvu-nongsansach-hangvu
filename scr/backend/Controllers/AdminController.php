<?php

declare(strict_types=1);

final class AdminController extends Controller
{
    private const USER_PAGE_SIZE = 20;

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
        $canViewBatches = Auth::can('batches.view');
        $topScanned = $canViewBatches
            ? Database::connection()->query(
                'SELECT b.id, b.batch_code, p.name AS product_name, COUNT(s.id) AS scan_count
                 FROM trace_scans s JOIN product_batches b ON b.id = s.batch_id
                 JOIN products p ON p.id = b.product_id
                 GROUP BY b.id, b.batch_code, p.name
                 ORDER BY scan_count DESC, b.id DESC LIMIT 10'
            )->fetchAll()
            : [];
        $dashboard = Auth::can('reports.view') ? (new ReportService())->dashboard() : null;
        $this->view('admin/dashboard', [
            'title' => 'Quản trị',
            'user' => Auth::user(),
            'topScanned' => $topScanned,
            'canViewBatches' => $canViewBatches,
            'dashboard' => $dashboard,
            'menu' => $this->menu(),
            'quickLinks' => AdminNavigation::quickLinks(),
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
        $database = Database::connection();
        $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
        $count = $database->prepare('SELECT COUNT(*) FROM users' . $where);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / self::USER_PAGE_SIZE));
        $requestedPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
        $page = max(1, $requestedPage === false ? 1 : $requestedPage);
        $page = min($page, $pages);
        $sql = 'SELECT id, name, email, phone, role, status, created_at FROM users'
            . $where . ' ORDER BY id DESC LIMIT :limit OFFSET :offset';
        $statement = $database->prepare($sql);
        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . $name, $value, PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', self::USER_PAGE_SIZE, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * self::USER_PAGE_SIZE, PDO::PARAM_INT);
        $statement->execute();
        $this->view('admin/users', [
            'title' => 'Quản lý người dùng',
            'users' => $statement->fetchAll(),
            'query' => $query,
            'role' => $role,
            'roles' => $validRoles,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'listUrl' => url('/admin/users'),
            'currentUserId' => (int) Auth::user()['id'],
            'canManage' => Auth::can('users.manage'),
            'menu' => $this->menu(),
            'success' => flash('success'),
            'error' => flash('error'),
        ], 'admin');
    }

    public function customers(): void
    {
        $database = Database::connection();
        $query = trim((string) ($_GET['q'] ?? ''));
        $parameters = [];
        $where = "role = 'customer'";
        if ($query !== '') {
            $where .= ' AND (name LIKE :name_query OR email LIKE :email_query OR phone LIKE :phone_query)';
            $search = '%' . $query . '%';
            $parameters = [
                'name_query' => $search,
                'email_query' => $search,
                'phone_query' => $search,
            ];
        }
        $count = $database->prepare('SELECT COUNT(*) FROM users WHERE ' . $where);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / self::USER_PAGE_SIZE));
        $requestedPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
        $page = min(max(1, $requestedPage === false ? 1 : $requestedPage), $pages);
        $statement = $database->prepare(
            "SELECT id, name, email, phone, role, status, created_at FROM users
             WHERE " . $where . ' ORDER BY id DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . $name, $value, PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', self::USER_PAGE_SIZE, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * self::USER_PAGE_SIZE, PDO::PARAM_INT);
        $statement->execute();
        $this->view('admin/users', [
            'title' => 'Khách hàng',
            'users' => $statement->fetchAll(),
            'query' => $query,
            'role' => 'customer',
            'roles' => ['customer'],
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'listUrl' => url('/admin/customers'),
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

    public function menu(): array
    {
        $items = [];
        foreach (AdminNavigation::groups() as $group) {
            foreach ($group['items'] as $item) {
                unset($item['active']);
                $items[] = $item;
            }
        }

        return $items;
    }
}
