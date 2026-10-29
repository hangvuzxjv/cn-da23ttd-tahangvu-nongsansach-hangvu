<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

define('ROOT_PATH', __DIR__);
define('BACKEND_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'backend');
define('FRONTEND_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'frontend');

$composerAutoloads = [
    dirname(ROOT_PATH) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php',
    ROOT_PATH . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php',
];
foreach ($composerAutoloads as $composerAutoload) {
    if (is_file($composerAutoload)) {
        require $composerAutoload;
        break;
    }
}

$scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$baseUrl = rtrim($scriptDirectory, '/');
define('BASE_URL', $baseUrl === '.' ? '' : $baseUrl);

require BACKEND_PATH . '/Helpers/helpers.php';

spl_autoload_register(static function (string $class): void {
    $path = BACKEND_PATH . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . $class . '.php';

    if (is_file($path)) {
        require $path;
        return;
    }

    $controllerPath = BACKEND_PATH . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . $class . '.php';
    if (is_file($controllerPath)) {
        require $controllerPath;
        return;
    }

    $mailerPath = BACKEND_PATH . DIRECTORY_SEPARATOR . 'Mailers' . DIRECTORY_SEPARATOR . $class . '.php';
    if (is_file($mailerPath)) {
        require $mailerPath;
    }
});

$request = null;
try {
    $request = Request::capture();
    $config = require BACKEND_PATH . '/config/config.php';
    Session::start();

    $router = new Router();
    require BACKEND_PATH . '/routes.php';
    $router->dispatch($request);
} catch (Throwable $exception) {
    if (!($exception instanceof HttpException) || $exception->status() >= 500) {
        error_log(sprintf(
            "[%s] %s in %s:%d\n%s\n",
            date(DATE_ATOM),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        ), 3, BACKEND_PATH . '/storage/logs/app.log');
    }

    if ($request !== null && $request->expectsJson()) {
        $status = $exception instanceof HttpException ? $exception->status() : 500;
        $message = $status === 500
            ? 'Đã xảy ra lỗi máy chủ. Vui lòng thử lại sau.'
            : $exception->getMessage();
        Response::json(false, $message, [], $status)->send();
        return;
    }

    http_response_code(500);
    try {
        View::renderError(500, $exception, (bool) ($config['app']['debug'] ?? false));
    } catch (Throwable $renderException) {
        error_log(sprintf(
            "[%s] Unable to render error page: %s in %s:%d\n",
            date(DATE_ATOM),
            $renderException->getMessage(),
            $renderException->getFile(),
            $renderException->getLine()
        ), 3, BACKEND_PATH . '/storage/logs/app.log');
        echo '<h1>500 - Lỗi máy chủ</h1>';
    }
}
