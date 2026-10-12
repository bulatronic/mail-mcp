<?php

declare(strict_types=1);

namespace MailMcp\Tests;

use MailMcp\Mcp\Tool\PingTool;
use PHPUnit\Framework\TestCase;

final class PingToolTest extends TestCase
{
    public function testPingReturnsOk(): void
    {
        $tool = new PingTool();

        self::assertSame('ok', $tool->ping());
    }
}
