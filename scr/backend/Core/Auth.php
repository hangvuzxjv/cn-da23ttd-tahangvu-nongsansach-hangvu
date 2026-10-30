<?php

declare(strict_types=1);

final class Auth
{
    public static function check(): bool
    {
        Session::start();
        if (!isset($_SESSION['user']['id'])) {
            return false;
        }

        $statement = Database::connection()->prepare(
            "SELECT id, name, email, phone, role, status FROM users WHERE id = :id LIMIT 1"
        );
        $statement->execute(['id' => $_SESSION['user']['id']]);
        $user = $statement->fetch();
        if (!$user || $user['status'] !== 'active') {
            unset($_SESSION['user']);
            return false;
        }

        $_SESSION['user'] = $user;
        return true;
    }

    public static function user(): ?array
    {
        return self::check() ? $_SESSION['user'] : null;
    }

    public static function login(array $user): void
    {
        Session::start();
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'role' => $user['role'],
            'status' => $user['status'],
        ];
        $_SESSION['_last_activity'] = time();
    }

    public static function logout(): void
    {
        Session::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => 'Lax',
            ]);
        }
        session_destroy();
    }

    public static function requireLogin(?Request $request = null): void
    {
        if (!self::check()) {
            if ($request !== null && $request->expectsJson()) {
                throw new HttpException(401, 'Vui lòng đăng nhập để tiếp tục.');
            }
            Session::start();
            $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
            if (is_internal_redirect_path($requestUri)) {
                $_SESSION['_intended_url'] = $requestUri;
            }
            redirect('/login');
        }
    }

    public static function can(string $permission): bool
    {
        $user = self::user();
        if ($user === null) {
            return false;
        }

        $permissions = require BACKEND_PATH . '/config/permissions.php';
        $rolePermissions = $permissions[$user['role']] ?? [];
        return in_array('*', $rolePermissions, true) || in_array($permission, $rolePermissions, true);
    }

    public static function requirePermission(string $permission, ?Request $request = null): void
    {
        self::requireLogin($request);
        if (!self::can($permission)) {
            if ($request !== null && $request->expectsJson()) {
                throw new HttpException(403, 'Bạn không có quyền thực hiện thao tác này.');
            }
            View::renderError(403);
            exit;
        }
    }

    public static function requireRole(string $role, ?Request $request = null): void
    {
        self::requireLogin($request);
        if ((self::user()['role'] ?? null) !== $role) {
            if ($request !== null && $request->expectsJson()) {
                throw new HttpException(403, 'Bạn không có quyền thực hiện thao tác này.');
            }
            View::renderError(403);
            exit;
        }
    }
}
