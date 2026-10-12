<?php

declare(strict_types=1);

namespace MailMcp\Tests\Unit;

use MailMcp\Domain\Error\DraftError;
use MailMcp\Mcp\Audit\StdoutJsonAuditLogger;
use MailMcp\Mcp\GuardedReferenceHandler;
use MailMcp\Mcp\RateLimit\FileRateLimiter;
use Mcp\Capability\Registry\ReferenceHandlerInterface;
use Mcp\Capability\Registry\ToolReference;
use Mcp\Exception\RegistryException;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\Tool;
use PHPUnit\Framework\TestCase;

final class GuardedReferenceHandlerTest extends TestCase
{
    private string $ratePath;
    /** @var resource */
    private $auditStream;

    protected function setUp(): void
    {
        $this->ratePath = sys_get_temp_dir() . '/mail-mcp-guard-rate-' . uniqid('', true) . '.json';
        $stream = fopen('php://memory', 'r+');
        self::assertNotFalse($stream);
        $this->auditStream = $stream;
    }

    protected function tearDown(): void
    {
        if (is_file($this->ratePath)) {
            unlink($this->ratePath);
        }
        fclose($this->auditStream);
    }

    /**
     * @throws RegistryException
     * @throws \JsonException
     */
    public function testLogsSuccessWithoutSecrets(): void
    {
        $inner = $this->createMock(ReferenceHandlerInterface::class);
        $inner->method('handle')->willReturn(['ok' => true]);

        $handler = $this->handler($inner);
        $result = $handler->handle($this->toolRef('read_message'), [
            'uid' => 42,
            'subject' => 'SECRET SUBJECT',
            'body' => 'SECRET BODY',
        ]);

        self::assertSame(['ok' => true], $result);
        $line = $this->readAuditLine();
        self::assertSame('read_message', $line['tool']);
        self::assertSame(42, $line['uid']);
        self::assertSame('ok', $line['status']);
        self::assertArrayHasKey('duration_ms', $line);
        self::assertStringNotContainsString('SECRET', json_encode($line) ?: '');
    }

    /**
     * @throws RegistryException
     * @throws \JsonException
     */
    public function testConvertsDomainErrorToToolCallExceptionWithoutStack(): void
    {
        $inner = $this->createMock(ReferenceHandlerInterface::class);
        $inner->method('handle')->willThrowException(new DraftError('body length must be between 1 and 10000 characters'));

        $handler = $this->handler($inner);

        try {
            $handler->handle($this->toolRef('save_draft'), ['reply_to_uid' => 7, 'body' => '']);
            self::fail('Expected ToolCallException');
        } catch (ToolCallException $e) {
            self::assertSame('body length must be between 1 and 10000 characters', $e->getMessage());
            self::assertStringNotContainsString('GuardedReferenceHandlerTest.php', $e->getMessage());
            self::assertStringNotContainsString("\n", $e->getMessage());
        }

        $line = $this->readAuditLine();
        self::assertSame('error', $line['status']);
        self::assertSame(7, $line['uid']);
    }

    /**
     * @throws RegistryException
     */
    public function testHidesInternalExceptionDetails(): void
    {
        $inner = $this->createMock(ReferenceHandlerInterface::class);
        $inner->method('handle')->willThrowException(new \RuntimeException('password=super-secret IMAP boom'));

        $handler = $this->handler($inner);

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Internal error while executing tool');
        $handler->handle($this->toolRef('get_mailbox_state'), []);
    }

    /**
     * @throws RegistryException
     */
    public function testRateLimitsToolCalls(): void
    {
        $inner = $this->createMock(ReferenceHandlerInterface::class);
        $inner->expects(self::once())->method('handle')->willReturn(['ok' => true]);

        $handler = new GuardedReferenceHandler(
            $inner,
            new FileRateLimiter($this->ratePath, maxCalls: 1, windowSeconds: 60),
            new StdoutJsonAuditLogger($this->auditStream),
        );

        $handler->handle($this->toolRef('find_reply'), ['message_id' => '<x@y>']);

        try {
            $handler->handle($this->toolRef('find_reply'), ['message_id' => '<x@y>']);
            self::fail('Expected rate limit ToolCallException');
        } catch (ToolCallException $e) {
            self::assertStringContainsString('Rate limit exceeded', $e->getMessage());
        }

        rewind($this->auditStream);
        $raw = stream_get_contents($this->auditStream) ?: '';
        self::assertStringContainsString('"status":"rate_limited"', $raw);
    }

    private function handler(ReferenceHandlerInterface $inner): GuardedReferenceHandler
    {
        return new GuardedReferenceHandler(
            $inner,
            new FileRateLimiter($this->ratePath, maxCalls: 60, windowSeconds: 60),
            new StdoutJsonAuditLogger($this->auditStream),
        );
    }

    private function toolRef(string $name): ToolReference
    {
        return new ToolReference(
            new Tool(
                name: $name,
                title: null,
                inputSchema: [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                    'required' => null,
                ],
                description: null,
                annotations: null,
            ),
            [self::class, 'dummy'],
        );
    }

    public static function dummy(): array
    {
        return [];
    }

    /**
     * @return array{time: string, tool: string, uid: int|null, status: string, duration_ms: int}
     * @throws \JsonException
     */
    private function readAuditLine(): array
    {
        rewind($this->auditStream);
        $raw = stream_get_contents($this->auditStream);
        self::assertNotFalse($raw);
        $line = trim($raw);
        self::assertNotSame('', $line);

        /** @var array{time: string, tool: string, uid: int|null, status: string, duration_ms: int} $decoded */
        $decoded = json_decode($line, true, 512, \JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
