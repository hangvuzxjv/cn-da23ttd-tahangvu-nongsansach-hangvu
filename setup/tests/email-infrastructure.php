<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

function emailTestAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$database = inventoryTestDatabase();
$email = 'mail-test-' . bin2hex(random_bytes(8)) . '@example.test';
$ip = '192.0.2.' . random_int(1, 200);
$database->prepare(
    "INSERT INTO users (name, email, phone, password, role, status)
     VALUES ('Mail Test', :email, '0900000000', :password, 'customer', 'active')"
)->execute(['email' => $email, 'password' => password_hash('OldPassword123', PASSWORD_BCRYPT)]);
$userId = (int) $database->lastInsertId();
$previousDriverQuery = $database->prepare("SELECT setting_value FROM settings WHERE setting_key = 'mailer.driver'");
$previousDriverQuery->execute();
$previousDriver = $previousDriverQuery->fetchColumn();
$hasPreviousDriver = $previousDriver !== false;
$messageIds = [];
$rateHashes = [];

try {
    $tokens = new AccountTokenService($database);
    $database->beginTransaction();
    $tokens->issueVerification($userId);
    emailTestAssert($database->inTransaction(), 'Verification-token issue ended its caller transaction.');
    $database->rollBack();
    $verificationToken = $tokens->issueVerification($userId);
    $storedVerification = $database->prepare(
        'SELECT token_hash FROM email_verifications WHERE user_id = :user_id ORDER BY id DESC LIMIT 1'
    );
    $storedVerification->execute(['user_id' => $userId]);
    emailTestAssert(
        hash('sha256', $verificationToken) === $storedVerification->fetchColumn()
            && hash('sha256', $verificationToken) !== $verificationToken,
        'Verification token was not stored as a hash.'
    );
    emailTestAssert($tokens->verifyEmail($verificationToken), 'A valid verification token was rejected.');
    emailTestAssert(!$tokens->verifyEmail($verificationToken), 'A verification token was accepted more than once.');
    $verified = $database->prepare('SELECT email_verified_at FROM users WHERE id = :id');
    $verified->execute(['id' => $userId]);
    emailTestAssert($verified->fetchColumn() !== null, 'Email verification did not update the account.');

    $expiredVerification = $tokens->issueVerification($userId, -1);
    emailTestAssert(!$tokens->verifyEmail($expiredVerification), 'An expired verification token was accepted.');

    $resetToken = $tokens->issuePasswordReset($userId);
    $storedReset = $database->prepare(
        'SELECT token_hash FROM password_resets WHERE user_id = :user_id ORDER BY id DESC LIMIT 1'
    );
    $storedReset->execute(['user_id' => $userId]);
    emailTestAssert(hash('sha256', $resetToken) === $storedReset->fetchColumn(), 'Reset token was not stored as a hash.');
    emailTestAssert($tokens->resetPassword($resetToken, 'NewPassword456'), 'A valid reset token was rejected.');
    $passwordQuery = $database->prepare('SELECT password FROM users WHERE id = :id');
    $passwordQuery->execute(['id' => $userId]);
    emailTestAssert(password_verify('NewPassword456', (string) $passwordQuery->fetchColumn()), 'Password reset did not update the password.');
    emailTestAssert(!$tokens->resetPassword($resetToken, 'AnotherPassword789'), 'A reset token was accepted more than once.');
    $expiredReset = $tokens->issuePasswordReset($userId, -1);
    emailTestAssert(!$tokens->resetPassword($expiredReset, 'ExpiredPassword123'), 'An expired reset token was accepted.');

    $limiter = new MailRateLimiter($database);
    for ($attempt = 0; $attempt < 3; $attempt++) {
        emailTestAssert($limiter->allow('mail-test-email', $email, $ip, 3, 100), 'A request under the email rate limit was denied.');
    }
    emailTestAssert(!$limiter->allow('mail-test-email', $email, $ip, 3, 100), 'The email rate limit did not reject an extra request.');
    for ($attempt = 0; $attempt < 2; $attempt++) {
        emailTestAssert($limiter->allow('mail-test-ip', $email . $attempt, $ip, 10, 2), 'A request under the IP rate limit was denied.');
    }
    emailTestAssert(!$limiter->allow('mail-test-ip', 'other-' . $email, $ip, 10, 2), 'The IP rate limit did not reject an extra request.');
    foreach ([
        hash('sha256', 'mail-test-email:email:' . mb_strtolower($email, 'UTF-8')),
        hash('sha256', 'mail-test-email:ip:' . $ip),
        hash('sha256', 'mail-test-ip:ip:' . $ip),
    ] as $rateHash) {
        $rateHashes[] = $rateHash;
    }

    $database->prepare(
        "INSERT INTO settings (setting_key, setting_value, description)
         VALUES ('mailer.driver', 'outbox', 'Email infrastructure test')
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    )->execute();
    $outbox = new MailService($database, ['mailer' => ['driver' => 'outbox']]);
    $queued = $outbox->sendTemplate($email, 'Test outbox', 'Thư kiểm thử', ['Lưu hộp thư chờ.']);
    emailTestAssert($queued['accepted'] && $queued['queued'] && !$queued['delivered'], 'Outbox mode did not queue the email.');
    $messageIds[] = (int) $queued['id'];

    $database->prepare(
        "UPDATE settings SET setting_value = 'smtp' WHERE setting_key = 'mailer.driver'"
    )->execute();
    $failingMailer = new MailService(
        $database,
        ['mailer' => ['driver' => 'smtp', 'from_address' => 'no-reply@example.test', 'smtp' => []]],
        static function (): void {
            throw new RuntimeException('Simulated transport failure');
        }
    );
    $failedDelivery = $failingMailer->sendTemplate($email, 'Test transport failure', 'Thư kiểm thử', ['Thử dự phòng.']);
    emailTestAssert(
        $failedDelivery['accepted'] && $failedDelivery['queued'] && !$failedDelivery['delivered'],
        'A failed transport was not queued.'
    );
    $messageIds[] = (int) $failedDelivery['id'];
    $pending = $database->prepare(
        'SELECT status, attempts, last_error FROM mail_outbox WHERE id = :id'
    );
    $pending->execute(['id' => $failedDelivery['id']]);
    $failedRow = $pending->fetch();
    emailTestAssert(
        $failedRow['status'] === 'pending'
            && (int) $failedRow['attempts'] === 1
            && !str_contains((string) $failedRow['last_error'], 'Simulated transport failure'),
        'Transport failure details were not sanitized in the outbox.'
    );

    printf(
        "PASS: verification and reset tokens are hashed, expire, and are one-time; per-email/IP limits reject excess requests; outbox and failed-delivery fallback work.\n"
    );
} finally {
    foreach ($messageIds as $messageId) {
        $database->prepare('DELETE FROM mail_outbox WHERE id = :id')->execute(['id' => $messageId]);
    }
    foreach ($rateHashes as $rateHash) {
        $database->prepare('DELETE FROM mail_rate_limits WHERE key_hash = :key_hash')->execute(['key_hash' => $rateHash]);
    }
    $database->prepare('DELETE FROM audit_logs WHERE user_id = :user_id')->execute(['user_id' => $userId]);
    $database->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);
    if ($hasPreviousDriver) {
        $database->prepare(
            "UPDATE settings SET setting_value = :value WHERE setting_key = 'mailer.driver'"
        )->execute(['value' => $previousDriver]);
    } else {
        $database->prepare("DELETE FROM settings WHERE setting_key = 'mailer.driver'")->execute();
    }
}
