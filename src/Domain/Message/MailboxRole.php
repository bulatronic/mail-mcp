<?php

declare(strict_types=1);

namespace MailMcp\Domain\Message;

enum MailboxRole: string
{
    case Inbox = 'inbox';
    case Sent = 'sent';
    case Drafts = 'drafts';
}
