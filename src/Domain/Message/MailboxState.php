<?php

declare(strict_types=1);

namespace MailMcp\Domain\Message;

final readonly class MailboxState
{
    public function __construct(
        public int $uidvalidity,
        public int $lastUid,
        public int $totalMessages,
    ) {
    }
}
