<?php

declare(strict_types=1);

final class AccountTokenService
{
    public function __construct(private ?PDO $database = null)
    {
        $this->database ??= Database::connection();
    }

    public function issueVerification(int $userId, int $validMinutes = 60): string
    {
        $token = bin2hex(random_bytes(32));
        $ownsTransaction = !$this->database->inTransaction();
        if ($ownsTransaction) {
            $this->database->beginTransaction();
        }
        try {
            $this->database->prepare(
                'UPDATE email_verifications SET verified_at = NOW()
                 WHERE user_id = :user_id AND verified_at IS NULL'
            )->execute(['user_id' => $userId]);
            $this->database->prepare(
                'INSERT INTO email_verifications (user_id, token_hash, expires_at)
                 VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL ' . (int) $validMinutes . ' MINUTE))'
            )->execute(['user_id' => $userId, 'token_hash' => hash('sha256', $token)]);
            if ($ownsTransaction) {
                $this->database->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
        return $token;
    }

    public function verifyEmail(string $token): bool
    {
        $this->database->beginTransaction();
        try {
            $query = $this->database->prepare(
                'SELECT id, user_id FROM email_verifications
                 WHERE token_hash = :token_hash AND verified_at IS NULL AND expires_at > NOW()
                 LIMIT 1 FOR UPDATE'
            );
            $query->execute(['token_hash' => hash('sha256', $token)]);
            $verification = $query->fetch();
            if (!$verification) {
                $this->database->rollBack();
                return false;
            }
            $update = $this->database->prepare(
                'UPDATE email_verifications SET verified_at = NOW()
                 WHERE id = :id AND verified_at IS NULL AND expires_at > NOW()'
            );
            $update->execute(['id' => $verification['id']]);
            if ($update->rowCount() !== 1) {
                $this->database->rollBack();
                return false;
            }
            $this->database->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = :user_id')
                ->execute(['user_id' => $verification['user_id']]);
            $this->database->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function issuePasswordReset(int $userId, int $validMinutes = 30): string
    {
        $token = bin2hex(random_bytes(32));
        $this->database->beginTransaction();
        try {
            $this->database->prepare(
                'UPDATE password_resets SET used_at = NOW() WHERE user_id = :user_id AND used_at IS NULL'
            )->execute(['user_id' => $userId]);
            $this->database->prepare(
                'INSERT INTO password_resets (user_id, token_hash, expires_at)
                 VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL ' . (int) $validMinutes . ' MINUTE))'
            )->execute(['user_id' => $userId, 'token_hash' => hash('sha256', $token)]);
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
        return $token;
    }

    public function resetPassword(string $token, string $password): bool
    {
        $this->database->beginTransaction();
        try {
            $query = $this->database->prepare(
                'SELECT id, user_id FROM password_resets
                 WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at > NOW()
                 LIMIT 1 FOR UPDATE'
            );
            $query->execute(['token_hash' => hash('sha256', $token)]);
            $reset = $query->fetch();
            if (!$reset) {
                $this->database->rollBack();
                return false;
            }
            $this->database->prepare('UPDATE users SET password = :password WHERE id = :user_id')
                ->execute([
                    'password' => password_hash($password, PASSWORD_BCRYPT),
                    'user_id' => $reset['user_id'],
                ]);
            $this->database->prepare(
                'UPDATE password_resets SET used_at = NOW() WHERE user_id = :user_id AND used_at IS NULL'
            )->execute(['user_id' => $reset['user_id']]);
            AuditLog::record((int) $reset['user_id'], 'password_changed', 'user', (int) $reset['user_id'], 'via=password_reset', $this->database);
            AuditLog::record((int) $reset['user_id'], 'password_reset', 'user', (int) $reset['user_id'], null, $this->database);
            $this->database->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }

    }

    public function passwordMatchesCurrent(string $token, string $password): bool
    {
        $query = $this->database->prepare(
            'SELECT u.password
             FROM password_resets r
             JOIN users u ON u.id = r.user_id
             WHERE r.token_hash = :token_hash AND r.used_at IS NULL AND r.expires_at > NOW()
             LIMIT 1'
        );
        $query->execute(['token_hash' => hash('sha256', $token)]);
        $hash = $query->fetchColumn();
        return is_string($hash) && password_verify($password, $hash);
    }
}
