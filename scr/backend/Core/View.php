<?php

declare(strict_types=1);

final class View
{
    public static function render(string $template, array $data = [], string $layout = 'client'): void
    {
        $viewPath = FRONTEND_PATH . '/views/' . $template . '.php';
        $layoutPath = FRONTEND_PATH . '/views/layouts/' . $layout . '.php';
        if (!is_file($viewPath) || !is_file($layoutPath)) {
            throw new RuntimeException('Không tìm thấy view hoặc layout.');
        }

        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $viewPath;
            $content = (string) ob_get_clean();
        } catch (Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
        require $layoutPath;
    }

    public static function renderError(int $status, ?Throwable $exception = null, bool $debug = false): void
    {
        http_response_code($status);
        $template = FRONTEND_PATH . '/views/errors/' . $status . '.php';
        if (!is_file($template)) {
            echo $status === 404 ? '404 - Không tìm thấy trang.' : '500 - Lỗi máy chủ.';
            return;
        }

        $detail = $debug && $exception !== null
            ? $exception->getMessage() . "\n" . $exception->getFile() . ':' . $exception->getLine()
            : null;
        $content = null;
        require $template;
        require FRONTEND_PATH . '/views/layouts/client.php';
    }
}
