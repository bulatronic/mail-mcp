<?php

declare(strict_types=1);

namespace MailMcp\Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Fails when src/ contains forbidden mutating IMAP ops or send-mail dependencies.
 */
final class ForbiddenOperationsTest extends TestCase
{
    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function forbiddenPatterns(): array
    {
        return [
            ['/\bexpunge\s*\(/i', 'expunge('],
            ['/->\s*delete\s*\(/i', '->delete('],
            ['/->\s*move\s*\(/i', '->move('],
            ['/->\s*copy\s*\(/i', '->copy('],
            ['/\bsetFlag\s*\(/i', 'setFlag('],
            ['/\bremoveFlag\s*\(/i', 'removeFlag('],
            ['/\bmarkAsRead\s*\(/i', 'markAsRead('],
            ['/\bmarkAsSeen\s*\(/i', 'markAsSeen('],
            ['/\bmarkSeen\s*\(/i', 'markSeen('],
            ['/\bmarkUnseen\s*\(/i', 'markUnseen('],
            ['/\bSTORE\b/', 'STORE'],
            ['/\bsmtp\b/i', 'smtp'],
            ['/\bPHPMailer\b/i', 'PHPMailer'],
            ['/Symfony\\\\Component\\\\Mailer/i', 'Symfony Mailer'],
            ['/\\\\Mailer\\\\Transport\b/i', 'Mailer Transport'],
            ['/(?<![A-Za-z0-9_\\\\])mail\s*\(/i', 'mail('],
        ];
    }

    public function testSrcContainsNoForbiddenOperationsOrDependencies(): void
    {
        $root = dirname(__DIR__, 2).'/src';
        self::assertDirectoryExists($root);

        $violations = [];

        /** @var SplFileInfo $file */
        foreach ($this->phpFiles($root) as $file) {
            $path = $file->getPathname();
            $relative = substr($path, strlen(dirname($root)) + 1);
            $contents = file_get_contents($path);
            self::assertNotFalse($contents);

            foreach (self::forbiddenPatterns() as [$pattern, $label]) {
                if (preg_match($pattern, $contents) === 1) {
                    $violations[] = sprintf('%s → %s', $relative, $label);
                }
            }
        }

        self::assertSame(
            [],
            $violations,
            "Forbidden operations/dependencies found in src/:\n".implode("\n", $violations),
        );
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function phpFiles(string $root): iterable
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                yield $file;
            }
        }
    }
}
