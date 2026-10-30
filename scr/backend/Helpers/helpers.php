<?php

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    if (preg_match('/\Ahttps?:\/\//i', $path) === 1) {
        return $path;
    }

    return BASE_URL . ($path === '' ? '/' : '/' . ltrim($path, '/'));
}

function redirect_target(string $path, ?array $redirectConfig = null): string
{
    if (preg_match('/\Ahttps?:\/\//i', $path) === 1) {
        $config = $redirectConfig ?? require BACKEND_PATH . '/config/config.php';
        $vnpay = $config['vnpay'] ?? [];
        $configuredUrl = (string) ($vnpay['url'] ?? '');
        $parts = parse_url($path);
        $configuredParts = parse_url($configuredUrl);
        if (
            $parts === false
            || $configuredParts === false
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || strtolower((string) ($configuredParts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
            || empty($configuredParts['host'])
            || strtolower((string) ($parts['host'] ?? '')) !== strtolower((string) ($configuredParts['host'] ?? ''))
            || ($parts['port'] ?? null) !== ($configuredParts['port'] ?? null)
            || ($parts['path'] ?? '') !== ($configuredParts['path'] ?? '')
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
            || isset($configuredParts['user'])
            || isset($configuredParts['pass'])
            || isset($configuredParts['query'])
            || isset($configuredParts['fragment'])
        ) {
            throw new InvalidArgumentException('Địa chỉ chuyển hướng ngoài không được phép.');
        }

        parse_str((string) ($parts['query'] ?? ''), $parameters);
        $signature = $parameters['vnp_SecureHash'] ?? null;
        unset($parameters['vnp_SecureHash'], $parameters['vnp_SecureHashType']);
        if (!is_string($signature) || preg_match('/\A[a-f0-9]{128}\z/i', $signature) !== 1) {
            throw new InvalidArgumentException('Chữ ký URL VNPay không hợp lệ.');
        }
        foreach ($parameters as $value) {
            if (!is_scalar($value) && $value !== null) {
                throw new InvalidArgumentException('Tham số URL VNPay không hợp lệ.');
            }
        }
        $hashSecret = trim((string) ($vnpay['hash_secret'] ?? ''));
        if (!VnpaySignature::verify($parameters, trim($hashSecret), $signature)) {
            throw new InvalidArgumentException('Chữ ký URL VNPay không hợp lệ.');
        }
        if (!hash_equals(trim((string) ($vnpay['tmn_code'] ?? '')), (string) ($parameters['vnp_TmnCode'] ?? ''))) {
            throw new InvalidArgumentException('Mã website trong URL VNPay không hợp lệ.');
        }

        return $path;
    }

    if (
        str_starts_with($path, '//')
        || str_contains($path, '\\')
        || preg_match('/\A[a-z][a-z0-9+.-]*:/i', $path) === 1
        || preg_match('/[\r\n]/', $path) === 1
    ) {
        throw new InvalidArgumentException('Chỉ chấp nhận đường dẫn nội bộ hoặc URL VNPay hợp lệ.');
    }

    return url($path);
}

function is_internal_redirect_path(string $path): bool
{
    return str_starts_with($path, '/')
        && !str_starts_with($path, '//')
        && !str_contains($path, '\\')
        && preg_match('/[\r\n]/', $path) !== 1;
}

function asset(string $path): string
{
    return url($path);
}

function redirect(string $path): never
{
    header('Location: ' . redirect_target($path), true, 302);
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
