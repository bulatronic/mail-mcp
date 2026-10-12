<?php

declare(strict_types=1);

namespace MailMcp\Mcp\Tool;

use MailMcp\Application\UseCase\SaveDraft;
use Mcp\Capability\Attribute\McpTool;

final readonly class SaveDraftTool
{
    public function __construct(
        private SaveDraft $useCase,
    ) {}

    /**
     * Save a draft reply in Drafts for the given INBOX message UID.
     *
     * @return array{created: bool, folder: string, subject: string, to: string}
     */
    #[McpTool(name: 'save_draft')]
    public function saveDraft(int $reply_to_uid, string $body): array
    {
        $result = $this->useCase->execute($reply_to_uid, $body);

        return [
            'created' => $result->created,
            'folder' => $result->folder,
            'subject' => $result->subject,
            'to' => $result->to,
        ];
    }
}
