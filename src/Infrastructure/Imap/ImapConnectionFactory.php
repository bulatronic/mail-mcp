<?php

declare(strict_types=1);

namespace MailMcp\Infrastructure\Imap;

use MailMcp\Infrastructure\Config\MailConfig;

final readonly class ImapConnectionFactory
{
    public function __construct(
        private MailConfig $config,
    ) {}

    public function create(): ExamineOnlyMailbox
    {
        $this->config->assertCredentialsPresent();

        return new ExamineOnlyMailbox([
            'host' => $this->config->host,
            'port' => $this->config->port,
            'username' => $this->config->username,
            'password' => $this->config->password,
            'encryption' => 'ssl',
            'validate_cert' => true,
            'authentication' => 'plain',
            'timeout' => $this->config->timeoutSeconds,
        ]);
    }
}
