<?php

declare(strict_types=1);

final class AdminController extends Controller
{
    public function index(): void
    {
        $this->view('admin/dashboard', ['title' => 'Quản trị'], 'admin');
    }
}
