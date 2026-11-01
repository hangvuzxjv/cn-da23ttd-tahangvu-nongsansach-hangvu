<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

final class MailService
{
    private PDO $database;
    private array $config;
    private ?Closure $transportOverride;

    public function __construct(?PDO $database = null, ?array $config = null, ?callable $transportOverride = null)
    {
        $this->database = $database ?? Database::connection();
        $application = $config ?? require BACKEND_PATH . '/config/config.php';
        $this->config = $application['mailer'] ?? ['driver' => 'outbox'];
        $this->transportOverride = $transportOverride === null ? null : Closure::fromCallable($transportOverride);
    }

    public function sendTemplate(
        string $recipient,
        string $subject,
        string $title,
        array $paragraphs,
        ?string $actionLabel = null,
        ?string $actionUrl = null,
        array $metadata = []
    ): array {
        $escapedTitle = self::escape($title);
        $text = $title . "\n\n" . implode("\n\n", array_map('strval', $paragraphs));
        $htmlParagraphs = '';
        foreach ($paragraphs as $paragraph) {
            $htmlParagraphs .= '<p style="margin:0 0 16px;line-height:1.6">'
                . nl2br(self::escape((string) $paragraph), false) . '</p>';
        }
        $html = '<!doctype html><html lang="vi"><meta charset="utf-8"><body style="font-family:Arial,sans-serif;color:#24312a">'
            . '<div style="max-width:600px;margin:24px auto;padding:24px;border:1px solid #e3ebe5;border-radius:8px">'
            . '<h1 style="font-size:22px;color:#198754">' . $escapedTitle . '</h1>'
            . $htmlParagraphs;
        if ($actionLabel !== null && $actionUrl !== null) {
            $html .= '<p><a style="display:inline-block;padding:11px 18px;background:#198754;color:#fff;text-decoration:none;border-radius:5px" href="'
                . self::escape($actionUrl) . '">' . self::escape($actionLabel) . '</a></p>';
            $text .= "\n\n" . $actionLabel . ': ' . $actionUrl;
        }
        $html .= '<p style="font-size:12px;color:#66736b">Nông Sản Sạch</p></div></body></html>';

        return $this->send($recipient, $subject, $text, $html, $metadata);
    }

    public function send(string $recipient, string $subject, string $textBody, ?string $htmlBody = null, array $metadata = []): array
    {
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || trim($subject) === '') {
            error_log('Mail delivery skipped: invalid message data.');
            return ['accepted' => false, 'delivered' => false, 'queued' => false, 'id' => null];
        }

        $htmlBody ??= '<!doctype html><html lang="vi"><meta charset="utf-8"><body><pre>'
            . self::escape($textBody) . '</pre></body></html>';
        $driver = $this->selectedDriver();
        $messageId = $this->createOutboxMessage($recipient, $subject, $textBody, $htmlBody, $driver, $metadata);
        if ($messageId === null) {
            return ['accepted' => false, 'delivered' => false, 'queued' => false, 'id' => null];
        }
        if ($driver === 'outbox') {
            return ['accepted' => true, 'delivered' => false, 'queued' => true, 'id' => $messageId];
        }

