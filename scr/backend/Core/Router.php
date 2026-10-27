<?php

declare(strict_types=1);

final class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function add(string $method, string $path, callable|array $handler, array $middleware = []): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => '/' . trim($path, '/'),
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method()) {
                continue;
            }

            $parameters = $this->match($route['path'], $request->path());
            if ($parameters === null) {
                continue;
            }

            foreach ($route['middleware'] as $middleware) {
                $this->runMiddleware($middleware, $request);
            }

            $handler = $route['handler'];
            if (is_array($handler) && is_string($handler[0])) {
                $handler[0] = new $handler[0]();
            }

            $response = call_user_func_array($handler, array_values($parameters));
            if ($response instanceof Response) {
                $response->send();
            }
            return;
        }

        http_response_code(404);
        error_log(sprintf("[%s] 404 %s\n", date(DATE_ATOM), $request->path()), 3, BACKEND_PATH . '/storage/logs/app.log');
        View::renderError(404);
    }

    private function match(string $routePath, string $requestPath): ?array
    {
        $pattern = preg_replace_callback('/\{(id|slug)\}/', static function (array $matches): string {
            return $matches[1] === 'id' ? '(?P<id>\d+)' : '(?P<slug>[A-Za-z0-9_-]+)';
        }, $routePath);

        if (!preg_match('#^' . $pattern . '/?$#', $requestPath, $matches)) {
            return null;
        }

        return array_filter($matches, static fn ($key): bool => is_string($key), ARRAY_FILTER_USE_KEY);
    }

    private function runMiddleware(string $middleware, Request $request): void
    {
        if ($middleware === 'csrf') {
            Csrf::verify($request);
            return;
        }

        if ($middleware === 'auth') {
            Auth::requireLogin();
            return;
        }

        if (str_starts_with($middleware, 'role:')) {
            Auth::requireRole(substr($middleware, 5));
            return;
        }

        throw new RuntimeException('Middleware không được hỗ trợ: ' . $middleware);
    }
}
