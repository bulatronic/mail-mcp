<?php

declare(strict_types=1);

namespace MailMcp\Mcp\Tool;

use MailMcp\Application\UseCase\FindReply;
use Mcp\Capability\Attribute\McpTool;

final readonly class FindReplyTool
{
    public function __construct(
        private FindReply $useCase,
    ) {}

    /**
     * Look in Sent for a reply whose In-Reply-To or References contains message_id.
     *
     * @return array{replied: bool, sent_uid: int|null, sent_date: string|null}
     */
    #[McpTool(name: 'find_reply')]
    public function findReply(string $message_id): array
    {
        $result = $this->useCase->execute($message_id);

        return [
            'replied' => $result->replied,
            'sent_uid' => $result->sentUid,
            'sent_date' => $result->sentDate,
        ];
    }
}
