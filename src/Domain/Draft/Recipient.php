<?php

declare(strict_types=1);

namespace MailMcp\Domain\Draft;

final readonly class Recipient
{
    public function __construct(
        public string $email,
        public string $name = '',
    ) {}

    public function format(): string
    {
        $name = trim($this->name);
        if ($name === '') {
            return $this->email;
        }

        return sprintf('%s <%s>', $name, $this->email);
    }
}
