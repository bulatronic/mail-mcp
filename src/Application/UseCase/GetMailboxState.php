<?php

declare(strict_types=1);

namespace MailMcp\Application\UseCase;

use MailMcp\Domain\Message\MailboxState;
use MailMcp\Ports\MailboxReader;

final readonly class GetMailboxState
{
    public function __construct(
        private MailboxReader $mailboxReader,
    ) {
    }

    public function execute(): MailboxState
    {
        return $this->mailboxReader->getMailboxState();
    }
}
