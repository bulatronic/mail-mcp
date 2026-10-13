<?php

declare(strict_types=1);

namespace MailMcp\Infrastructure\Mime;

final class TextSanitizer
{
    public static function sanitize(string $text): string
    {
        // preg_replace(/u) returns null on invalid UTF-8 — scrub first.
        $text = mb_scrub($text, 'UTF-8');

        // C0 controls (except TAB/LF/CR) and DEL.
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';

        // Invisible / bidi / tag / variation Unicode used for prompt-injection hiding:
        // U+200B–U+200F (incl. ZWJ U+200D), U+202A–U+202E, U+2060–U+206F,
        // U+FEFF, Tags U+E0000–U+E007F, variation selectors U+FE00–U+FE0F and
        // U+E0100–U+E01EF.
        // Removing U+200D / U+FE0F can change how composite emoji render; accepted
        // deliberately to block hidden-payload smuggling via variation sequences.
        $text = preg_replace(
            '/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{206F}\x{FEFF}\x{FE00}-\x{FE0F}\x{E0000}-\x{E007F}\x{E0100}-\x{E01EF}]/u',
            '',
            $text,
        ) ?? '';

        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * @return array{0: string, 1: bool}
     */
    public static function truncate(string $text, int $maxChars): array
    {
        if (mb_strlen($text) <= $maxChars) {
            return [$text, false];
        }

        return [mb_substr($text, 0, $maxChars), true];
    }
}
