<?php

declare(strict_types=1);

namespace MailMcp\Domain\Message;

final readonly class MessageListResult
{
    /**
     * @param list<MessageSummary> $messages
     */
    public function __construct(
        public int $uidvalidity,
        public array $messages,
    ) {
    }
}
