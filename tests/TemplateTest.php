<?php

declare(strict_types=1);

namespace Kurama\Tests;

use InvalidArgumentException;
use Kurama\Template;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TemplateTest extends TestCase
{
    private function template(): Template
    {
        return new Template(__DIR__ . '/fixtures/views');
    }

    public function testRendersTemplateWithData(): void
    {
        self::assertSame('Hello Kurama', trim($this->template()->render('hello', ['name' => 'Kurama'])));
    }

    public function testEscapesValues(): void
    {
        $output = $this->template()->render('hello', ['name' => '<b>&']);

        self::assertSame('Hello &lt;b&gt;&amp;', trim($output));
    }

    public function testDotNotationRendersPartial(): void
    {
        $output = $this->template()->render('with-partial', ['label' => 'new']);

        self::assertSame('Teaser: [new]', trim($output));
    }

    public function testExists(): void
    {
        $template = $this->template();

        self::assertTrue($template->exists('hello'));
        self::assertFalse($template->exists('missing'));
    }

    public function testMissingTemplateThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->template()->render('missing');
    }

    public function testPathTraversalIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->template()->render('../secret');
    }

    public function testEscapeAlias(): void
    {
        self::assertSame('&lt;x&gt;', $this->template()->e('<x>'));
    }
}
