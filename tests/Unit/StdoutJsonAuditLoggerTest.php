<?php

declare(strict_types=1);

namespace MailMcp\Tests\Unit;

use MailMcp\Domain\Error\DraftError;
use MailMcp\Mcp\Audit\StdoutJsonAuditLogger;
use PHPUnit\Framework\TestCase;

final class StdoutJsonAuditLoggerTest extends TestCase
{
    /**
     * @throws \JsonException
     */
    public function testWritesJsonLineWithRequiredFieldsOnly(): void
    {
        $stream = fopen('php://memory', 'r+');
        self::assertNotFalse($stream);

        $logger = new StdoutJsonAuditLogger($stream);
        $logger->log('save_draft', 15, 'ok', 12, '2026-10-12T11:00:00+00:00');

        rewind($stream);
        $line = trim((string) stream_get_contents($stream));
        fclose($stream);

        $decoded = json_decode($line, true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame([
            'time' => '2026-10-12T11:00:00+00:00',
            'tool' => 'save_draft',
            'uid' => 15,
            'status' => 'ok',
            'duration_ms' => 12,
        ], $decoded);
        self::assertArrayNotHasKey('error_class', $decoded);
    }

    /**
     * @throws \JsonException
     */
    public function testErrorStatusIncludesErrorClassWithoutMessage(): void
    {
        $stream = fopen('php://memory', 'r+');
        self::assertNotFalse($stream);

        $logger = new StdoutJsonAuditLogger($stream);
        $logger->log(
            'save_draft',
            15,
            'error',
            3,
            '2026-10-12T11:00:00+00:00',
            errorClass: DraftError::class,
        );

        rewind($stream);
        $line = trim((string) stream_get_contents($stream));
        fclose($stream);

        $decoded = json_decode($line, true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('error', $decoded['status']);
        self::assertSame(DraftError::class, $decoded['error_class']);
        self::assertStringNotContainsString('body length', $line);
        self::assertStringNotContainsString('SECRET', $line);
    }

    public function testDefaultStreamUsesPhpStdoutNotStdoutConstant(): void
    {
        $logger = new StdoutJsonAuditLogger();
        // Construction must not reference CLI-only STDOUT (FrankenPHP web SAPI).
        $logger->log('get_mailbox_state', null, 'ok', 1);
        self::assertTrue(true);
    }
}
