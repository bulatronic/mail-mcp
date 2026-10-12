<?php

declare(strict_types=1);

namespace MailMcp\Mcp;

use Mcp\Capability\Registry\ElementReference;
use Mcp\Capability\Registry\ReferenceHandlerInterface;
use Mcp\Capability\Registry\ToolReference;
use Mcp\Exception\RegistryException;
use Mcp\Exception\ToolCallException;
use MailMcp\Domain\Error\DraftError;
use MailMcp\Domain\Error\MailboxError;
use MailMcp\Mcp\Audit\StdoutJsonAuditLogger;
use MailMcp\Mcp\RateLimit\FileRateLimiter;
use Throwable;

/**
 * Wraps tool execution with rate limiting, audit logging and safe MCP errors.
 */
final readonly class GuardedReferenceHandler implements ReferenceHandlerInterface
{
    public function __construct(
        private ReferenceHandlerInterface $inner,
        private FileRateLimiter           $rateLimiter,
        private StdoutJsonAuditLogger     $auditLogger,
    ) {
    }

    /**
     * @param array<string, mixed> $arguments
     * @throws RegistryException
     */
    public function handle(ElementReference $reference, array $arguments): mixed
    {
        if (!$reference instanceof ToolReference) {
            return $this->inner->handle($reference, $arguments);
        }

        $tool = $reference->tool->name;
        $uid = $this->extractUid($arguments);
        $started = hrtime(true);

        if (!$this->rateLimiter->attempt()) {
            $this->auditLogger->log($tool, $uid, 'rate_limited', $this->elapsedMs($started));
            throw new ToolCallException('Rate limit exceeded: max 60 tool calls per minute');
        }

        try {
            $result = $this->inner->handle($reference, $arguments);
            $this->auditLogger->log($tool, $uid, 'ok', $this->elapsedMs($started));

            return $result;
        } catch (ToolCallException $e) {
            $this->auditLogger->log($tool, $uid, 'error', $this->elapsedMs($started));
            throw new ToolCallException($this->publicMessage($e), 0, $e);
        } catch (DraftError|MailboxError|\InvalidArgumentException $e) {
            $this->auditLogger->log($tool, $uid, 'error', $this->elapsedMs($started));
            throw new ToolCallException($this->publicMessage($e), 0, $e);
        } catch (Throwable $e) {
            $this->auditLogger->log($tool, $uid, 'error', $this->elapsedMs($started));
            throw new ToolCallException('Internal error while executing tool', 0, $e);
        }
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function extractUid(array $arguments): ?int
    {
        foreach (['uid', 'reply_to_uid'] as $key) {
            if (!array_key_exists($key, $arguments)) {
                continue;
            }

            $value = $arguments[$key];
            if (is_int($value)) {
                return $value;
            }
            if (is_string($value) && ctype_digit($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    private function elapsedMs(int $startedHrtime): int
    {
        return (int) max(0, (hrtime(true) - $startedHrtime) / 1_000_000);
    }

    private function publicMessage(Throwable $e): string
    {
        $message = trim($e->getMessage());
        if ($message === '') {
            return 'Tool execution failed';
        }

        // Never leak credentials or multiline stack-like dumps.
        $message = preg_replace('/\bMAIL_APP_PASSWORD\b|\bpassword\b[=:]\s*\S+/i', '[redacted]', $message) ?? $message;
        $message = str_replace(["\r", "\n", "\t"], ' ', $message);
        if (strlen($message) > 300) {
            $message = substr($message, 0, 297).'...';
        }

        return $message;
    }
}
