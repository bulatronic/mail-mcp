<?php

declare(strict_types=1);

namespace MailMcp\Infrastructure\Config;

use MailMcp\Domain\Error\MailboxError;

final readonly class MailConfig
{
    public function __construct(
        public string $host,
        public int $port,
        #[\SensitiveParameter]
        public string $username,
        #[\SensitiveParameter]
        public string $password,
        public int $timeoutSeconds,
        public ?string $sentFolder,
        public ?string $draftsFolder,
        public ?string $fromName = null,
    ) {
    }

    public static function fromEnvironment(): self
    {
        $username = self::envString('MAIL_USER');
        $password = self::envString('MAIL_APP_PASSWORD');
        $host = self::envString('MAIL_IMAP_HOST', 'imap.mail.ru');
        $portRaw = self::envString('MAIL_IMAP_PORT', '993');
        $sent = self::envString('MAIL_SENT_FOLDER');
        $drafts = self::envString('MAIL_DRAFTS_FOLDER');
        $fromName = self::envString('MAIL_FROM_NAME');

        $port = ctype_digit($portRaw) ? (int) $portRaw : 993;

        return new self(
            host: $host !== '' ? $host : 'imap.mail.ru',
            port: $port > 0 ? $port : 993,
            username: $username,
            password: $password,
            timeoutSeconds: 15,
            sentFolder: $sent !== '' ? $sent : null,
            draftsFolder: $drafts !== '' ? $drafts : null,
            fromName: $fromName !== '' ? $fromName : null,
        );
    }

    public function assertCredentialsPresent(): void
    {
        if ($this->username === '' || $this->password === '') {
            throw new MailboxError('MAIL_USER and MAIL_APP_PASSWORD must be set');
        }
    }

    private static function envString(string $key, string $default = ''): string
    {
        $value = getenv($key);
        if (is_string($value)) {
            return $value;
        }

        $env = $_ENV[$key] ?? null;

        return is_string($env) ? $env : $default;
    }
}
