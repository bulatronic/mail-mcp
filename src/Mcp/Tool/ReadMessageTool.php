<?php

declare(strict_types=1);

namespace MailMcp\Mcp\Tool;

use MailMcp\Application\UseCase\ReadMessage;
use MailMcp\Domain\Message\AttachmentMeta;
use Mcp\Capability\Attribute\McpTool;

final readonly class ReadMessageTool
{
    public function __construct(
        private ReadMessage $useCase,
    ) {}

    /**
     * Read one INBOX message body as safe plain text; attachment metadata only.
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'read_message')]
    public function readMessage(int $uid, int $max_chars = 6000): array
    {
        $content = $this->useCase->execute($uid, $max_chars);

        return [
            'uid' => $content->uid,
            'from' => $content->from,
            'to' => $content->to,
            'cc' => $content->cc,
            'subject' => $content->subject,
            'date' => $content->date,
            'text' => $content->text,
            'truncated' => $content->truncated,
            'attachments' => array_map($this->mapAttachment(...), $content->attachments),
            'notice' => $content->notice,
        ];
    }

    /**
     * @return array{filename: string, mime: string, size: int}
     */
    private function mapAttachment(AttachmentMeta $attachment): array
    {
        return [
            'filename' => $attachment->filename,
            'mime' => $attachment->mime,
            'size' => $attachment->size,
        ];
    }
}
