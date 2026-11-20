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
        $referralCode = $_GET['ref'] ?? '';
        $this->view('client/register', [
            'title' => 'Tạo tài khoản',
            'errors' => form_errors(),
            'referralCode' => is_string($referralCode) ? strtoupper(trim($referralCode)) : '',
        ]);
    }

    public function showRegistrationOtp(): void
    {
        $userId = $this->pendingRegistrationUserId();
        if ($userId === null) {
            redirect('/login');
        }

        $query = Database::connection()->prepare(
            'SELECT email, email_verified_at FROM users WHERE id = :id LIMIT 1'
        );
        $query->execute(['id' => $userId]);
        $user = $query->fetch();
        if (!$user) {
            $this->clearPendingRegistration();
            redirect('/register');
        }
        if ($user['email_verified_at'] !== null) {
            $this->clearPendingRegistration();
            flash('success', 'Email của bạn đã được xác minh. Vui lòng đăng nhập.');
            redirect('/login');
        }

        $this->view('client/verify-email-otp', [
            'title' => 'Xác minh email',
            'email' => (string) $user['email'],
            'errors' => form_errors(),
            'success' => flash('success'),
            'error' => flash('error'),
            'resendAfter' => (new RegistrationOtpService())->resendAfter($userId),
            'loadVerificationOtp' => true,
        ]);
    }

    public function register(): void
    {
        $input = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'email' => strtolower(trim((string) ($_POST['email'] ?? ''))),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'referral_code' => strtoupper(trim((string) ($_POST['referral_code'] ?? ''))),
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
                'INSERT INTO users (name, email, phone, password, role, status, email_verified_at)
                 VALUES (:name, :email, :phone, :password, \'customer\', \'active\', NULL)'
            );
            $statement->execute([
                'name' => $input['name'],
                'email' => $input['email'],
                'phone' => $input['phone'],
                'password' => password_hash($password, PASSWORD_BCRYPT),
            ]);
            $userId = (int) $database->lastInsertId();
            if ($input['referral_code'] !== '') {
                (new CustomerMissionService($database))->attachReferrer($userId, $input['referral_code']);
            }
            $otp = (new RegistrationOtpService($database))->issue($userId);
            if ($otp['status'] !== 'issued') {
                throw new RuntimeException('Không thể tạo mã xác minh cho tài khoản mới.');
            }
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            if ($exception instanceof InvalidArgumentException) {
                $this->failValidation('/register', $input, ['referral_code' => $exception->getMessage()]);
            }
            if ($exception instanceof PDOException && $exception->getCode() === '23000') {
                $this->failValidation('/register', $input, ['email' => 'Email này đã được đăng ký.']);
            }
            throw $exception;
        }

        AuditLog::record($userId, 'register', 'user', $userId);
        Session::start();
        $_SESSION['pending_registration_user_id'] = $userId;
        $verificationMail = $this->sendRegistrationOtp(
            $userId,
            $input['email'],
            $input['name'],
            (string) $otp['code']
        );
        if (!$verificationMail['accepted']) {
            flash('error', 'Chưa thể gửi mã xác minh. Vui lòng thử gửi lại sau.');
        } elseif (!$verificationMail['delivered']) {
            flash('success', 'Tài khoản đã tạo. Mã xác minh đang được chuyển đến hộp thư Gmail của bạn.');
        } else {
            flash('success', 'Tài khoản đã tạo. Mã xác minh đã được gửi đến hộp thư Gmail của bạn.');
        }
        redirect('/verify-email-otp');
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
            'SELECT id, name, email, phone, password, role, status, email_verified_at
             FROM users WHERE email = :email LIMIT 1'
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
        if ($user['email_verified_at'] === null) {
            Session::start();
            $_SESSION['pending_registration_user_id'] = (int) $user['id'];
            flash('error', 'Bạn cần xác minh email trước khi đăng nhập. Nhập mã đã gửi vào hộp thư của bạn.');
            redirect('/verify-email-otp');
        }
        $this->clearPendingRegistration();
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

        Session::start();
        $_SESSION['password_reset_email'] = $email;
        $_SESSION['password_reset_otp_sent_at'] = time();
        $_SESSION['password_reset_otp_attempts'] = 0;

        $allowed = (new MailRateLimiter())->allow(
            'password-reset',
            $email,
            (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
        );
        $user = false;
        if ($allowed) {
            $statement = Database::connection()->prepare(
                'SELECT id, name, email FROM users WHERE email = :email LIMIT 1'
            );
            $statement->execute(['email' => $email]);
            $user = $statement->fetch();
        }
        if ($user) {
            $otp = (new RegistrationOtpService())->issuePasswordReset((int) $user['id']);
            if ($otp['status'] === 'issued') {
                $this->sendPasswordResetOtp(
                    (int) $user['id'],
                    (string) $user['email'],
                    (string) $user['name'],
                    (string) $otp['code']
                );
            }
        }

        flash('success', 'Nếu địa chỉ thuộc tài khoản, mã xác minh sẽ được gửi đến hộp thư.');
        redirect('/forgot-password/code');
    }

    public function showPasswordResetOtp(): void
    {
        $email = $this->pendingPasswordResetEmail();
        if ($email === null) {
            redirect('/forgot-password');
        }

        $this->view('client/forgot-password-code', [
            'title' => 'Xác minh email',
            'email' => $email,
            'errors' => form_errors(),
            'success' => flash('success'),
            'error' => flash('error'),
            'resendAfter' => $this->passwordResetResendAfter(),
            'loadVerificationOtp' => true,
        ]);
    }

    public function verifyPasswordResetOtp(): void
    {
        $email = $this->pendingPasswordResetEmail();
        if ($email === null) {
            redirect('/forgot-password');
        }

        Session::start();
        $attempts = (int) ($_SESSION['password_reset_otp_attempts'] ?? 0);
        $code = trim((string) ($_POST['code'] ?? ''));
        if ($attempts >= RegistrationOtpService::MAX_ATTEMPTS) {
            flash('error', 'Mã xác minh không đúng hoặc đã hết hạn. Hãy kiểm tra thư và thử lại.');
            redirect('/forgot-password/code');
        }

        $query = Database::connection()->prepare(
            'SELECT id FROM users WHERE email = :email LIMIT 1'
        );
        $query->execute(['email' => $email]);
        $userId = $query->fetchColumn();
        $status = 'invalid';
        if ($userId !== false) {
            $status = (new RegistrationOtpService())->verifyPasswordReset((int) $userId, $code);
        } else {
            password_verify($code, self::dummyPasswordHash());
        }

        if ($status === 'verified' && $userId !== false) {
            $token = (new AccountTokenService())->issuePasswordReset((int) $userId, 10);
            $this->clearPasswordResetSession();
            redirect('/reset-password/' . rawurlencode($token));
        }

        if ($status === 'invalid' || $userId === false) {
            $_SESSION['password_reset_otp_attempts'] = min(
                RegistrationOtpService::MAX_ATTEMPTS,
                $attempts + 1
            );
        } elseif ($status === 'locked') {
            $_SESSION['password_reset_otp_attempts'] = RegistrationOtpService::MAX_ATTEMPTS;
        }
        flash('error', 'Mã xác minh không đúng hoặc đã hết hạn. Hãy kiểm tra thư và thử lại.');
        redirect('/forgot-password/code');
    }

    public function resendPasswordResetOtp(): void
    {
        $email = $this->pendingPasswordResetEmail();
        if ($email === null) {
            redirect('/forgot-password');
        }
        if ($this->passwordResetResendAfter() > 0) {
            flash('error', 'Vui lòng đợi hết thời gian đếm ngược trước khi gửi lại mã.');
            redirect('/forgot-password/code');
        }

        $allowed = (new MailRateLimiter())->allow(
            'password-reset',
            $email,
            (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
        );
        if ($allowed) {
            $query = Database::connection()->prepare(
                'SELECT id, name, email FROM users WHERE email = :email LIMIT 1'
            );
            $query->execute(['email' => $email]);
            $user = $query->fetch();
            if ($user) {
                $otp = (new RegistrationOtpService())->issuePasswordReset((int) $user['id']);
                if ($otp['status'] === 'issued') {
                    $this->sendPasswordResetOtp(
                        (int) $user['id'],
                        (string) $user['email'],
                        (string) $user['name'],
                        (string) $otp['code']
                    );
                }
            }
        }

        Session::start();
        $_SESSION['password_reset_otp_sent_at'] = time();
        $_SESSION['password_reset_otp_attempts'] = 0;
        flash('success', 'Nếu địa chỉ thuộc tài khoản, mã xác minh sẽ được gửi đến hộp thư.');
        redirect('/forgot-password/code');
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

    public function verifyRegistrationOtp(): void
    {
        $userId = $this->pendingRegistrationUserId();
        if ($userId === null) {
            redirect('/login');
        }

        $code = trim((string) ($_POST['code'] ?? ''));
        $status = (new RegistrationOtpService())->verify($userId, $code);
        if ($status === 'verified') {
            $this->clearPendingRegistration();
            $user = $this->findUserById($userId);
            if ($user['status'] !== 'active') {
                flash('error', 'Tài khoản hiện không thể đăng nhập.');
                redirect('/login');
            }
            if ($user['role'] === 'customer') {
                (new VoucherWalletService())->grantWelcomeSafely($userId);
            }
            Auth::login($user);
            (new CartService())->mergeSessionCart($userId);
            AuditLog::record($userId, 'email_verified_otp', 'user', $userId);
            flash('success', 'Email đã được xác minh. Tài khoản của bạn đã sẵn sàng.');
            redirect($this->takeIntendedUrl() ?? ($user['role'] === 'customer' ? '/' : '/admin'));
        }

        $message = match ($status) {
            'invalid' => 'Mã xác minh không đúng. Vui lòng kiểm tra lại.',
            'locked' => 'Bạn đã nhập sai 5 lần. Hãy gửi mã mới để tiếp tục.',
            'expired' => 'Mã xác minh đã hết hạn. Hãy gửi mã mới.',
            'missing_code' => 'Không tìm thấy mã xác minh. Hãy gửi mã mới.',
            default => 'Không thể xác minh email. Vui lòng thử lại.',
        };
        flash('error', $message);
        redirect('/verify-email-otp');
    }

    public function resendRegistrationOtp(): void
    {
        $userId = $this->pendingRegistrationUserId();
        if ($userId === null) {
            redirect('/login');
        }

        $otp = (new RegistrationOtpService())->issue($userId);
        if ($otp['status'] === 'verified') {
            $this->clearPendingRegistration();
            flash('success', 'Email của bạn đã được xác minh. Vui lòng đăng nhập.');
            redirect('/login');
        }
        if ($otp['status'] === 'missing') {
            $this->clearPendingRegistration();
            redirect('/register');
        }
        if ($otp['status'] === 'cooldown') {
            flash('error', 'Vui lòng đợi ' . (int) $otp['retry_after'] . ' giây trước khi gửi lại mã.');
            redirect('/verify-email-otp');
        }

        $query = Database::connection()->prepare('SELECT name FROM users WHERE id = :id LIMIT 1');
        $query->execute(['id' => $userId]);
        $name = (string) ($query->fetchColumn() ?: '');
        $result = $this->sendRegistrationOtp(
            $userId,
            (string) $otp['email'],
            $name,
            (string) $otp['code']
        );
        flash(
            $result['accepted'] ? 'success' : 'error',
            !$result['accepted']
                ? 'Chưa thể gửi mã xác minh. Vui lòng thử lại sau.'
                : ($result['delivered']
                ? 'Mã xác minh mới đã được gửi đến hộp thư của bạn.'
                : 'Mã xác minh mới đang được chuyển đến hộp thư của bạn.')
        );
        redirect('/verify-email-otp');
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
            $errors['email'] = 'Vui lòng nhập địa chỉ Gmail hợp lệ.';
        } elseif (!str_ends_with($input['email'], '@gmail.com')) {
            $errors['email'] = 'Đăng ký chỉ nhận địa chỉ Gmail kết thúc bằng @gmail.com.';
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
        if (strlen($input['referral_code']) > 16) {
            $errors['referral_code'] = 'Mã giới thiệu không đúng định dạng.';
        } elseif ($input['referral_code'] !== '') {
            try {
                (new CustomerMissionService())->validateReferralCode($input['referral_code'], $input['phone']);
            } catch (InvalidArgumentException $exception) {
                $errors['referral_code'] = $exception->getMessage();
            }
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

    private function pendingRegistrationUserId(): ?int
    {
        Session::start();
        $userId = $_SESSION['pending_registration_user_id'] ?? null;
        return is_numeric($userId) && (int) $userId > 0 ? (int) $userId : null;
    }

    private function clearPendingRegistration(): void
    {
        Session::start();
        unset($_SESSION['pending_registration_user_id']);
    }

    private function pendingPasswordResetEmail(): ?string
    {
        Session::start();
        $email = $_SESSION['password_reset_email'] ?? null;
        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function passwordResetResendAfter(): int
    {
        Session::start();
        $sentAt = (int) ($_SESSION['password_reset_otp_sent_at'] ?? 0);
        return $sentAt > 0 ? max(0, 60 - (time() - $sentAt)) : 0;
    }

    private function clearPasswordResetSession(): void
    {
        Session::start();
        unset(
            $_SESSION['password_reset_email'],
            $_SESSION['password_reset_otp_sent_at'],
            $_SESSION['password_reset_otp_attempts']
        );
    }

    private function sendRegistrationOtp(int $userId, string $email, string $name, string $code): array
    {
        return (new MailService())->sendTemplate(
            $email,
            'Mã xác minh đăng ký - Nông Sản Sạch',
            'Xác minh tài khoản',
            [
                'Xin chào ' . $name . '.',
                'Mã xác minh của bạn là: ' . $code,
                'Mã có hiệu lực trong 10 phút và bạn có tối đa 5 lần nhập. Không chia sẻ mã này với bất kỳ ai.',
            ],
            null,
            null,
            ['type' => 'registration_email_otp', 'user_id' => $userId]
        );
    }

    private function sendPasswordResetOtp(int $userId, string $email, string $name, string $code): array
    {
        return (new MailService())->sendTemplate(
            $email,
            'Mã xác minh đặt lại mật khẩu - Nông Sản Sạch',
            'Đặt lại mật khẩu',
            [
                'Xin chào ' . $name . '.',
                'Mã xác minh đặt lại mật khẩu của bạn là: ' . $code,
                'Mã có hiệu lực trong 10 phút và bạn có tối đa 5 lần nhập. Không chia sẻ mã này với bất kỳ ai.',
            ],
            null,
            null,
            ['type' => 'password_reset_otp', 'user_id' => $userId]
        );
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
