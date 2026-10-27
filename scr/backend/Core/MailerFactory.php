<?php

declare(strict_types=1);

final class MailerFactory
{
    public static function make(array $config): Mailer
    {
        return match ($config['driver'] ?? 'log') {
            'log' => new LogMailer(),
            'smtp' => new SmtpMailer($config),
            default => throw new RuntimeException('Mailer driver không được hỗ trợ: ' . (string) $config['driver']),
        };
    }
}
