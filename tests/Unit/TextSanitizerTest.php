<?php

declare(strict_types=1);

namespace MailMcp\Tests\Unit;

use MailMcp\Infrastructure\Mime\TextSanitizer;
use PHPUnit\Framework\TestCase;

final class TextSanitizerTest extends TestCase
{
    public function testRemovesInvisibleUnicodeFromBody(): void
    {
        $raw = "Hello\u{200B}World\u{FEFF}!\u{202E}ltr";

        $clean = TextSanitizer::sanitize($raw);

        self::assertSame('HelloWorld!ltr', $clean);
    }

    public function testRemovesUnicodeTagsBlock(): void
    {
        // U+E0065 U+E0076 U+E0069 U+E006C = Tags spelling "evil" (plus cancel tag).
        $raw = "Visible\u{E0065}\u{E0076}\u{E0069}\u{E006C}\u{E007F} text";

        $clean = TextSanitizer::sanitize($raw);

        self::assertSame('Visible text', $clean);
        self::assertStringNotContainsString("\u{E0065}", $clean);
    }

    public function testRemovesInvisibleCharsFromSubjectLikeStrings(): void
    {
        $subject = "Re:\u{200C} Meeting\u{2066} hidden\u{2069}";

        self::assertSame('Re: Meeting hidden', TextSanitizer::sanitize($subject));
    }

    public function testScrubsInvalidUtf8BeforePreg(): void
    {
        $invalid = "ok\xBFmore";

        $clean = TextSanitizer::sanitize($invalid);

        self::assertStringContainsString('ok', $clean);
        self::assertStringContainsString('more', $clean);
    }

    public function testStripsControlCharactersAndCollapsesBlankLines(): void
    {
        $raw = "a\x00b\n\n\n\nc";

        self::assertSame("ab\n\nc", TextSanitizer::sanitize($raw));
    }
}