        $delivered = $this->deliver($messageId, $recipient, $subject, $textBody, $htmlBody, $driver);
        return ['accepted' => true, 'delivered' => $delivered, 'queued' => !$delivered, 'id' => $messageId];
    }

    public function resend(int $messageId): bool
    {
        $driver = $this->selectedDriver();
        if ($driver === 'outbox') {
            return false;
        }
        $query = $this->database->prepare(
            'SELECT id, recipient, subject, text_body, html_body FROM mail_outbox
             WHERE id = :id AND status = \'pending\' LIMIT 1'
        );
        $query->execute(['id' => $messageId]);
        $message = $query->fetch();
        if (!$message) {
            return false;
        }

        return $this->deliver(
            (int) $message['id'],
            (string) $message['recipient'],
            (string) $message['subject'],
            (string) $message['text_body'],
            (string) $message['html_body'],
            $driver
        );
    }

    public function selectedDriver(): string
    {
        $driver = (string) ($this->config['driver'] ?? 'outbox');
        try {
            $query = $this->database->prepare("SELECT setting_value FROM settings WHERE setting_key = 'mailer.driver' LIMIT 1");
            $query->execute();
            $override = $query->fetchColumn();
            if (is_string($override) && in_array($override, ['smtp', 'brevo', 'outbox'], true)) {
                $driver = $override;
            }
        } catch (PDOException) {
            error_log('Mail driver setting unavailable; using the configured driver.');
        }
        return $driver === 'log' || !in_array($driver, ['smtp', 'brevo', 'outbox'], true)
            ? 'outbox'
            : $driver;
    }

    public static function maskedRecipient(string $recipient): string
    {
        [$local, $domain] = array_pad(explode('@', $recipient, 2), 2, '');
        if ($domain === '') {
            return '***';
        }
        return substr($local, 0, 1) . str_repeat('*', max(2, min(8, strlen($local) - 1))) . '@' . $domain;
    }

    private function createOutboxMessage(
        string $recipient,
        string $subject,
        string $textBody,
        string $htmlBody,
        string $driver,
        array $metadata
    ): ?int {
        try {
            $statement = $this->database->prepare(
                'INSERT INTO mail_outbox
                    (recipient, subject, text_body, html_body, driver, status, last_error, metadata)
                 VALUES (:recipient, :subject, :text_body, :html_body, :driver, \'pending\',
                         :last_error, :metadata)'
            );
            $statement->execute([
                'recipient' => $recipient,
                'subject' => mb_substr($subject, 0, 200, 'UTF-8'),
                'text_body' => $textBody,
                'html_body' => $htmlBody,
                'driver' => $driver,
                'last_error' => $driver === 'outbox' ? 'Chế độ hộp thư chờ; chưa gửi.' : null,
                'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ]);
            return (int) $this->database->lastInsertId();
        } catch (Throwable) {
            error_log('Mail delivery failed: could not save message to the outbox.');
            return null;
        }
    }

    private function deliver(
        int $messageId,
        string $recipient,
        string $subject,
        string $textBody,
        string $htmlBody,
        string $driver
    ): bool {
        try {
            if ($this->transportOverride !== null) {
                ($this->transportOverride)($driver, $recipient, $subject, $htmlBody, $textBody, $this->config);
            } elseif ($driver === 'smtp') {
                $this->sendSmtp($recipient, $subject, $htmlBody, $textBody);
            } elseif ($driver === 'brevo') {
                $this->sendBrevo($recipient, $subject, $htmlBody, $textBody);
            } else {
                throw new RuntimeException('Không có dịch vụ gửi thư được bật.');
            }
            $this->database->prepare(
                'UPDATE mail_outbox SET status = \'sent\', driver = :driver, attempts = attempts + 1,
                    last_error = NULL, last_attempted_at = NOW(), sent_at = NOW() WHERE id = :id'
            )->execute(['driver' => $driver, 'id' => $messageId]);
            return true;
        } catch (Throwable) {
            try {
                $this->database->prepare(
                    'UPDATE mail_outbox SET status = \'pending\', driver = :driver,
                        attempts = attempts + 1, last_error = :error, last_attempted_at = NOW()
                     WHERE id = :id'
                )->execute([
                    'driver' => $driver,
                    'error' => 'Kênh ' . $driver . ' không gửi được; thư được lưu để gửi lại.',
                    'id' => $messageId,
                ]);
            } catch (Throwable) {
                error_log('Mail delivery failed: could not update outbox status.');
            }
            error_log('Mail delivery failed; message is queued for retry (id=' . $messageId . ').');
            return false;
        }
    }

    private function sendSmtp(string $recipient, string $subject, string $htmlBody, string $textBody): void
    {
        if (!class_exists(PHPMailer::class)) {
            throw new RuntimeException('PHPMailer is unavailable.');
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
        $mail->setFrom((string) ($this->config['from_address'] ?? ''), (string) ($this->config['from_name'] ?? 'Nông Sản Sạch'));
        $mail->addAddress($recipient);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody;
        $mail->send();
    }

    private function sendBrevo(string $recipient, string $subject, string $htmlBody, string $textBody): void
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL is unavailable.');
        }
        $apiKey = trim((string) ($this->config['brevo']['api_key'] ?? ''));
        if ($apiKey === '') {
            throw new RuntimeException('Brevo API key is not configured.');
        }
        $payload = json_encode([
            'sender' => [
                'name' => (string) ($this->config['from_name'] ?? 'Nông Sản Sạch'),
                'email' => (string) ($this->config['from_address'] ?? ''),
            ],
            'to' => [['email' => $recipient]],
            'subject' => $subject,
            'htmlContent' => $htmlBody,
            'textContent' => $textBody,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $curl = curl_init('https://api.brevo.com/v3/smtp/email');
        if ($curl === false) {
            throw new RuntimeException('Unable to initialize cURL.');
        }
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'api-key: ' . $apiKey,
                'content-type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        if (!is_string($response) || $status < 200 || $status >= 300) {
            throw new RuntimeException('Brevo request failed.');
        }
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
