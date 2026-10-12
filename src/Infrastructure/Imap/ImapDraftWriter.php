<?php

declare(strict_types=1);

namespace MailMcp\Infrastructure\Imap;

use DirectoryTree\ImapEngine\Enums\ImapFlag;
use DirectoryTree\ImapEngine\Exceptions\ImapCommandException;
use DirectoryTree\ImapEngine\Exceptions\ImapConnectionException;
use MailMcp\Domain\Draft\Draft;
use MailMcp\Domain\Draft\SavedDraftInfo;
use MailMcp\Domain\Error\DraftError;
use MailMcp\Domain\Message\MailboxRole;
use MailMcp\Infrastructure\Mime\DraftMimeBuilder;
use MailMcp\Ports\DraftWriter;
use Throwable;

final class ImapDraftWriter implements DraftWriter
{
    public function __construct(
        private readonly ImapConnectionFactory $connectionFactory,
        private readonly FolderResolver $folderResolver,
        private readonly DraftMimeBuilder $mimeBuilder = new DraftMimeBuilder(),
    ) {}

    public function appendDraft(Draft $draft): SavedDraftInfo
    {
        $mailbox = $this->connectionFactory->create();

        try {
            $mailbox->connect();
            $folder = $this->folderResolver->resolve($mailbox, MailboxRole::Drafts);
            $raw = $this->mimeBuilder->build($draft);

            $folder->messages()->append($raw, [
                ImapFlag::Draft,
                ImapFlag::Seen,
            ]);

            return new SavedDraftInfo(
                folder: $folder->name(),
                subject: $draft->subject,
                to: $draft->to->format(),
            );
        } catch (DraftError $e) {
            throw $e;
        } catch (ImapConnectionException|ImapCommandException $e) {
            throw new DraftError('Failed to append draft', 0, $e);
        } catch (Throwable $e) {
            throw new DraftError('Failed to append draft', 0, $e);
        } finally {
            $mailbox->disconnect();
        }
    }
}
