<?php

declare(strict_types=1);

final class Request
{
    private function __construct(
        private string $httpMethod,
        private string $requestPath,
        private array $query,
        private array $body
    ) {
    }

    public static function capture(): self
    {
        $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $basePath = BASE_URL;
        if ($basePath !== '' && ($uriPath === $basePath || str_starts_with($uriPath, $basePath . '/'))) {
            $uriPath = substr($uriPath, strlen($basePath)) ?: '/';
        }

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            '/' . trim(rawurldecode($uriPath), '/'),
            $_GET,
            $_POST
        );
    }

    public function method(): string
    {
        return $this->httpMethod;
    }

    public function path(): string
    {
        return $this->requestPath;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }
}
