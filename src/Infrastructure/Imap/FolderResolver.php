<?php

declare(strict_types=1);

namespace MailMcp\Infrastructure\Imap;

use DirectoryTree\ImapEngine\FolderInterface;
use DirectoryTree\ImapEngine\MailboxInterface;
use MailMcp\Domain\Error\MailboxError;
use MailMcp\Domain\Message\MailboxRole;
use MailMcp\Infrastructure\Config\MailConfig;

final readonly class FolderResolver
{
    public function __construct(
        private MailConfig $config,
    ) {
    }

    public function resolve(MailboxInterface $mailbox, MailboxRole $role): FolderInterface
    {
        return match ($role) {
            MailboxRole::Inbox => $mailbox->inbox(),
            MailboxRole::Sent => $this->resolveSpecial($mailbox, '\\Sent', $this->config->sentFolder, 'Sent'),
            MailboxRole::Drafts => $this->resolveSpecial($mailbox, '\\Drafts', $this->config->draftsFolder, 'Drafts'),
        };
    }

    private function resolveSpecial(
        MailboxInterface $mailbox,
        string $specialUse,
        ?string $envFallback,
        string $roleLabel,
    ): FolderInterface {
        foreach ($mailbox->folders()->get() as $folder) {
            /** @var list<string> $flags */
            $flags = array_values(array_map(strval(...), $folder->flags()));
            if ($this->hasSpecialUse($flags, $specialUse)) {
                return $folder;
            }
        }

        if ($envFallback !== null && $envFallback !== '') {
            $folder = $mailbox->folders()->find($envFallback);
            if ($folder !== null) {
                return $folder;
            }
        }

        throw new MailboxError(sprintf('Unable to resolve %s folder via SPECIAL-USE or env fallback', $roleLabel));
    }

    /**
     * @param list<string> $flags
     */
    private function hasSpecialUse(array $flags, string $specialUse): bool
    {
        $normalizedTarget = strtolower(ltrim($specialUse, '\\'));

        foreach ($flags as $flag) {
            $normalized = strtolower(ltrim((string) $flag, '\\'));
            if ($normalized === $normalizedTarget) {
                return true;
            }
        }

        return false;
    }
}
