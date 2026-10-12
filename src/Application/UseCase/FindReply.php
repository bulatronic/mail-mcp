<?php

declare(strict_types=1);

namespace MailMcp\Application\UseCase;

use InvalidArgumentException;
use MailMcp\Domain\Message\ReplyLookupResult;
use MailMcp\Ports\MailboxReader;

final readonly class FindReply
{
    public function __construct(
        private MailboxReader $mailboxReader,
    ) {
    }

    public function execute(string $messageId): ReplyLookupResult
    {
        $messageId = trim($messageId);
        if ($messageId === '') {
            throw new InvalidArgumentException('message_id must not be empty');
        }

        return $this->mailboxReader->findReply($messageId);
    }
}
