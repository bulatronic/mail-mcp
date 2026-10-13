<?php

declare(strict_types=1);

namespace MailMcp\Domain\Message;

use MailMcp\Domain\Draft\Recipient;

final readonly class ReplySource
{
    public function __construct(
        public int $uid,
        public string $messageId,
        public string $subject,
        public string $references,
        public Recipient $recipient,
        public string $fromEmail,
        public ?string $replyToEmail,
        public string $quoteAuthorName,
        public string $quoteDate,
        public string $quoteBody,
    ) {}
}
