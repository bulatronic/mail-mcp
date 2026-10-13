<?php

declare(strict_types=1);

namespace MailMcp\Application\Draft;

final class ReplySubject
{
    public static function fromOriginal(string $subject): string
    {
        $subject = trim($subject);
        // Keep existing reply markers: "Re:", "RE:", "Re[2]:", etc.
        if ($subject !== '' && preg_match('/^Re(?:\[\d+\])?:\s/i', $subject) === 1) {
            return $subject;
        }

        return 'Re: ' . $subject;
    }
}
