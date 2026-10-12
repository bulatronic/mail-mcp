<?php

declare(strict_types=1);

namespace MailMcp\Domain\Message;

final readonly class AttachmentMeta
{
    public function __construct(
        public string $filename,
        public string $mime,
        public int $size,
    ) {}
}
