<?php

declare(strict_types=1);

namespace MailMcp\Tests\Unit;

use MailMcp\Application\Draft\ReplySubject;
use PHPUnit\Framework\TestCase;

final class ReplySubjectTest extends TestCase
{
    public function testAddsRePrefix(): void
    {
        self::assertSame('Re: Hello', ReplySubject::fromOriginal('Hello'));
    }

    public function testKeepsExistingRePrefix(): void
    {
        self::assertSame('Re: Hello', ReplySubject::fromOriginal('Re: Hello'));
        self::assertSame('re: Hello', ReplySubject::fromOriginal('re: Hello'));
    }

    public function testKeepsUppercaseRePrefix(): void
    {
        self::assertSame('RE: Hello', ReplySubject::fromOriginal('RE: Hello'));
    }

    public function testKeepsNumberedRePrefix(): void
    {
        self::assertSame('Re[2]: Hello', ReplySubject::fromOriginal('Re[2]: Hello'));
    }

    public function testPrefixesForwardedSubject(): void
    {
        self::assertSame('Re: Fwd: Hello', ReplySubject::fromOriginal('Fwd: Hello'));
    }
}
