<?php

declare(strict_types=1);

namespace MailMcp\Application\UseCase;

use InvalidArgumentException;
use MailMcp\Domain\Message\MessageContent;
use MailMcp\Ports\MailboxReader;

final readonly class ReadMessage
{
    public function __construct(
        private MailboxReader $mailboxReader,
    ) {}

    public function execute(int $uid, int $maxChars = 6000): MessageContent
    {
        if ($uid < 1) {
            throw new InvalidArgumentException('uid must be >= 1');
        }

        if ($maxChars < 1) {
            throw new InvalidArgumentException('max_chars must be >= 1');
        }

        return $this->mailboxReader->readMessage($uid, $maxChars);
    }
}
