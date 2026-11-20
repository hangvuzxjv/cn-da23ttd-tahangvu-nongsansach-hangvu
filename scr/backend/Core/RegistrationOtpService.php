<?php

declare(strict_types=1);

final class RegistrationOtpService
{
    public const MAX_ATTEMPTS = 5;
    private const RESEND_DELAY_SECONDS = 60;

    public function __construct(private ?PDO $database = null)
    {
        $this->database ??= Database::connection();
    }

    /** @return array{status: string, email?: string, code?: string, retry_after?: int} */
    public function issue(int $userId): array
    {
        return $this->issueForPurpose($userId, 'registration');
    }

    /** @return array{status: string, email?: string, code?: string, retry_after?: int} */
    public function issuePasswordReset(int $userId): array
    {
        return $this->issueForPurpose($userId, 'password_reset');
    }

    /** @return array{status: string, email?: string, code?: string, retry_after?: int} */
    private function issueForPurpose(int $userId, string $purpose): array
    {
        $table = $this->otpTable($purpose);
        $ownsTransaction = !$this->database->inTransaction();
        if ($ownsTransaction) {
            $this->database->beginTransaction();
        }

        try {
            $userQuery = $this->database->prepare(
                'SELECT email, email_verified_at FROM users WHERE id = :id LIMIT 1 FOR UPDATE'
            );
            $userQuery->execute(['id' => $userId]);
            $user = $userQuery->fetch();
            if (!$user) {
                if ($ownsTransaction) {
                    $this->database->commit();
                }
                return ['status' => 'missing'];
            }
            if ($purpose === 'registration' && $user['email_verified_at'] !== null) {
                if ($ownsTransaction) {
                    $this->database->commit();
                }
                return ['status' => 'verified'];
            }

            $otpQuery = $this->database->prepare(
                'SELECT GREATEST(0, :delay - TIMESTAMPDIFF(SECOND, last_sent_at, CURRENT_TIMESTAMP)) AS retry_after
                 FROM ' . $table . ' WHERE user_id = :user_id LIMIT 1 FOR UPDATE'
            );
            $otpQuery->execute([
                'delay' => self::RESEND_DELAY_SECONDS,
                'user_id' => $userId,
            ]);
            $otp = $otpQuery->fetch();
            $retryAfter = (int) ($otp['retry_after'] ?? 0);
            if ($retryAfter > 0) {
                if ($ownsTransaction) {
                    $this->database->commit();
                }
                return ['status' => 'cooldown', 'retry_after' => $retryAfter];
            }

            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $hash = password_hash($code, PASSWORD_DEFAULT);
            if ($otp) {
                $save = $this->database->prepare(
                    'UPDATE ' . $table . '
                     SET otp_hash = :otp_hash, expires_at = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 10 MINUTE),
                         attempts = 0, last_sent_at = CURRENT_TIMESTAMP
                     WHERE user_id = :user_id'
                );
            } else {
                $save = $this->database->prepare(
                    'INSERT INTO ' . $table . '
                        (user_id, otp_hash, expires_at, attempts, last_sent_at)
                     VALUES (:user_id, :otp_hash, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 10 MINUTE), 0, CURRENT_TIMESTAMP)'
                );
            }
            $save->execute(['user_id' => $userId, 'otp_hash' => $hash]);

            if ($ownsTransaction) {
                $this->database->commit();
            }
            return [
                'status' => 'issued',
                'email' => (string) $user['email'],
                'code' => $code,
                'retry_after' => self::RESEND_DELAY_SECONDS,
            ];
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function resendAfter(int $userId): int
    {
        return $this->resendAfterPurpose($userId, 'registration');
    }

    private function resendAfterPurpose(int $userId, string $purpose): int
    {
        $table = $this->otpTable($purpose);
        $query = $this->database->prepare(
            'SELECT GREATEST(0, :delay - TIMESTAMPDIFF(SECOND, last_sent_at, CURRENT_TIMESTAMP))
             FROM ' . $table . ' WHERE user_id = :user_id LIMIT 1'
        );
        $query->execute([
            'delay' => self::RESEND_DELAY_SECONDS,
            'user_id' => $userId,
        ]);
        return max(0, (int) $query->fetchColumn());
    }

    public function verify(int $userId, string $code): string
    {
        return $this->verifyForPurpose($userId, $code, 'registration');
    }

    public function verifyPasswordReset(int $userId, string $code): string
    {
        return $this->verifyForPurpose($userId, $code, 'password_reset');
    }

    private function verifyForPurpose(int $userId, string $code, string $purpose): string
    {
        $table = $this->otpTable($purpose);
        $ownsTransaction = !$this->database->inTransaction();
        if ($ownsTransaction) {
            $this->database->beginTransaction();
        }

        try {
            $userQuery = $this->database->prepare(
                'SELECT email_verified_at FROM users WHERE id = :id LIMIT 1 FOR UPDATE'
            );
            $userQuery->execute(['id' => $userId]);
            $user = $userQuery->fetch();
            if (!$user) {
                if ($ownsTransaction) {
                    $this->database->commit();
                }
                return 'missing';
            }
            if ($purpose === 'registration' && $user['email_verified_at'] !== null) {
                if ($ownsTransaction) {
                    $this->database->commit();
                }
                return 'verified';
            }

            $otpQuery = $this->database->prepare(
                'SELECT otp_hash, attempts, expires_at <= CURRENT_TIMESTAMP AS is_expired
                 FROM ' . $table . ' WHERE user_id = :user_id LIMIT 1 FOR UPDATE'
            );
            $otpQuery->execute(['user_id' => $userId]);
            $otp = $otpQuery->fetch();
            if (!$otp) {
                if ($ownsTransaction) {
                    $this->database->commit();
                }
                return 'missing_code';
            }
            if ((int) $otp['attempts'] >= self::MAX_ATTEMPTS) {
                if ($ownsTransaction) {
                    $this->database->commit();
                }
                return 'locked';
            }
            if ((int) $otp['is_expired'] === 1) {
                if ($ownsTransaction) {
                    $this->database->commit();
                }
                return 'expired';
            }

            if (preg_match('/\A[0-9]{6}\z/', $code) !== 1 || !password_verify($code, (string) $otp['otp_hash'])) {
                $this->database->prepare(
                    'UPDATE ' . $table . ' SET attempts = attempts + 1 WHERE user_id = :user_id'
                )->execute(['user_id' => $userId]);
                $status = (int) $otp['attempts'] + 1 >= self::MAX_ATTEMPTS ? 'locked' : 'invalid';
                if ($ownsTransaction) {
                    $this->database->commit();
                }
                return $status;
            }

            if ($purpose === 'registration') {
                $this->database->prepare(
                    'UPDATE users SET email_verified_at = CURRENT_TIMESTAMP WHERE id = :id AND email_verified_at IS NULL'
                )->execute(['id' => $userId]);
            }
            $this->database->prepare('DELETE FROM ' . $table . ' WHERE user_id = :user_id')
                ->execute(['user_id' => $userId]);
            if ($ownsTransaction) {
                $this->database->commit();
            }
            return 'verified';
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    private function otpTable(string $purpose): string
    {
        return match ($purpose) {
            'registration' => 'registration_email_otps',
            'password_reset' => 'password_reset_otps',
            default => throw new InvalidArgumentException('Mục đích OTP không hợp lệ.'),
        };
    }
}
