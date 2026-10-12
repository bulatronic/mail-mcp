<?php

declare(strict_types=1);

namespace MailMcp\Tests\Unit;

use MailMcp\Domain\Draft\Draft;
use MailMcp\Domain\Draft\Recipient;
use MailMcp\Infrastructure\Mime\DraftMimeBuilder;
use PHPUnit\Framework\TestCase;

final class DraftMimeBuilderTest extends TestCase
{
    public function testAssembledMessageHasRequiredHeadersAndPlainUtf8Body(): void
    {
        $raw = (new DraftMimeBuilder())->build(new Draft(
            to: new Recipient('alice@example.com', 'Alice'),
            fromEmail: 'me@mail.ru',
            fromName: 'Me',
            subject: 'Re: Hello',
            inReplyTo: '<orig@example.com>',
            references: '<prev@example.com> <orig@example.com>',
            bodyText: "Reply body\n\n12.10.2026 10:00, Alice написал(а):\n> Hi",
        ));

        self::assertMatchesRegularExpression('/^From:.*Me.*me@mail\.ru/mi', $raw);
        self::assertMatchesRegularExpression('/^To:.*Alice.*alice@example\.com/mi', $raw);
        self::assertMatchesRegularExpression('/^Subject:\s*Re: Hello/mi', $raw);
        self::assertMatchesRegularExpression('/^In-Reply-To:.*<orig@example\.com>/mi', $raw);
        self::assertMatchesRegularExpression('/^References:.*<prev@example\.com>.*<orig@example\.com>/mi', $raw);
        self::assertMatchesRegularExpression('/^X-Drafted-By:\s*mail-mcp/mi', $raw);
        self::assertDoesNotMatchRegularExpression('/^Cc:/mi', $raw);
        self::assertDoesNotMatchRegularExpression('/^Bcc:/mi', $raw);
        self::assertMatchesRegularExpression('/Content-Type:\s*text\/plain;\s*charset=utf-8/i', $raw);
        self::assertStringContainsString('Reply body', $raw);
        self::assertStringContainsString('12.10.2026 10:00, Alice', $raw);
        self::assertStringContainsString('> Hi', $raw);
        // Cyrillic quote marker is QP-encoded in the wire format.
        self::assertStringContainsString('=D0=BD=D0=B0=D0=BF=D0=B8=D1=81', $raw);
    }
}
