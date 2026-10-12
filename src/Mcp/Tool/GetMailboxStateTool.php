<?php

declare(strict_types=1);

namespace MailMcp\Mcp\Tool;

use MailMcp\Application\UseCase\GetMailboxState;
use Mcp\Capability\Attribute\McpTool;

final readonly class GetMailboxStateTool
{
    public function __construct(
        private GetMailboxState $useCase,
    ) {}

    /**
     * Return INBOX UIDVALIDITY, last UID and total message count as a sync checkpoint.
     *
     * @return array{uidvalidity: int, last_uid: int, total_messages: int}
     */
    #[McpTool(name: 'get_mailbox_state')]
    public function getMailboxState(): array
    {
        $state = $this->useCase->execute();

        return [
            'uidvalidity' => $state->uidvalidity,
            'last_uid' => $state->lastUid,
            'total_messages' => $state->totalMessages,
        ];
    }
}
