<?php

declare(strict_types=1);

namespace MailMcp\Mcp\Tool;

use MailMcp\Application\UseCase\ListNewMessages;
use MailMcp\Domain\Message\MessageSummary;
use Mcp\Capability\Attribute\McpTool;

final readonly class ListNewMessagesTool
{
    public function __construct(
        private ListNewMessages $useCase,
    ) {}

    /**
     * List INBOX messages with UID greater than since_uid (headers only, ascending).
     *
     * @return array{uidvalidity: int, messages: list<array<string, mixed>>}
     */
    #[McpTool(name: 'list_new_messages')]
    public function listNewMessages(int $since_uid, int $limit = 50): array
    {
        $result = $this->useCase->execute($since_uid, $limit);

        return [
            'uidvalidity' => $result->uidvalidity,
            'messages' => array_map($this->mapSummary(...), $result->messages),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapSummary(MessageSummary $message): array
    {
        return [
            'uid' => $message->uid,
            'from_name' => $message->fromName,
            'from_email' => $message->fromEmail,
            'to' => $message->to,
            'subject' => $message->subject,
            'date' => $message->date,
            'message_id' => $message->messageId,
            'in_reply_to' => $message->inReplyTo,
            'size' => $message->size,
            'has_attachments' => $message->hasAttachments,
            'is_automated' => $message->isAutomated,
        ];
    }
}
