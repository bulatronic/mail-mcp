<?php

declare(strict_types=1);

namespace MailMcp\Domain\Draft;

final readonly class Draft
{
    public function __construct(
        public Recipient $to,
        public string $fromEmail,
        public ?string $fromName,
        public string $subject,
        public string $inReplyTo,
        public string $references,
        public string $bodyText,
    ) {}
}
