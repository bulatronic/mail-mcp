<?php

declare(strict_types=1);

namespace MailMcp\Tests\Unit;

use MailMcp\Infrastructure\Mime\AutomatedMessageDetector;
use PHPUnit\Framework\TestCase;

final class AutomatedMessageDetectorTest extends TestCase
{
    private AutomatedMessageDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new AutomatedMessageDetector();
    }

    public function testListUnsubscribeMarksAutomated(): void
    {
        self::assertTrue($this->detector->isAutomated('a@b.c', '<mailto:x>', null, null));
    }

    public function testPrecedenceBulkMarksAutomated(): void
    {
        self::assertTrue($this->detector->isAutomated('a@b.c', null, 'bulk', null));
        self::assertTrue($this->detector->isAutomated('a@b.c', null, 'list', null));
        self::assertTrue($this->detector->isAutomated('a@b.c', null, 'junk', null));
    }

    public function testAutoSubmittedNotNoMarksAutomated(): void
    {
        self::assertTrue($this->detector->isAutomated('a@b.c', null, null, 'auto-generated'));
        self::assertFalse($this->detector->isAutomated('a@b.c', null, null, 'no'));
    }

    public function testNoreplyLocalPartMarksAutomated(): void
    {
        self::assertTrue($this->detector->isAutomated('noreply@example.com', null, null, null));
        self::assertTrue($this->detector->isAutomated('no-reply@example.com', null, null, null));
        self::assertFalse($this->detector->isAutomated('alice@example.com', null, null, null));
    }
}
