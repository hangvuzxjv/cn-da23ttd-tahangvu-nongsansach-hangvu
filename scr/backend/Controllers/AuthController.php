<?php

declare(strict_types=1);

final class AuthController extends Controller
{
    private const GENERIC_LOGIN_ERROR = 'Email hoặc mật khẩu không đúng, hoặc tài khoản tạm thời bị khóa.';

    public function showLogin(): void
    {
        $this->view('client/login', [
            'title' => 'Đăng nhập',
            'errors' => form_errors(),
            'mailLink' => flash('mail_link'),
        ]);
    }

    public function showRegister(): void
    {
        $this->view('client/register', [
            'title' => 'Tạo tài khoản',
            'errors' => form_errors(),
        ]);
    }

    public function register(): void
    {
        $input = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'email' => strtolower(trim((string) ($_POST['email'] ?? ''))),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
        ];
        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');
        $errors = $this->validateRegistration($input, $password, $confirmation);
        if ($errors !== []) {
            $this->failValidation('/register', $input, $errors);
        }

        $database = Database::connection();
        $database->beginTransaction();
        try {
            $statement = $database->prepare(
                'INSERT INTO users (name, email, phone, password, role, status)
                 VALUES (:name, :email, :phone, :password, \'customer\', \'active\')'
            );
            $statement->execute([
                'name' => $input['name'],
                'email' => $input['email'],
                'phone' => $input['phone'],
                'password' => password_hash($password, PASSWORD_BCRYPT),
            ]);
            $userId = (int) $database->lastInsertId();
            $verificationToken = bin2hex(random_bytes(32));
            $verification = $database->prepare(
                'INSERT INTO email_verifications (user_id, token_hash, expires_at)
                 VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 60 MINUTE))'
            );
            $verification->execute([
                'user_id' => $userId,
                'token_hash' => hash('sha256', $verificationToken),
            ]);
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            if ($exception instanceof PDOException && $exception->getCode() === '23000') {
                $this->failValidation('/register', $input, ['email' => 'Email này đã được đăng ký.']);
            }
            throw $exception;
        }

        AuditLog::record($userId, 'register', 'user', $userId);
        $verifyUrl = absolute_url('/verify-email/' . $verificationToken);
        $mailerConfig = $this->mailerConfig();
        MailerFactory::make($mailerConfig)->send(
            $input['email'],
            'Xác thực email - Nông Sản Sạch',
            "Xin chào {$input['name']},\n\nXác thực email của bạn tại:\n{$verifyUrl}\n\nLiên kết có hiệu lực trong 60 phút."
        );
        Auth::login($this->findUserById($userId));
        (new CartService())->mergeSessionCart($userId);
        flash('success', 'Tạo tài khoản thành công. Bạn có thể mua hàng ngay; email xác thực là tùy chọn.');
        $this->exposeDemoLink($mailerConfig, $verifyUrl);
        redirect('/profile');
    }

    public function login(): void
    {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            remember_form_data(['email' => $email]);
            flash('validation_errors', ['login' => self::GENERIC_LOGIN_ERROR]);
            redirect('/login');
        }

        [$emailHash, $ipHash] = $this->attemptKey($email);
        if ($this->isLocked($emailHash, $ipHash)) {
            password_verify($password, self::dummyPasswordHash());
            AuditLog::record(null, 'login_failed', 'login_attempt', null, 'email_hash=' . $emailHash);
            remember_form_data(['email' => $email]);
            flash('validation_errors', ['login' => self::GENERIC_LOGIN_ERROR]);
            redirect('/login');
        }

        $statement = Database::connection()->prepare(
            'SELECT id, name, email, phone, password, role, status FROM users WHERE email = :email LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();
        $passwordMatches = password_verify($password, $user['password'] ?? self::dummyPasswordHash());
        if (!$user || !$passwordMatches || $user['status'] !== 'active') {
            $this->recordFailedAttempt($emailHash, $ipHash);
            AuditLog::record($user ? (int) $user['id'] : null, 'login_failed', 'login_attempt', null, 'email_hash=' . $emailHash);
            remember_form_data(['email' => $email]);
            flash('validation_errors', ['login' => self::GENERIC_LOGIN_ERROR]);
            redirect('/login');
        }

        $clearAttempts = Database::connection()->prepare(
            'DELETE FROM login_attempts WHERE email_hash = :email_hash AND ip_hash = :ip_hash'
        );
        $clearAttempts->execute(['email_hash' => $emailHash, 'ip_hash' => $ipHash]);
        Auth::login($user);
        (new CartService())->mergeSessionCart((int) $user['id']);
        AuditLog::record((int) $user['id'], 'login', 'user', (int) $user['id']);
        $intended = $this->takeIntendedUrl();
        redirect($intended ?? (($user['role'] === 'customer') ? '/' : '/admin'));
    }

    public function logout(): void
    {
        $user = Auth::user();
        if ($user !== null) {
            AuditLog::record((int) $user['id'], 'logout', 'user', (int) $user['id']);
        }
        Auth::logout();
        redirect('/');
    }

    public function showForgotPassword(): void
    {
        $this->view('client/forgot-password', [
            'title' => 'Quên mật khẩu',
            'errors' => form_errors(),
            'mailLink' => flash('mail_link'),
            'success' => flash('success'),
        ]);
    }

    public function forgotPassword(): void
    {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            remember_form_data(['email' => $email]);
            flash('validation_errors', ['email' => 'Vui lòng nhập email hợp lệ.']);
            redirect('/forgot-password');
        }

        $statement = Database::connection()->prepare('SELECT id, name FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();
        $mailLink = null;
        $mailerConfig = $this->mailerConfig();
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $database = Database::connection();
            $database->beginTransaction();
            try {
                $database->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = :user_id AND used_at IS NULL')
                    ->execute(['user_id' => $user['id']]);
                $tokenStatement = $database->prepare(
                    'INSERT INTO password_resets (user_id, token_hash, expires_at)
                     VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 60 MINUTE))'
                );
                $tokenStatement->execute([
                    'user_id' => $user['id'],
                    'token_hash' => hash('sha256', $token),
                ]);
                $database->commit();
            } catch (Throwable $exception) {
                if ($database->inTransaction()) {
                    $database->rollBack();
                }
                throw $exception;
            }
            $mailLink = absolute_url('/reset-password/' . $token);
            MailerFactory::make($mailerConfig)->send(
                $email,
                'Đặt lại mật khẩu - Nông Sản Sạch',
                "Xin chào {$user['name']},\n\nĐặt lại mật khẩu tại:\n{$mailLink}\n\nLiên kết chỉ dùng một lần và hết hạn sau 60 phút."
            );
        }

        flash('success', 'Nếu email thuộc tài khoản hợp lệ, hướng dẫn đặt lại mật khẩu sẽ được gửi.');
        if ($mailLink !== null) {
            $this->exposeDemoLink($mailerConfig, $mailLink);
        }
        redirect('/forgot-password');
    }

    public function showResetPassword(string $token): void
    {
        $statement = Database::connection()->prepare(
            'SELECT id FROM password_resets
             WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at > NOW() LIMIT 1'
        );
        $statement->execute(['token_hash' => hash('sha256', $token)]);
        if (!$statement->fetch()) {
            http_response_code(400);
            $this->view('client/reset-password', [
                'title' => 'Đặt lại mật khẩu',
                'token' => '',
                'errors' => ['token' => 'Liên kết không hợp lệ, đã dùng hoặc hết hạn.'],
            ]);
            return;
        }

        $this->view('client/reset-password', [
            'title' => 'Đặt lại mật khẩu',
            'token' => $token,
            'errors' => form_errors(),
        ]);
    }

    public function resetPassword(string $token): void
    {
        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');
        $errors = $this->validatePassword($password);
        if ($confirmation !== $password) {
            $errors['password_confirmation'] = 'Mật khẩu nhập lại không khớp.';
        }
        if ($errors !== []) {
            $this->failValidation('/reset-password/' . rawurlencode($token), [], $errors);
        }

        $database = Database::connection();
        $database->beginTransaction();
        try {
            $statement = $database->prepare(
                'SELECT id, user_id FROM password_resets
                 WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at > NOW()
                 LIMIT 1 FOR UPDATE'
            );
            $statement->execute(['token_hash' => hash('sha256', $token)]);
            $reset = $statement->fetch();
            if (!$reset) {
                $database->rollBack();
                http_response_code(400);
                $this->view('client/reset-password', [
                    'title' => 'Đặt lại mật khẩu',
                    'token' => '',
                    'errors' => ['token' => 'Liên kết không hợp lệ, đã dùng hoặc hết hạn.'],
                ]);
                return;
            }

            $update = $database->prepare('UPDATE users SET password = :password WHERE id = :user_id');
            $update->execute([
                'password' => password_hash($password, PASSWORD_BCRYPT),
                'user_id' => $reset['user_id'],
            ]);
            $database->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = :user_id AND used_at IS NULL')
                ->execute(['user_id' => $reset['user_id']]);
            AuditLog::record((int) $reset['user_id'], 'password_changed', 'user', (int) $reset['user_id'], 'via=password_reset');
            AuditLog::record((int) $reset['user_id'], 'password_reset', 'user', (int) $reset['user_id']);
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }

        flash('success', 'Mật khẩu đã được cập nhật. Vui lòng đăng nhập.');
        redirect('/login');
    }

    public function verifyEmail(string $token): void
    {
        $database = Database::connection();
        $statement = $database->prepare(
            'SELECT id, user_id FROM email_verifications
             WHERE token_hash = :token_hash AND verified_at IS NULL AND expires_at > NOW() LIMIT 1'
        );
        $statement->execute(['token_hash' => hash('sha256', $token)]);
        $verification = $statement->fetch();
        if (!$verification) {
            http_response_code(400);
            flash('error', 'Liên kết xác thực không hợp lệ hoặc đã hết hạn.');
            redirect(Auth::check() ? '/profile' : '/login');
        }

        $database->beginTransaction();
        try {
            $database->prepare('UPDATE email_verifications SET verified_at = NOW() WHERE id = :id')
                ->execute(['id' => $verification['id']]);
            $database->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = :user_id')
                ->execute(['user_id' => $verification['user_id']]);
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }

        flash('success', 'Email đã được xác thực.');
        redirect(Auth::check() ? '/profile' : '/login');
    }

    private function validateRegistration(array $input, string $password, string $confirmation): array
    {
        $errors = [];
        if ($input['name'] === '' || mb_strlen($input['name'], 'UTF-8') > 150) {
            $errors['name'] = 'Họ tên là bắt buộc và không vượt quá 150 ký tự.';
        }
        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL) || strlen($input['email']) > 190) {
            $errors['email'] = 'Vui lòng nhập email hợp lệ.';
        } else {
            $statement = Database::connection()->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $statement->execute(['email' => $input['email']]);
            if ($statement->fetch()) {
                $errors['email'] = 'Email này đã được đăng ký.';
            }
        }
        if (preg_match('/\A[0-9+()\-\s]{6,30}\z/', $input['phone']) !== 1) {
            $errors['phone'] = 'Vui lòng nhập số điện thoại hợp lệ (6-30 ký tự).';
        }
        return array_merge($errors, $this->validatePassword($password), $confirmation !== $password
            ? ['password_confirmation' => 'Mật khẩu nhập lại không khớp.']
            : []);
    }

    private function validatePassword(string $password): array
    {
        return strlen($password) <= 72 && preg_match('/\A(?=.{8,}\z)(?=.*\p{L})(?=.*\d).+\z/uD', $password) === 1
            ? []
            : ['password' => 'Mật khẩu cần ít nhất 8 ký tự, có chữ và số, tối đa 72 byte.'];
    }

    private function failValidation(string $path, array $input, array $errors): never
    {
        remember_form_data($input);
        flash('validation_errors', $errors);
        redirect($path);
    }

    private function attemptKey(string $email): array
    {
        return [
            hash('sha256', mb_strtolower($email, 'UTF-8')),
            hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')),
        ];
    }

    private function isLocked(string $emailHash, string $ipHash): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT locked_until IS NOT NULL AND locked_until > NOW()
             FROM login_attempts WHERE email_hash = :email_hash AND ip_hash = :ip_hash LIMIT 1'
        );
        $statement->execute(['email_hash' => $emailHash, 'ip_hash' => $ipHash]);
        return (bool) $statement->fetchColumn();
    }

    private function recordFailedAttempt(string $emailHash, string $ipHash): void
    {
        $database = Database::connection();
        $database->beginTransaction();
        try {
            $database->prepare(
                'INSERT IGNORE INTO login_attempts (email_hash, ip_hash, failed_attempts)
                 VALUES (:email_hash, :ip_hash, 0)'
            )->execute(['email_hash' => $emailHash, 'ip_hash' => $ipHash]);
            $select = $database->prepare(
                'SELECT failed_attempts, locked_until IS NOT NULL AND locked_until <= NOW() AS lock_expired
                 FROM login_attempts
                 WHERE email_hash = :email_hash AND ip_hash = :ip_hash LIMIT 1 FOR UPDATE'
            );
            $select->execute(['email_hash' => $emailHash, 'ip_hash' => $ipHash]);
            $attempt = $select->fetch();
            $count = $attempt && (int) $attempt['lock_expired'] === 1
                ? 0
                : (int) ($attempt['failed_attempts'] ?? 0);
            $count++;
            $update = $database->prepare(
                'UPDATE login_attempts SET failed_attempts = :failed_attempts,
                    locked_until = IF(:lock_attempt = 1, DATE_ADD(NOW(), INTERVAL 15 MINUTE), NULL),
                    last_failed_at = NOW()
                 WHERE email_hash = :email_hash AND ip_hash = :ip_hash'
            );
            $update->execute([
                'failed_attempts' => $count,
                'lock_attempt' => $count >= 5 ? 1 : 0,
                'email_hash' => $emailHash,
                'ip_hash' => $ipHash,
            ]);
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    private function findUserById(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, name, email, phone, role, status FROM users WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $userId]);
        $user = $statement->fetch();
        if (!$user) {
            throw new RuntimeException('Không tìm thấy tài khoản vừa tạo.');
        }
        return $user;
    }

    private function takeIntendedUrl(): ?string
    {
        Session::start();
        $url = $_SESSION['_intended_url'] ?? null;
        unset($_SESSION['_intended_url']);
        if (!is_string($url) || !is_internal_redirect_path($url)) {
            return null;
        }
        return $url;
    }

    private function mailerConfig(): array
    {
        $config = require BACKEND_PATH . '/config/config.php';
        return $config['mailer'] ?? ['driver' => 'log'];
    }

    private function exposeDemoLink(array $mailerConfig, string $link): void
    {
        $appConfig = require BACKEND_PATH . '/config/config.php';
        if (($mailerConfig['driver'] ?? 'log') === 'log' && ($appConfig['app']['debug'] ?? false) === true) {
            flash('mail_link', $link);
        }
    }

    private static function dummyPasswordHash(): string
    {
        return '$2y$10$Om2rOB1t6FsKFKd48WLdUekO6m46UCvW67Lts2JY6VQjEFO/.mPQ.';
    }
}
