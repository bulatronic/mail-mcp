<?php

declare(strict_types=1);

namespace MailMcp\Infrastructure\Mime;

final class AutomatedMessageDetector
{
    public function isAutomated(
        ?string $fromEmail,
        ?string $listUnsubscribe,
        ?string $precedence,
        ?string $autoSubmitted,
    ): bool {
        if ($listUnsubscribe !== null && trim($listUnsubscribe) !== '') {
            return true;
        }

        $precedence = strtolower(trim((string) $precedence));
        if (in_array($precedence, ['bulk', 'list', 'junk'], true)) {
            return true;
        }

        $autoSubmitted = strtolower(trim((string) $autoSubmitted));
        if ($autoSubmitted !== '' && $autoSubmitted !== 'no') {
            return true;
        }

        $email = strtolower(trim((string) $fromEmail));
        if ($email === '') {
            return false;
        }

        $local = explode('@', $email, 2)[0];

        return (bool) preg_match('/^(noreply|no-reply|donotreply|do-not-reply|mailer-daemon|postmaster)([+._-].*)?$/i', $local);
    }
}
