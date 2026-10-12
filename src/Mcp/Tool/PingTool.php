<?php

declare(strict_types=1);

namespace MailMcp\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;

final class PingTool
{
    /**
     * Temporary health-check tool used while the mailbox tools are not ready.
     */
    #[McpTool(name: 'ping')]
    public function ping(): string
    {
        return 'ok';
    }
}
