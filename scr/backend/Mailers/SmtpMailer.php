<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

final class SmtpMailer implements Mailer
{
    public function __construct(private array $config)
    {
    }

    public function send(string $recipient, string $subject, string $body): void
    {
        if (!class_exists(PHPMailer::class)) {
            throw new RuntimeException('Thiếu PHPMailer. Chạy composer install trước khi dùng SMTP.');
        }

        $smtp = $this->config['smtp'] ?? [];
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = (string) ($smtp['host'] ?? '');
        $mail->Port = (int) ($smtp['port'] ?? 587);
        $mail->SMTPAuth = (string) ($smtp['username'] ?? '') !== '';
        $mail->Username = (string) ($smtp['username'] ?? '');
        $mail->Password = (string) ($smtp['password'] ?? '');
        $mail->SMTPSecure = ($smtp['encryption'] ?? 'tls') === 'ssl'
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom((string) $this->config['from_address'], (string) $this->config['from_name']);
        $mail->addAddress($recipient);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->isHTML(false);
        if (!$mail->send()) {
            throw new RuntimeException('SMTP không gửi được email: ' . $mail->ErrorInfo);
        }
    }
}
