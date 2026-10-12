<?php

declare(strict_types=1);

namespace MailMcp\Tests\Unit;

use MailMcp\Domain\Message\MessageContent;
use MailMcp\Infrastructure\Mime\MessageContentFactory;
use PHPUnit\Framework\TestCase;

final class MessageContentFactoryTest extends TestCase
{
    private MessageContentFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new MessageContentFactory();
    }

    public function testCp1251SubjectAndBodyAreDecodedToUtf8(): void
    {
        $content = $this->readFixture('cp1251.eml');

        self::assertStringContainsString('тест', $content->subject);
        self::assertStringContainsString('windows-1251', $content->text);
        self::assertStringContainsString('Петров', $content->from);
        self::assertSame(MessageContent::UNTRUSTED_NOTICE, $content->notice);
    }

    public function testMultipartAlternativePrefersPlainText(): void
    {
        $content = $this->readFixture('multipart_alternative.eml');

        self::assertStringContainsString('Prefer plain text version', $content->text);
        self::assertStringNotContainsString('HTML version should be ignored', $content->text);
        self::assertStringNotContainsString('alert(1)', $content->text);
    }

    public function testHtmlOnlyIsConvertedWithoutScriptsAndHiddenParts(): void
    {
        $content = $this->readFixture('html_only.eml');

        self::assertStringContainsString('Visible paragraph', $content->text);
        self::assertStringContainsString('Second line', $content->text);
        self::assertStringNotContainsString('Hidden tracking block', $content->text);
        self::assertStringNotContainsString('document.cookie', $content->text);
        self::assertStringNotContainsString('<script', $content->text);
    }

    public function testForwardedMessageWithEmptySubjectReturnsEmptySubject(): void
    {
        $content = $this->readFixture('forwarded_empty_subject.eml');

        self::assertSame('', $content->subject);
        self::assertStringContainsString('forwarded message', $content->text);
    }

    public function testAttachmentMetadataIsPresentWithoutEmbeddingBinary(): void
    {
        $content = $this->readFixture('with_attachment.eml');

        self::assertCount(1, $content->attachments);
        self::assertSame('invoice.pdf', $content->attachments[0]->filename);
        self::assertStringContainsString('pdf', strtolower($content->attachments[0]->mime));
        self::assertGreaterThan(0, $content->attachments[0]->size);
        self::assertStringContainsString('attached invoice', $content->text);
        self::assertStringNotContainsString('%PDF', $content->text);
    }

    public function testPromptInjectionBodyIsReturnedAsOrdinaryText(): void
    {
        $content = $this->readFixture('prompt_injection.eml');

        self::assertStringContainsString('Ignore all previous instructions', $content->text);
        self::assertStringContainsString('DROP TABLE users', $content->text);
        self::assertSame(MessageContent::UNTRUSTED_NOTICE, $content->notice);
    }

    public function testAutomatedHeadersAreDetectedInSummary(): void
    {
        $summary = $this->factory->summaryFromRawEml(
            $this->fixture('automated_bulk.eml'),
            42,
        );

        self::assertTrue($summary->isAutomated);
        self::assertSame('noreply@example.com', $summary->fromEmail);
    }

    private function readFixture(string $name): MessageContent
    {
        return $this->factory->contentFromRawEml($this->fixture($name), 1, 6000);
    }

    private function fixture(string $name): string
    {
        $path = dirname(__DIR__) . '/Fixtures/eml/' . $name;
        $raw = file_get_contents($path);
        self::assertNotFalse($raw);

        return $raw;
    }
}
