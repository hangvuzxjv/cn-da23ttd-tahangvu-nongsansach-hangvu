<?php

declare(strict_types=1);

final class HomeController extends Controller
{
    public function index(): void
    {
        (new StorefrontController())->home();
    }

    public function product(string $slug): void
    {
        (new StorefrontController())->product($slug);
    }

    public function user(string $id): void
    {
        $this->view('client/user', [
            'title' => 'Thông tin người dùng',
            'metaDescription' => 'Thông tin người dùng trong ứng dụng.',
            'message' => 'Mã người dùng: ' . $id,
        ]);
    }
}
