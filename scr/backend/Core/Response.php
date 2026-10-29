<?php

declare(strict_types=1);

final class Response
{
    public function __construct(
        private string $content = '',
        private int $status = 200,
        private array $headers = []
    ) {
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => url($url)]);
    }

    public static function json(bool $success, string $message, array $data = [], int $status = 200): self
    {
        return new self(
            json_encode([
                'success' => $success,
                'message' => $message,
                'data' => $data,
            ], JSON_THROW_ON_ERROR),
            $status,
            [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Cache-Control' => 'no-store',
            ]
        );
    }

    public function send(): void
    {
        if ($this->status === 419) {
            header('HTTP/1.1 419 Page Expired', true);
        } else {
            http_response_code($this->status);
        }
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->content;
    }
}
