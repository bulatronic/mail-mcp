<?php

declare(strict_types=1);

namespace MailMcp\Infrastructure\Imap;

use DirectoryTree\ImapEngine\FolderInterface;
use DirectoryTree\ImapEngine\Mailbox;

/**
 * Forces EXAMINE instead of SELECT for every folder open.
 * BODY.PEEK remains the fetch mode; EXAMINE keeps the mailbox read-only.
 */
final class ExamineOnlyMailbox extends Mailbox
{
    public function select(FolderInterface $folder, bool $force = false): void
    {
        if (!$this->selected($folder) || $force) {
            $this->connection()->examine($folder->path());
        }

        $this->selected = $folder;
    }
}
