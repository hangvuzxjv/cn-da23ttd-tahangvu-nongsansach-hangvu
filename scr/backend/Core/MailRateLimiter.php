<?php

declare(strict_types=1);

final class MailRateLimiter
{
    public function __construct(private ?PDO $database = null)
    {
        $this->database ??= Database::connection();
    }

    public function allow(string $scope, string $email, string $ip, int $perEmail = 3, int $perIp = 10): bool
    {
        $checks = [
            [hash('sha256', $scope . ':email:' . mb_strtolower(trim($email), 'UTF-8')), $perEmail],
            [hash('sha256', $scope . ':ip:' . trim($ip)), $perIp],
        ];
        $allowed = true;
        foreach ($checks as [$keyHash, $limit]) {
            $statement = $this->database->prepare(
                'INSERT INTO mail_rate_limits (key_hash, window_started_at, request_count)
                 VALUES (:key_hash, UTC_TIMESTAMP(), 1)
                 ON DUPLICATE KEY UPDATE
                    request_count = IF(window_started_at < UTC_TIMESTAMP() - INTERVAL 1 HOUR, 1, request_count + 1),
                    window_started_at = IF(window_started_at < UTC_TIMESTAMP() - INTERVAL 1 HOUR,
                                            UTC_TIMESTAMP(), window_started_at)'
            );
            $statement->execute(['key_hash' => $keyHash]);
            $count = $this->database->prepare('SELECT request_count FROM mail_rate_limits WHERE key_hash = :key_hash');
            $count->execute(['key_hash' => $keyHash]);
            if ((int) $count->fetchColumn() > $limit) {
                $allowed = false;
            }
        }
        return $allowed;
    }
}
