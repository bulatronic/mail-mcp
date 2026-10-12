<?php

declare(strict_types=1);

namespace MailMcp\Tests\Unit;

use DirectoryTree\ImapEngine\Testing\FakeFolder;
use DirectoryTree\ImapEngine\Testing\FakeMailbox;
use MailMcp\Domain\Error\MailboxError;
use MailMcp\Domain\Message\MailboxRole;
use MailMcp\Infrastructure\Config\MailConfig;
use MailMcp\Infrastructure\Imap\FolderResolver;
use PHPUnit\Framework\TestCase;

final class FolderResolverTest extends TestCase
{
    public function testResolvesSentAndDraftsBySpecialUse(): void
    {
        $mailbox = new FakeMailbox(folders: [
            new FakeFolder('INBOX'),
            new FakeFolder('Отправленные', ['\\Sent']),
            new FakeFolder('Черновики', ['\\Drafts']),
        ]);

        $resolver = new FolderResolver($this->config());

        self::assertSame('INBOX', $resolver->resolve($mailbox, MailboxRole::Inbox)->path());
        self::assertSame('Отправленные', $resolver->resolve($mailbox, MailboxRole::Sent)->path());
        self::assertSame('Черновики', $resolver->resolve($mailbox, MailboxRole::Drafts)->path());
    }

    public function testFallsBackToEnvFolderNames(): void
    {
        $mailbox = new FakeMailbox(folders: [
            new FakeFolder('INBOX'),
            new FakeFolder('Sent Box'),
            new FakeFolder('Draft Box'),
        ]);

        $resolver = new FolderResolver($this->config(
            sentFolder: 'Sent Box',
            draftsFolder: 'Draft Box',
        ));

        self::assertSame('Sent Box', $resolver->resolve($mailbox, MailboxRole::Sent)->path());
        self::assertSame('Draft Box', $resolver->resolve($mailbox, MailboxRole::Drafts)->path());
    }

    public function testThrowsWhenFolderCannotBeResolved(): void
    {
        $mailbox = new FakeMailbox(folders: [
            new FakeFolder('INBOX'),
        ]);

        $resolver = new FolderResolver($this->config());

        $this->expectException(MailboxError::class);
        $resolver->resolve($mailbox, MailboxRole::Sent);
    }

    private function config(?string $sentFolder = null, ?string $draftsFolder = null): MailConfig
    {
        return new MailConfig(
            host: 'imap.example.test',
            port: 993,
            username: 'user',
            password: 'secret',
            timeoutSeconds: 15,
            sentFolder: $sentFolder,
            draftsFolder: $draftsFolder,
        );
    }
}
