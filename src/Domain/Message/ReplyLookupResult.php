<?php

declare(strict_types=1);

namespace MailMcp\Domain\Message;

final readonly class ReplyLookupResult
{
    public function __construct(
        public bool $replied,
        public ?int $sentUid,
        public ?string $sentDate,
    ) {}

    public static function notFound(): self
    {
        return new self(false, null, null);
    }
}
