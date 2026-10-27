<?php

declare(strict_types=1);

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        $this->view('client/login', ['title' => 'Đăng nhập']);
    }

    public function login(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        if ($email === '') {
            flash('error', 'Vui lòng nhập email để đăng nhập giả lập.');
            redirect('/login');
        }

        Auth::login(['name' => $email, 'email' => $email, 'role' => 'admin']);
        redirect('/admin');
    }

    public function logout(): void
    {
        Auth::logout();
        redirect('/');
    }
}
