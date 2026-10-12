<?php

declare(strict_types=1);

namespace MailMcp\Infrastructure\Imap;

use DirectoryTree\ImapEngine\Connection\ImapQueryBuilder;
use DirectoryTree\ImapEngine\Exceptions\ImapCommandException;
use DirectoryTree\ImapEngine\Exceptions\ImapConnectionException;
use DirectoryTree\ImapEngine\FolderInterface;
use DirectoryTree\ImapEngine\Message;
use DirectoryTree\ImapEngine\MessageQueryInterface;
use MailMcp\Domain\Error\MailboxError;
use MailMcp\Domain\Message\MailboxRole;
use MailMcp\Domain\Message\MailboxState;
use MailMcp\Domain\Message\MessageContent;
use MailMcp\Domain\Message\MessageListResult;
use MailMcp\Domain\Message\ReplyLookupResult;
use MailMcp\Infrastructure\Mime\MessageContentFactory;
use MailMcp\Ports\MailboxReader;
use Throwable;

final readonly class ImapMailboxReader implements MailboxReader
{
    public function __construct(
        private ImapConnectionFactory $connectionFactory,
        private FolderResolver        $folderResolver,
        private MessageContentFactory $messageContentFactory = new MessageContentFactory(),
    ) {
    }

    public function getMailboxState(): MailboxState
    {
        return $this->withMailbox(function (ExamineOnlyMailbox $mailbox): MailboxState {
            $inbox = $this->folderResolver->resolve($mailbox, MailboxRole::Inbox);
            $status = $this->statusInts($inbox->status());

            return new MailboxState(
                uidvalidity: $status['uidvalidity'],
                lastUid: max(0, $status['uidnext'] - 1),
                totalMessages: $status['messages'],
            );
        });
    }

    public function listNewMessages(int $sinceUid, int $limit): MessageListResult
    {
        return $this->withMailbox(function (ExamineOnlyMailbox $mailbox) use ($sinceUid, $limit): MessageListResult {
            $inbox = $this->folderResolver->resolve($mailbox, MailboxRole::Inbox);
            $status = $this->statusInts($inbox->status());

            $query = $inbox->messages();
            $query->uid($sinceUid + 1, INF);
            $query->oldest();
            $query->limit($limit);
            $query->withHeaders();
            $query->withSize();
            $query->withBodyStructure();
            $query->leaveUnread();
            $messages = $query->get();

            $summaries = [];
            foreach ($messages as $message) {
                if (!$message instanceof Message) {
                    continue;
                }

                $hasAttachments = $message->bodyStructure()?->hasAttachments() ?? false;
                $summaries[] = $this->messageContentFactory->summaryFromMessage(
                    $message,
                    $message->uid(),
                    $message->size(),
                    $hasAttachments,
                );
            }

            return new MessageListResult($status['uidvalidity'], $summaries);
        });
    }

    public function readMessage(int $uid, int $maxChars): MessageContent
    {
        return $this->withMailbox(function (ExamineOnlyMailbox $mailbox) use ($uid, $maxChars): MessageContent {
            $inbox = $this->folderResolver->resolve($mailbox, MailboxRole::Inbox);

            $query = $inbox->messages();
            $query->withHeaders();
            $query->withSize();
            $query->withBodyStructure();
            $query->leaveUnread();
            $message = $query->find($uid);

            if (!$message instanceof Message) {
                throw new MailboxError(sprintf('Message UID %d not found in INBOX', $uid));
            }

            $message->bodyStructure(fetch: true);

            return $this->messageContentFactory->contentFromMessage($message, $uid, $maxChars);
        });
    }

    public function findReply(string $messageId): ReplyLookupResult
    {
        return $this->withMailbox(function (ExamineOnlyMailbox $mailbox) use ($messageId): ReplyLookupResult {
            $sent = $this->folderResolver->resolve($mailbox, MailboxRole::Sent);
            $needle = trim($messageId, " \t<>");

            $message = $this->searchReply($sent, $needle);
            if ($message === null) {
                return ReplyLookupResult::notFound();
            }

            return new ReplyLookupResult(
                replied: true,
                sentUid: $message->uid(),
                sentDate: $message->date()?->toIso8601String(),
            );
        });
    }

    private function searchReply(FolderInterface $sent, string $needle): ?Message
    {
        $variants = array_values(array_unique([
            $needle,
            '<'.$needle.'>',
        ]));

        foreach ($variants as $variant) {
            $query = $sent->messages();
            $query->where(static function (ImapQueryBuilder $builder) use ($variant): void {
                $builder->header('In-Reply-To', $variant);
                $builder->orWhere(static function (ImapQueryBuilder $inner) use ($variant): void {
                    $inner->header('References', $variant);
                });
            });
            $query->withHeaders();
            $query->newest();
            $query->limit(1);
            $query->leaveUnread();
            $found = $query->get()->first();

            if ($found instanceof Message) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param array $status
     *
     * @return array{uidvalidity: int, uidnext: int, messages: int}
     */
    private function statusInts(array $status): array
    {
        return [
            'uidvalidity' => $this->toInt($status['UIDVALIDITY'] ?? 0),
            'uidnext' => $this->toInt($status['UIDNEXT'] ?? 1),
            'messages' => $this->toInt($status['MESSAGES'] ?? 0),
        ];
    }

    private function toInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }

    /**
     * @template T
     *
     * @param callable(ExamineOnlyMailbox): T $callback
     *
     * @return T
     */
    private function withMailbox(callable $callback): mixed
    {
        $mailbox = $this->connectionFactory->create();

        try {
            $mailbox->connect();

            return $callback($mailbox);
        } catch (MailboxError $e) {
            throw $e;
        } catch (ImapConnectionException|ImapCommandException $e) {
            throw new MailboxError('IMAP operation failed', 0, $e);
        } catch (Throwable $e) {
            throw new MailboxError('IMAP operation failed', 0, $e);
        } finally {
            $mailbox->disconnect();
        }
    }
}
