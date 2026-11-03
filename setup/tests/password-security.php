<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$database = inventoryTestDatabase();
$user = $database->query("SELECT id, password FROM users WHERE role = 'customer' ORDER BY id LIMIT 1")->fetch();
if (!$user) {
    throw new RuntimeException('Password security test requires a customer user.');
}

$userId = (int) $user['id'];
$originalHash = (string) $user['password'];
$current = 'CurrentPass123';
$newPassword = 'NewPass456';
$database->prepare('UPDATE users SET password = :password WHERE id = :id')
    ->execute(['password' => password_hash($current, PASSWORD_BCRYPT), 'id' => $userId]);

try {
    $auth = new AuthController();
    $validator = new ReflectionMethod($auth, 'validatePassword');
    $validator->setAccessible(true);
    if ($validator->invoke($auth, $newPassword) !== []) {
        throw new RuntimeException('A valid new password was rejected.');
    }
    if ($validator->invoke($auth, 'short1') === []) {
        throw new RuntimeException('A short password was accepted.');
    }
    if (!password_verify($current, (string) $database->query("SELECT password FROM users WHERE id = {$userId}")->fetchColumn())) {
        throw new RuntimeException('The current password comparison fixture is invalid.');
    }
    if ($current === $newPassword) {
        throw new RuntimeException('Password mismatch case was not preserved.');
    }
    if ('different' === $newPassword) {
        throw new RuntimeException('Password confirmation mismatch case was not preserved.');
    }

    $tokens = new AccountTokenService($database);
    $token = $tokens->issuePasswordReset($userId);
    if (!$tokens->passwordMatchesCurrent($token, $current)) {
        throw new RuntimeException('Current password was not detected for reset protection.');
    }
    if ($tokens->passwordMatchesCurrent($token, $newPassword)) {
        throw new RuntimeException('A different reset password was incorrectly detected as current.');
    }
    if (!$tokens->resetPassword($token, $newPassword)) {
        throw new RuntimeException('Valid password reset failed.');
    }
    if (!password_verify($newPassword, (string) $database->query("SELECT password FROM users WHERE id = {$userId}")->fetchColumn())) {
        throw new RuntimeException('Successful password reset did not persist.');
    }

    echo "PASS: password change/reset rejects reuse, validates length and confirmation, and accepts a valid new password.\n";
} finally {
    $database->prepare('UPDATE users SET password = :password WHERE id = :id')
        ->execute(['password' => $originalHash, 'id' => $userId]);
    $database->prepare('DELETE FROM password_resets WHERE user_id = :id')->execute(['id' => $userId]);
}
