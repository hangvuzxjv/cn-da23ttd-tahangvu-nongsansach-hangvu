<?php

declare(strict_types=1);

final class Csrf
{
    public static function token(): string
    {
        Session::start();
        if (!isset($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    public static function verify(Request $request): void
    {
        if ($request->method() !== 'POST') {
            return;
        }

        $submittedToken = $request->input('_token');
        if (!is_string($submittedToken) || !hash_equals(self::token(), $submittedToken)) {
            if ($request->expectsJson()) {
                throw new HttpException(419, 'Phiên làm việc đã hết hạn. Hãy tải lại trang và thử lại.');
            }
            View::renderError(419);
            exit;
        }
    }
}
