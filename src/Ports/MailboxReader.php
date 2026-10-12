<?php

declare(strict_types=1);

namespace MailMcp\Ports;

use MailMcp\Domain\Draft\SavedDraftInfo;
use MailMcp\Domain\Message\MailboxState;
use MailMcp\Domain\Message\MessageContent;
use MailMcp\Domain\Message\MessageListResult;
use MailMcp\Domain\Message\ReplyLookupResult;
use MailMcp\Domain\Message\ReplySource;

interface MailboxReader
{
    public function getMailboxState(): MailboxState;

    public function listNewMessages(int $sinceUid, int $limit): MessageListResult;

    public function readMessage(int $uid, int $maxChars): MessageContent;

    public function findReply(string $messageId): ReplyLookupResult;

    public function getReplySource(int $uid): ReplySource;

    public function findDraftByInReplyTo(string $messageId): ?SavedDraftInfo;
}
