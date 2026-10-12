<?php

declare(strict_types=1);

namespace MailMcp\Domain\Draft;

final readonly class SavedDraftInfo
{
    public function __construct(
        public string $folder,
        public string $subject,
        public string $to,
    ) {
    }
}
