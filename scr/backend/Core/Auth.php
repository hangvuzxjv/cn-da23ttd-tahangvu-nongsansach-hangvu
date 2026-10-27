<?php

declare(strict_types=1);

final class Auth
{
    public static function check(): bool
    {
        Session::start();
        return isset($_SESSION['user']) && is_array($_SESSION['user']);
    }

    public static function user(): ?array
    {
        return self::check() ? $_SESSION['user'] : null;
    }

    public static function login(array $user): void
    {
        Session::start();
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
    }

    public static function logout(): void
    {
        Session::start();
        unset($_SESSION['user']);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect('/login');
        }
    }

    public static function requireRole(string $role): void
    {
        self::requireLogin();
        if ((self::user()['role'] ?? null) !== $role) {
            http_response_code(403);
            echo '403 - Bạn không có quyền truy cập.';
            exit;
        }
    }
}
