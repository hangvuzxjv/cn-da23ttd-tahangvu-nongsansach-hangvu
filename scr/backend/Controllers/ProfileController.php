<?php

declare(strict_types=1);

final class ProfileController extends Controller
{
    public function show(): void
    {
        $user = Auth::user();
        $statement = Database::connection()->prepare(
            'SELECT id, name, email, phone, email_verified_at FROM users WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $user['id']]);
        $this->view('client/profile', [
            'title' => 'Hồ sơ của tôi',
            'profile' => $statement->fetch(),
            'errors' => form_errors(),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function update(): void
    {
        $user = Auth::user();
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $input = ['name' => $name, 'email' => $email, 'phone' => $phone];
        $errors = [];
        if ($name === '' || mb_strlen($name, 'UTF-8') > 150) {
            $errors['name'] = 'Họ tên là bắt buộc và không vượt quá 150 ký tự.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $errors['email'] = 'Email không hợp lệ.';
        } else {
            $statement = Database::connection()->prepare(
                'SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1'
            );
            $statement->execute(['email' => $email, 'id' => $user['id']]);
            if ($statement->fetch()) {
                $errors['email'] = 'Email này đã được tài khoản khác sử dụng.';
            }
        }
        if (preg_match('/\A[0-9+()\-\s]{6,30}\z/', $phone) !== 1) {
            $errors['phone'] = 'Số điện thoại cần từ 6 đến 30 ký tự hợp lệ.';
        }
        if ($errors !== []) {
            remember_form_data($input);
            flash('validation_errors', $errors);
            redirect('/profile');
        }

        $database = Database::connection();
        $emailChanged = strcasecmp($email, (string) $user['email']) !== 0;
        $verificationToken = $emailChanged ? bin2hex(random_bytes(32)) : null;
        $database->beginTransaction();
        try {
            $database->prepare(
                'UPDATE users SET name = :name, email = :email, phone = :phone,
                    email_verified_at = IF(:email_changed = 1, NULL, email_verified_at)
                 WHERE id = :id'
            )->execute([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'email_changed' => $emailChanged ? 1 : 0,
                'id' => $user['id'],
            ]);
            if ($emailChanged) {
                $database->prepare('UPDATE email_verifications SET verified_at = NOW() WHERE user_id = :user_id AND verified_at IS NULL')
                    ->execute(['user_id' => $user['id']]);
                $database->prepare(
                    'INSERT INTO email_verifications (user_id, token_hash, expires_at)
                     VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 60 MINUTE))'
                )->execute([
                    'user_id' => $user['id'],
                    'token_hash' => hash('sha256', $verificationToken),
                ]);
            }
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
        if ($emailChanged) {
            $config = require BACKEND_PATH . '/config/config.php';
            $link = absolute_url('/verify-email/' . $verificationToken);
            MailerFactory::make($config['mailer'] ?? ['driver' => 'log'])->send(
                $email,
                'Xác thực email - Nông Sản Sạch',
                "Xác thực địa chỉ email mới tại:\n{$link}\n\nLiên kết có hiệu lực trong 60 phút."
            );
            if (($config['app']['debug'] ?? false) === true && ($config['mailer']['driver'] ?? 'log') === 'log') {
                flash('mail_link', $link);
            }
        }
        flash('success', 'Thông tin hồ sơ đã được cập nhật.');
        redirect('/profile');
    }

    public function changePassword(): void
    {
        $user = Auth::user();
        $current = (string) ($_POST['current_password'] ?? '');
        $password = (string) ($_POST['new_password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');
        $statement = Database::connection()->prepare('SELECT password FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $user['id']]);
        $hash = (string) $statement->fetchColumn();
        $errors = [];
        if (!password_verify($current, $hash)) {
            $errors['current_password'] = 'Mật khẩu hiện tại không đúng.';
        }
        if (strlen($password) > 72 || preg_match('/\A(?=.{8,}\z)(?=.*\p{L})(?=.*\d).+\z/uD', $password) !== 1) {
            $errors['new_password'] = 'Mật khẩu mới cần ít nhất 8 ký tự, có chữ và số, tối đa 72 byte.';
        }
        if ($confirmation !== $password) {
            $errors['password_confirmation'] = 'Mật khẩu nhập lại không khớp.';
        }
        if ($errors !== []) {
            flash('validation_errors', $errors);
            redirect('/profile');
        }

        $database = Database::connection();
        $database->beginTransaction();
        try {
            $database->prepare('UPDATE users SET password = :password WHERE id = :id')
                ->execute(['password' => password_hash($password, PASSWORD_BCRYPT), 'id' => $user['id']]);
            $database->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = :user_id AND used_at IS NULL')
                ->execute(['user_id' => $user['id']]);
            AuditLog::record((int) $user['id'], 'password_changed', 'user', (int) $user['id']);
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
        flash('success', 'Mật khẩu đã được đổi.');
        redirect('/profile');
    }

    public function addresses(): void
    {
        $user = Auth::user();
        $statement = Database::connection()->prepare(
            'SELECT a.*, r.name AS region_name FROM addresses a
             LEFT JOIN regions r ON r.id = a.region_id
             WHERE a.user_id = :user_id ORDER BY a.is_default DESC, a.id DESC'
        );
        $statement->execute(['user_id' => $user['id']]);
        $regions = Database::connection()->query(
            'SELECT id, name FROM regions WHERE level = 2 AND is_active = 1 ORDER BY name'
        )->fetchAll();
        $this->view('client/addresses', [
            'title' => 'Sổ địa chỉ',
            'addresses' => $statement->fetchAll(),
            'regions' => $regions,
            'errors' => form_errors(),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function storeAddress(): void
    {
        $this->persistAddress(null);
    }

    public function updateAddress(string $id): void
    {
        $this->persistAddress((int) $id);
    }

    public function deleteAddress(string $id): void
    {
        $user = Auth::user();
        $database = Database::connection();
        $database->beginTransaction();
        try {
            $find = $database->prepare('SELECT is_default FROM addresses WHERE id = :id AND user_id = :user_id');
            $find->execute(['id' => $id, 'user_id' => $user['id']]);
            $address = $find->fetch();
            if (!$address) {
                $database->rollBack();
                flash('error', 'Không tìm thấy địa chỉ thuộc tài khoản của bạn.');
                redirect('/addresses');
            }
            $database->prepare('DELETE FROM addresses WHERE id = :id AND user_id = :user_id')
                ->execute(['id' => $id, 'user_id' => $user['id']]);
            if ((int) $address['is_default'] === 1) {
                $replacement = $database->prepare('SELECT id FROM addresses WHERE user_id = :user_id ORDER BY id LIMIT 1');
                $replacement->execute(['user_id' => $user['id']]);
                $replacementId = $replacement->fetchColumn();
                if ($replacementId !== false) {
                    $database->prepare('UPDATE addresses SET is_default = 1 WHERE id = :id')
                        ->execute(['id' => $replacementId]);
                }
            }
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
        flash('success', 'Đã xóa địa chỉ.');
        redirect('/addresses');
    }

    private function persistAddress(?int $addressId): void
    {
        $user = Auth::user();
        $input = [
            'recipient_name' => trim((string) ($_POST['recipient_name'] ?? '')),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'region_id' => (int) ($_POST['region_id'] ?? 0),
            'address_line' => trim((string) ($_POST['address_line'] ?? '')),
            'ward' => trim((string) ($_POST['ward'] ?? '')),
            'is_default' => isset($_POST['is_default']) ? 1 : 0,
        ];
        $errors = [];
        if ($input['recipient_name'] === '' || mb_strlen($input['recipient_name'], 'UTF-8') > 150) {
            $errors['recipient_name'] = 'Vui lòng nhập tên người nhận (tối đa 150 ký tự).';
        }
        if (preg_match('/\A[0-9+()\-\s]{6,30}\z/', $input['phone']) !== 1) {
            $errors['phone'] = 'Vui lòng nhập số điện thoại hợp lệ.';
        }
        if ($input['address_line'] === '' || mb_strlen($input['address_line'], 'UTF-8') > 255) {
            $errors['address_line'] = 'Vui lòng nhập địa chỉ (tối đa 255 ký tự).';
        }
        $region = Database::connection()->prepare('SELECT id FROM regions WHERE id = :id AND level = 2');
        $region->execute(['id' => $input['region_id']]);
        if (!$region->fetch()) {
            $errors['region_id'] = 'Vui lòng chọn tỉnh/vùng hợp lệ.';
        }
        if ($addressId !== null) {
            $owned = Database::connection()->prepare('SELECT id FROM addresses WHERE id = :id AND user_id = :user_id');
            $owned->execute(['id' => $addressId, 'user_id' => $user['id']]);
            if (!$owned->fetch()) {
                flash('error', 'Không tìm thấy địa chỉ thuộc tài khoản của bạn.');
                redirect('/addresses');
            }
        }
        if ($errors !== []) {
            remember_form_data($input);
            flash('validation_errors', $errors);
            redirect('/addresses');
        }

        $database = Database::connection();
        $database->beginTransaction();
        try {
            $hasDefault = $database->prepare('SELECT COUNT(*) FROM addresses WHERE user_id = :user_id AND is_default = 1');
            $hasDefault->execute(['user_id' => $user['id']]);
            if ($input['is_default'] === 1) {
                $database->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = :user_id')
                    ->execute(['user_id' => $user['id']]);
            } elseif ($addressId === null && (int) $hasDefault->fetchColumn() === 0) {
                $input['is_default'] = 1;
            }
            if ($addressId === null) {
                $statement = $database->prepare(
                    'INSERT INTO addresses (user_id, recipient_name, phone, region_id, address_line, ward, is_default)
                     VALUES (:user_id, :recipient_name, :phone, :region_id, :address_line, :ward, :is_default)'
                );
            } else {
                $statement = $database->prepare(
                    'UPDATE addresses SET recipient_name = :recipient_name, phone = :phone, region_id = :region_id,
                        address_line = :address_line, ward = :ward, is_default = :is_default
                     WHERE id = :id AND user_id = :user_id'
                );
                $statement->bindValue(':id', $addressId, PDO::PARAM_INT);
            }
            $statement->bindValue(':user_id', (int) $user['id'], PDO::PARAM_INT);
            $statement->bindValue(':recipient_name', $input['recipient_name']);
            $statement->bindValue(':phone', $input['phone']);
            $statement->bindValue(':region_id', $input['region_id'], PDO::PARAM_INT);
            $statement->bindValue(':address_line', $input['address_line']);
            $statement->bindValue(':ward', $input['ward']);
            $statement->bindValue(':is_default', $input['is_default'], PDO::PARAM_INT);
            $statement->execute();
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
        flash('success', $addressId === null ? 'Đã thêm địa chỉ.' : 'Đã cập nhật địa chỉ.');
        redirect('/addresses');
    }
}
