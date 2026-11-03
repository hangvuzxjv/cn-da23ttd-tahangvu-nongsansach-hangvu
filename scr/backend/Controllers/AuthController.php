<?php

declare(strict_types=1);

final class AuthController extends Controller
{
    private const GENERIC_LOGIN_ERROR = 'Email hoặc mật khẩu không đúng, hoặc tài khoản tạm thời bị khóa.';

    public function showLogin(): void
    {
        $returnTo = $_GET['return_to'] ?? null;
        if (is_string($returnTo) && is_internal_redirect_path($returnTo)
            && !str_starts_with($returnTo, '/login')) {
            Session::start();
            if (empty($_SESSION['_intended_url'])) {
                $_SESSION['_intended_url'] = $returnTo;
            }
        }
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
        if (!(new MailRateLimiter())->allow(
            'registration',
            $input['email'],
            (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
            2,
            8
        )) {
            $this->failValidation('/register', $input, ['email' => 'Bạn đã gửi quá nhiều yêu cầu. Vui lòng thử lại sau.']);
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
            $verificationToken = (new AccountTokenService($database))->issueVerification($userId);
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
        $verificationMail = null;
        try {
            $verifyUrl = absolute_url('/verify-email/' . $verificationToken);
            $verificationMail = (new MailService())->sendTemplate(
                $input['email'],
                'Xác thực email - Nông Sản Sạch',
                'Xác thực địa chỉ email',
                ['Xin chào ' . $input['name'] . '.', 'Liên kết có hiệu lực trong 60 phút và chỉ dùng một lần.'],
                'Xác thực email',
                $verifyUrl,
                ['type' => 'email_verification', 'user_id' => $userId]
            );
            $this->exposeDemoLink($verifyUrl);
        } catch (Throwable) {
            error_log('Registration verification email could not be queued.');
        }
        Auth::login($this->findUserById($userId));
        (new CartService())->mergeSessionCart($userId);
        flash('success', 'Tạo tài khoản thành công. Bạn có thể mua hàng ngay; email xác thực là tùy chọn.');
        if ($verificationMail === null || !$verificationMail['accepted']) {
            flash('error', 'Tài khoản đã tạo; email xác minh chưa thể được lưu để gửi. Bạn có thể yêu cầu gửi lại trong hồ sơ.');
        } elseif (!$verificationMail['delivered']) {
            flash('error', 'Tài khoản đã tạo; email xác minh đang chờ gửi lại.');
        }
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

        $allowed = (new MailRateLimiter())->allow(
            'password-reset',
            $email,
            (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
        );
        $statement = Database::connection()->prepare('SELECT id, name FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);
        $user = $allowed ? $statement->fetch() : false;
        $mailLink = null;
        if ($user) {
            $token = (new AccountTokenService())->issuePasswordReset((int) $user['id']);
            try {
                $mailLink = absolute_url('/reset-password/' . $token);
                (new MailService())->sendTemplate(
                    $email,
                    'Đặt lại mật khẩu - Nông Sản Sạch',
                    'Yêu cầu đặt lại mật khẩu',
                    ['Xin chào ' . $user['name'] . '.', 'Liên kết chỉ dùng một lần và hết hạn sau 30 phút.'],
                    'Đặt lại mật khẩu',
                    $mailLink,
                    ['type' => 'password_reset', 'user_id' => (int) $user['id']]
                );
            } catch (Throwable) {
                error_log('Password reset email could not be queued.');
            }
        }

        flash('success', 'Nếu email thuộc tài khoản hợp lệ, hướng dẫn đặt lại mật khẩu sẽ được gửi.');
        if ($mailLink !== null) {
            try {
                $this->exposeDemoLink($mailLink);
            } catch (Throwable) {
                error_log('Password reset debug link unavailable.');
            }
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
        if ($errors === [] && (new AccountTokenService())->passwordMatchesCurrent($token, $password)) {
            $errors['password'] = 'Mật khẩu mới phải khác mật khẩu hiện tại';
        }
        if ($errors !== []) {
            $this->failValidation('/reset-password/' . rawurlencode($token), [], $errors);
        }

        if (!(new AccountTokenService())->resetPassword($token, $password)) {
            http_response_code(400);
            $this->view('client/reset-password', [
                'title' => 'Đặt lại mật khẩu',
                'token' => '',
                'errors' => ['token' => 'Liên kết không hợp lệ, đã dùng hoặc hết hạn.'],
            ]);
            return;
        }

        flash('success', 'Mật khẩu đã được cập nhật. Vui lòng đăng nhập.');
        redirect('/login');
    }

    public function verifyEmail(string $token): void
    {
        if (!(new AccountTokenService())->verifyEmail($token)) {
            http_response_code(400);
            flash('error', 'Liên kết xác thực không hợp lệ hoặc đã hết hạn.');
            redirect(Auth::check() ? '/profile' : '/login');
        }

        flash('success', 'Email đã được xác thực.');
        redirect(Auth::check() ? '/profile' : '/login');
    }

    public function resendVerification(): Response
    {
        $user = Auth::user();
        try {
            $database = Database::connection();
            $verified = $database->prepare('SELECT email_verified_at FROM users WHERE id = :id');
            $verified->execute(['id' => $user['id']]);
            if ($verified->fetchColumn() !== null) {
                return Response::json(false, 'Email của bạn đã được xác thực.', [], 422);
            }
            if (!(new MailRateLimiter($database))->allow(
                'verification-resend',
                (string) $user['email'],
                (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
                3,
                10
            )) {
                return Response::json(false, 'Bạn đã yêu cầu gửi quá nhiều lần. Vui lòng thử lại sau.', [], 429);
            }
            $token = (new AccountTokenService($database))->issueVerification((int) $user['id']);
            $link = absolute_url('/verify-email/' . $token);
            $result = (new MailService($database))->sendTemplate(
                (string) $user['email'],
                'Xác thực email - Nông Sản Sạch',
                'Xác thực địa chỉ email',
                ['Liên kết có hiệu lực trong 60 phút và chỉ dùng một lần.'],
                'Xác thực email',
                $link,
                ['type' => 'email_verification', 'user_id' => (int) $user['id']]
            );
            if (!$result['accepted']) {
                return Response::json(false, 'Chưa thể lưu thư xác thực. Vui lòng thử lại sau.', [], 503);
            }
            $message = $result['delivered']
                ? 'Email xác thực đã được gửi.'
                : 'Email xác thực đã được đưa vào hàng chờ để gửi lại.';
            return Response::json(true, $message, ['queued' => $result['queued']]);
        } catch (Throwable) {
            error_log('Verification resend request failed.');
            return Response::json(false, 'Chưa thể gửi email xác thực. Vui lòng thử lại sau.', [], 503);
        }
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

    private function exposeDemoLink(string $link): void
    {
        $appConfig = require BACKEND_PATH . '/config/config.php';
        if ((new MailService())->selectedDriver() === 'outbox' && ($appConfig['app']['debug'] ?? false) === true) {
            flash('mail_link', $link);
        }
    }

    private static function dummyPasswordHash(): string
    {
        return '$2y$10$Om2rOB1t6FsKFKd48WLdUekO6m46UCvW67Lts2JY6VQjEFO/.mPQ.';
    }
}
