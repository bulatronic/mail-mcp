<?php

declare(strict_types=1);

namespace MailMcp\Application\Draft;

use MailMcp\Domain\Message\ReplySource;

final class ReplyQuote
{
    private const int MAX_LINES = 10;

    public static function append(string $body, ReplySource $source): string
    {
        $lines = preg_split("/\r\n|\n|\r/", $source->quoteBody) ?: [];
        $lines = array_slice($lines, 0, self::MAX_LINES);

        $quoted = array_map(static fn(string $line): string => '> ' . $line, $lines);
        $author = trim($source->quoteAuthorName) !== '' ? $source->quoteAuthorName : $source->recipient->email;
        $date = trim($source->quoteDate) !== '' ? $source->quoteDate : 'unknown date';

        $header = sprintf('%s, %s написал(а):', $date, $author);

        return rtrim($body) . "\n\n" . $header . "\n" . implode("\n", $quoted);
    }
}
