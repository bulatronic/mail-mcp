<?php

declare(strict_types=1);

namespace MailMcp\Application\Draft;

final class ReplySubject
{
    public static function fromOriginal(string $subject): string
    {
        $subject = trim($subject);
        if ($subject !== '' && preg_match('/^Re:\s/i', $subject) === 1) {
            return $subject;
        }

        return 'Re: '.$subject;
    }
}
