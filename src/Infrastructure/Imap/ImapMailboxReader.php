<?php

declare(strict_types=1);

namespace MailMcp\Infrastructure\Imap;

use DirectoryTree\ImapEngine\Connection\ImapQueryBuilder;
use DirectoryTree\ImapEngine\Exceptions\ImapCommandException;
use DirectoryTree\ImapEngine\Exceptions\ImapConnectionException;
use DirectoryTree\ImapEngine\FolderInterface;
use DirectoryTree\ImapEngine\Message;
use DirectoryTree\ImapEngine\MessageQueryInterface;
use MailMcp\Domain\Draft\Recipient;
use MailMcp\Domain\Draft\SavedDraftInfo;
use MailMcp\Domain\Error\MailboxError;
use MailMcp\Domain\Message\MailboxRole;
use MailMcp\Domain\Message\MailboxState;
use MailMcp\Domain\Message\MessageContent;
use MailMcp\Domain\Message\MessageListResult;
use MailMcp\Domain\Message\ReplyLookupResult;
use MailMcp\Domain\Message\ReplySource;
use MailMcp\Infrastructure\Mime\MessageContentFactory;
use MailMcp\Infrastructure\Mime\TextSanitizer;
use MailMcp\Ports\MailboxReader;
use Throwable;

final readonly class ImapMailboxReader implements MailboxReader
{
    public function __construct(
        private ImapConnectionFactory $connectionFactory,
        private FolderResolver $folderResolver,
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

            /** @var MessageQueryInterface $query */
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

            /** @var MessageQueryInterface $query */
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

            $message = $this->searchByInReplyToOrReferences($sent, $needle);
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

    public function getReplySource(int $uid): ReplySource
    {
        return $this->withMailbox(function (ExamineOnlyMailbox $mailbox) use ($uid): ReplySource {
            $inbox = $this->folderResolver->resolve($mailbox, MailboxRole::Inbox);

            /** @var MessageQueryInterface $query */
            $query = $inbox->messages();
            $query->withHeaders();
            $query->withBodyStructure();
            $query->leaveUnread();
            $message = $query->find($uid);

            if (!$message instanceof Message) {
                throw new MailboxError(sprintf('Message UID %d not found in INBOX', $uid));
            }

            $message->bodyStructure(fetch: true);
            $content = $this->messageContentFactory->contentFromMessage($message, $uid, 4000);

            $replyTo = $message->replyTo();
            $from = $message->from();
            $recipientAddress = $replyTo ?? $from;
            if ($recipientAddress === null || trim($recipientAddress->email()) === '') {
                throw new MailboxError(sprintf('Message UID %d has no Reply-To/From address', $uid));
            }

            $authorName = '';
            if ($from !== null) {
                $authorName = trim($from->name()) !== '' ? $from->name() : $from->email();
            }

            $referencesHeader = $message->header('References');
            $references = $referencesHeader !== null
                ? trim((string) ($referencesHeader->getRawValue() !== '' ? $referencesHeader->getRawValue() : $referencesHeader->getValue()))
                : '';

            return new ReplySource(
                uid: $uid,
                messageId: trim((string) ($message->messageId() ?? '')),
                subject: TextSanitizer::sanitize((string) ($message->subject() ?? '')),
                references: $references,
                recipient: new Recipient(
                    email: $recipientAddress->email(),
                    name: $recipientAddress->name(),
                ),
                quoteAuthorName: $authorName,
                quoteDate: $message->date()?->format('d.m.Y H:i') ?? '',
                quoteBody: $content->text,
            );
        });
    }

    public function findDraftByInReplyTo(string $messageId): ?SavedDraftInfo
    {
        return $this->withMailbox(function (ExamineOnlyMailbox $mailbox) use ($messageId): ?SavedDraftInfo {
            $drafts = $this->folderResolver->resolve($mailbox, MailboxRole::Drafts);
            $needle = trim($messageId, " \t<>");
            $message = $this->searchDraftByInReplyTo($drafts, $needle);
            if (!$message instanceof Message) {
                return null;
            }

            $to = array_values($message->to());
            $toFormatted = '';
            if ($to !== []) {
                $first = $to[0];
                $toFormatted = trim($first->name()) !== ''
                    ? sprintf('%s <%s>', $first->name(), $first->email())
                    : $first->email();
            }

            return new SavedDraftInfo(
                folder: $drafts->name(),
                subject: (string) ($message->subject() ?? ''),
                to: $toFormatted,
            );
        });
    }

    private function searchByInReplyToOrReferences(FolderInterface $folder, string $needle): ?Message
    {
        $variants = array_values(array_unique([
            $needle,
            '<'.$needle.'>',
        ]));

        foreach ($variants as $variant) {
            /** @var MessageQueryInterface $query */
            $query = $folder->messages();
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

    private function searchDraftByInReplyTo(FolderInterface $drafts, string $needle): ?Message
    {
        $variants = array_values(array_unique([
            $needle,
            '<'.$needle.'>',
        ]));

        foreach ($variants as $variant) {
            /** @var MessageQueryInterface $query */
            $query = $drafts->messages();
            $query->header('In-Reply-To', $variant);
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
     * @param array<mixed> $status
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
