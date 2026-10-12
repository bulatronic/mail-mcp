<?php

declare(strict_types=1);

namespace MailMcp\Domain\Draft;

final readonly class SaveDraftResult
{
    public function __construct(
        public bool $created,
        public string $folder,
        public string $subject,
        public string $to,
    ) {
    }

    public static function fromExisting(SavedDraftInfo $info): self
    {
        return new self(false, $info->folder, $info->subject, $info->to);
    }

    public static function created(SavedDraftInfo $info): self
    {
        return new self(true, $info->folder, $info->subject, $info->to);
    }
}
