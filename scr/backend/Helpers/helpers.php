<?php

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . ($path === '' ? '/' : '/' . ltrim($path, '/'));
}

function asset(string $path): string
{
    return url($path);
}

function redirect(string $path): never
{
    header('Location: ' . url($path), true, 302);
    exit;
}

function flash(string $key, mixed $value = null): mixed
{
    Session::start();
    if (func_num_args() > 1) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }

    $message = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $message;
}

function old(string $key, mixed $default = ''): mixed
{
    Session::start();
    $value = $_SESSION['_old'][$key] ?? $default;
    unset($_SESSION['_old'][$key]);
    return $value;
}

function form_errors(): array
{
    $errors = flash('validation_errors');
    return is_array($errors) ? $errors : [];
}

function has_permission(string $permission): bool
{
    return Auth::can($permission);
}

function absolute_url(string $path): string
{
    $config = require BACKEND_PATH . '/config/config.php';
    $baseUrl = rtrim((string) ($config['app']['url'] ?? ''), '/');
    $parts = parse_url($baseUrl);
    if (
        $parts === false
        || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
        || empty($parts['host'])
        || isset($parts['user'])
        || isset($parts['pass'])
        || isset($parts['query'])
        || isset($parts['fragment'])
    ) {
        throw new RuntimeException('Cấu hình app.url phải là URL http/https hợp lệ, không chứa thông tin đăng nhập.');
    }

    return $baseUrl . '/' . ltrim($path, '/');
}

function remember_form_data(array $data): void
{
    Session::start();
    unset($data['password'], $data['password_confirmation'], $data['current_password'], $data['new_password']);
    $_SESSION['_old'] = $data;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}
