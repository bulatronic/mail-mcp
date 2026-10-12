<?php

declare(strict_types=1);

namespace MailMcp\Application\UseCase;

use InvalidArgumentException;
use MailMcp\Domain\Message\MessageListResult;
use MailMcp\Ports\MailboxReader;

final class ListNewMessages
{
    private const int MAX_LIMIT = 100;

    public function __construct(
        private readonly MailboxReader $mailboxReader,
    ) {}

    public function execute(int $sinceUid, int $limit = 50): MessageListResult
    {
        if ($sinceUid < 0) {
            throw new InvalidArgumentException('since_uid must be >= 0');
        }

        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw new InvalidArgumentException(sprintf('limit must be between 1 and %d', self::MAX_LIMIT));
        }

        return $this->mailboxReader->listNewMessages($sinceUid, $limit);
    }
}
