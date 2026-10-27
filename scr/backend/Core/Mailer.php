<?php

declare(strict_types=1);

interface Mailer
{
    public function send(string $recipient, string $subject, string $body): void;
}
