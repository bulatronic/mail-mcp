<?php

declare(strict_types=1);

namespace MailMcp\Tests\Unit;

use MailMcp\Application\UseCase\SaveDraft;
use MailMcp\Domain\Draft\Draft;
use MailMcp\Domain\Draft\Recipient;
use MailMcp\Domain\Draft\SavedDraftInfo;
use MailMcp\Domain\Error\DraftError;
use MailMcp\Domain\Error\MailboxError;
use MailMcp\Domain\Message\ReplySource;
use MailMcp\Ports\DraftWriter;
use MailMcp\Ports\MailboxReader;
use PHPUnit\Framework\TestCase;

final class SaveDraftTest extends TestCase
{
    public function testCreatesDraftWithExpectedFields(): void
    {
        $source = $this->source();

        $reader = $this->createMock(MailboxReader::class);
        $reader->expects(self::once())->method('getReplySource')->with(10)->willReturn($source);
        $reader->expects(self::once())->method('findDraftByInReplyTo')->with('<orig@example.com>')->willReturn(null);

        $writer = $this->createMock(DraftWriter::class);
        $writer->expects(self::once())
            ->method('appendDraft')
            ->with(self::callback(static function (Draft $draft): bool {
                return $draft->to->email === 'alice@example.com'
                    && $draft->subject === 'Re: Hello'
                    && $draft->inReplyTo === '<orig@example.com>'
                    && str_contains($draft->references, '<prev@example.com>')
                    && str_contains($draft->references, '<orig@example.com>')
                    && str_contains($draft->bodyText, 'Thanks')
                    && str_contains($draft->bodyText, 'написал(а):')
                    && str_contains($draft->bodyText, '> Original line');
            }))
            ->willReturn(new SavedDraftInfo('Черновики', 'Re: Hello', 'Alice <alice@example.com>'));

        $result = $this->useCase($reader, $writer)->execute(10, 'Thanks');

        self::assertTrue($result->created);
        self::assertSame('Черновики', $result->folder);
        self::assertSame('Re: Hello', $result->subject);
        self::assertSame('Alice <alice@example.com>', $result->to);
    }

    public function testIdempotentWhenDraftAlreadyExists(): void
    {
        $source = $this->source();
        $existing = new SavedDraftInfo('Черновики', 'Re: Hello', 'alice@example.com');

        $reader = $this->createMock(MailboxReader::class);
        $reader->method('getReplySource')->willReturn($source);
        $reader->method('findDraftByInReplyTo')->willReturn($existing);

        $writer = $this->createMock(DraftWriter::class);
        $writer->expects(self::never())->method('appendDraft');

        $result = $this->useCase($reader, $writer)->execute(10, 'Thanks again');

        self::assertFalse($result->created);
        self::assertSame('Черновики', $result->folder);
        self::assertSame('Re: Hello', $result->subject);
    }

    public function testRejectsEmptyBody(): void
    {
        $reader = $this->createMock(MailboxReader::class);
        $writer = $this->createMock(DraftWriter::class);
        $reader->expects(self::never())->method('getReplySource');

        $this->expectException(DraftError::class);
        $this->useCase($reader, $writer)->execute(10, '');
    }

    public function testRejectsMissingSourceUid(): void
    {
        $reader = $this->createMock(MailboxReader::class);
        $reader->method('getReplySource')->willThrowException(new MailboxError('not found'));

        $writer = $this->createMock(DraftWriter::class);
        $writer->expects(self::never())->method('appendDraft');

        $this->expectException(DraftError::class);
        $this->useCase($reader, $writer)->execute(999, 'Hello');
    }

    public function testDoesNotDuplicateRePrefix(): void
    {
        $source = $this->source(subject: 'Re: Already');

        $reader = $this->createMock(MailboxReader::class);
        $reader->method('getReplySource')->willReturn($source);
        $reader->method('findDraftByInReplyTo')->willReturn(null);

        $writer = $this->createMock(DraftWriter::class);
        $writer->expects(self::once())
            ->method('appendDraft')
            ->with(self::callback(static fn (Draft $draft): bool => $draft->subject === 'Re: Already'))
            ->willReturn(new SavedDraftInfo('Drafts', 'Re: Already', 'alice@example.com'));

        $this->useCase($reader, $writer)->execute(10, 'Body');
    }

    private function useCase(MailboxReader $reader, DraftWriter $writer): SaveDraft
    {
        return new SaveDraft($reader, $writer, 'me@mail.ru', 'Me');
    }

    private function source(string $subject = 'Hello'): ReplySource
    {
        return new ReplySource(
            uid: 10,
            messageId: '<orig@example.com>',
            subject: $subject,
            references: '<prev@example.com>',
            recipient: new Recipient('alice@example.com', 'Alice'),
            quoteAuthorName: 'Alice',
            quoteDate: '12.10.2026 10:00',
            quoteBody: "Original line\nSecond line",
        );
    }
}
