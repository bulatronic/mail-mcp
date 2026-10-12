<?php

declare(strict_types=1);

namespace MailMcp\Domain\Message;

final readonly class MessageSummary
{
    public function __construct(
        public int $uid,
        public string $fromName,
        public string $fromEmail,
        public string $to,
        public string $subject,
        public string $date,
        public string $messageId,
        public string $inReplyTo,
        public int $size,
        public bool $hasAttachments,
        public bool $isAutomated,
    ) {}
}
