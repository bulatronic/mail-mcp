<?php

declare(strict_types=1);

namespace MailMcp\Mcp\Audit;

/**
 * Writes one JSON audit line per tool call to stdout.
 * Never logs passwords, subjects or message bodies.
 */
final class StdoutJsonAuditLogger
{
    /** @var resource */
    private $stream;

    /**
     * @param resource|null $stream
     */
    public function __construct(mixed $stream = null)
    {
        if ($stream === null) {
            $stream = \STDOUT;
        }
        if (!is_resource($stream)) {
            throw new \InvalidArgumentException('Audit logger stream must be a resource');
        }

        $this->stream = $stream;
    }

    public function log(
        string $tool,
        ?int $uid,
        string $status,
        int $durationMs,
        ?string $time = null,
    ): void {
        $payload = [
            'time' => $time ?? gmdate('c'),
            'tool' => $tool,
            'uid' => $uid,
            'status' => $status,
            'duration_ms' => $durationMs,
        ];

        $line = json_encode($payload, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        if ($line === false) {
            return;
        }

        fwrite($this->stream, $line . "\n");
    }
}
