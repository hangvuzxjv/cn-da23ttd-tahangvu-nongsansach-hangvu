<?php

declare(strict_types=1);

final class LogMailer implements Mailer
{
    public function send(string $recipient, string $subject, string $body): void
    {
        $message = sprintf(
            "[%s]\nTo: %s\nSubject: %s\n\n%s\n%s",
            date(DATE_ATOM),
            $recipient,
            $subject,
            $body,
            str_repeat('-', 72) . "\n"
        );
        $written = file_put_contents(BACKEND_PATH . '/storage/logs/mail.log', $message, FILE_APPEND | LOCK_EX);
        if ($written === false) {
            throw new RuntimeException('Không thể ghi email vào backend/storage/logs/mail.log.');
        }
    }
}
