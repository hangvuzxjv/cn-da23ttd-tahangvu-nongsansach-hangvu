<?php

declare(strict_types=1);

final class HomeController extends Controller
{
    public function index(): void
    {
        $this->view('client/home', ['title' => 'Trang chủ']);
    }

    public function product(string $slug): void
    {
        http_response_code(404);
        View::renderError(404);
    }

    public function user(string $id): void
    {
        $this->view('client/home', ['title' => 'Thông tin người dùng', 'message' => 'Mã người dùng: ' . $id]);
    }
}
