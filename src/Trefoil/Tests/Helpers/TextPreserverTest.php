<?php
declare(strict_types=1);
/*
 * This file is part of the trefoil application.
 *
 * (c) Miguel Angel Gabriel <magabriel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Trefoil\Helpers;
use PHPUnit\Framework\TestCase;

/**
 * Generated by PHPUnit_SkeletonGenerator 1.2.1 on 2013-12-31 at 09:17:37.
 */
class TextPreserverTest extends TestCase
{

    /**
     * @var TextPreserver
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void
    {
        $this->object = new TextPreserver;
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown(): void
    {

    }

    public function testNoChange(): void
    {
        $this->object->setText('Lorem ipsum');
        static::assertEquals('Lorem ipsum', $this->object->getText());
    }

    public function testPreserveMarkdownCodeBlocks(): void
    {
        $markdown = <<<TEXT
Lorem ipsum.

~~~
A fenced code block
~~~

Dolor sit amet `{@ an inline code block @}` lorem ipsum.

~~~.php
echo 'A PHP fenced code block';

echo 'With multiple lines';
~~~

~~~.html
{@ tabularlist_begin() @}
    
    ...the markdown list definition
    
{@ tabularlist_end() @}
~~~

~~~ .html
<div>An HTML code block with the language specifier separated from the fence</div>
~~~
            
TEXT;

        $this->object->setText($markdown);
        $this->object->preserveMarkdowmCodeBlocks();

        static::assertStringNotContainsString('~~~', $this->object->getText());
        static::assertStringNotContainsString('`', $this->object->getText());

        $this->object->restore();
        static::assertEquals($markdown, $this->object->getText());
    }

    public function testPreserveHtmlTags(): void
    {
        $html = '<div class="myclass">';
        $html .= '<a href="http://example.com/image.gif">Lorem ipsum</a>';
        $html .= '</div>';

        $this->object->setText($html);
        $this->object->preserveHtmlTags(['a']);

        static::assertStringNotContainsString('Lorem', $this->object->getText());
        static::assertStringNotContainsString('ipsum', $this->object->getText());

        $this->object->restore();
        static::assertEquals($html, $this->object->getText());
    }

    public function testPreserveHtmlTagsWithEmbeddedTags(): void
    {
        $html = '<div class="myclass">';
        $html .= '<a href="http://example.com/image.gif">Lorem <span>ipsum</span> dolor</a>';
        $html .= '</div>';

        $this->object->setText($html);
        $this->object->preserveHtmlTags(['a']);

        static::assertStringNotContainsString('Lorem', $this->object->getText());
        static::assertStringNotContainsString('ipsum', $this->object->getText());
        static::assertStringNotContainsString('dolor', $this->object->getText());
        static::assertStringNotContainsString('span', $this->object->getText());

        $this->object->restore();
        static::assertEquals($html, $this->object->getText());
    }

    public function testPreserveHtmlTagAttributes(): void
    {
        $html = '<div class="myclass">';
        $html .= '<a href="http://example.com/image.gif">Lorem ipsum</a>';
        $html .= '</div>';

        $this->object->setText($html);
        $this->object->preserveHtmlTagAttributes(['href', 'class']);

        static::assertNotEquals($html, $this->object->getText());

        $this->object->restore();
        static::assertEquals($html, $this->object->getText());
    }

    public function testCreatePlacehoder(): void
    {
        $value = 'myvalue';
        $placeholder = $this->object->internalCreatePlacehoder($value, 'prefix');

        $html = '<div class="myclass">' . $value . '</div>';
        $html2 = str_replace('myvalue', $placeholder, $html);

        $this->object->setText($html2);
        static::assertEquals($html2, $this->object->getText());

        $this->object->restore();
        static::assertEquals($html, $this->object->getText());
    }

}
