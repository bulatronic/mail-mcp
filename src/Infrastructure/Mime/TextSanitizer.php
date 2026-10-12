<?php

declare(strict_types=1);

namespace MailMcp\Infrastructure\Mime;

final class TextSanitizer
{
    public static function sanitize(string $text): string
    {
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;
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
