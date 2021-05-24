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
namespace Trefoil\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use Trefoil\Helpers\DropCaps;

/**
 * Class DropCapsTest
 *
 * @package Trefoil\Tests\Helpers
 */
class DropCapsTest extends TestCase
{
    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }

    public function testHeadings(): void
    {
        $input = [
            '<h1>H1 heading</h1>',
            '<p>First paragraph under H1</p>',
            '<h2>H2 heading</h2>',
            '<p>Other first paragraph under H2</p>',
            '<h3>H3 heading</h3>',
            '<p>Another First paragraph under H3</p>'
        ];

        $expected = [
            '<h1>H1 heading</h1>',
            '<p class="has-dropcaps"><span class="dropcaps">F</span>irst paragraph under H1</p>',
            '<h2>H2 heading</h2>',
            '<p>Other first paragraph under H2</p>',
            '<h3>H3 heading</h3>',
            '<p class="has-dropcaps"><span class="dropcaps">A</span>nother First paragraph under H3</p>'
        ];


        $dropCaps = new DropCaps(implode('', $input), DropCaps::MODE_LETTER, 1);

        // must not change
        $dropCaps->createForFirstParagraph();
        static::assertEquals(
             implode('', $input),
             $dropCaps->getOutput(),
             'Do not change first paragraph in text if preceding '
        );

        // must change
        $dropCaps->createForHeadings([1, 3]);
        static::assertEquals(
             implode('', $expected),
             $dropCaps->getOutput(),
             'Change only selected levels'
        );
    }

    public function testNoHeading(): void
    {
        $input = [
            '<p>First paragraph</p>',
            '<p>Second paragraph</p>',
            '<h2>H2 heading</h2>',
            '<p>Other first paragraph under H2</p>',
            '<h3>H3 heading</h3>',
            '<p>Another First paragraph under H3</p>'
        ];

        $expected = [
            '<p class="has-dropcaps"><span class="dropcaps">F</span>irst paragraph</p>',
            '<p>Second paragraph</p>',
            '<h2>H2 heading</h2>',
            '<p>Other first paragraph under H2</p>',
            '<h3>H3 heading</h3>',
            '<p>Another First paragraph under H3</p>'
        ];

        $dropCaps = new DropCaps(implode('', $input), DropCaps::MODE_LETTER, 1);

        $dropCaps->createForFirstParagraph();
        static::assertEquals(
             implode('', $expected),
             $dropCaps->getOutput(),
             'Change only first paragraph without heading'
        );
    }

    public function testAlreadyHasMarkup(): void
    {
        $input = [
            '<p class="has-dropcaps"><span class="dropcaps">- F</span>irst paragraph</p>',
            '<h2>H2 heading</h2>',
            '<p class="has-dropcaps"><span class="dropcaps">* O</span>Other first paragraph under H2</p>',
        ];

        $dropCaps = new DropCaps(implode('', $input), DropCaps::MODE_LETTER, 1);

        // must not change
        $dropCaps->createForFirstParagraph();
        static::assertEquals(
             implode('', $input),
             $dropCaps->getOutput(),
             'Do not change first paragraph in text if already has dropcaps markup'
        );

        // must not change
        $dropCaps->createForHeadings();
        static::assertEquals(
             implode('', $input),
             $dropCaps->getOutput(),
             'Do not change first paragraph under headings if already has dropcaps markup'
        );
    }

    public function testStartsWithHTMLEntity(): void
    {
        $input = [
            '&nbsp;First paragraph </p>',
            '<h2>H2 heading</h2>',
            '<p>Other first paragraph under H2</p>',
        ];

        $dropCaps = new DropCaps(implode('', $input), DropCaps::MODE_LETTER, 1);

        // must not change
        $dropCaps->createForFirstParagraph();
        static::assertEquals(
             implode('', $input),
             $dropCaps->getOutput()
        );
    }

    public function testStartsWithHTMLTag(): void
    {
        $input = [
            '<p><em>First</em> paragraph </p>',
            '<h2>H2 heading</h2>',
            '<p>Other first paragraph under H2</p>',
        ];

        $expected = [
            '<p class="has-dropcaps"><em><span class="dropcaps">F</span>irst</em> paragraph </p>',
            '<h2>H2 heading</h2>',
            '<p>Other first paragraph under H2</p>',
        ];

        $dropCaps = new DropCaps(implode('', $input), DropCaps::MODE_LETTER, 1);

        // must change
        $dropCaps->createForFirstParagraph();
        static::assertEquals(
             implode('', $expected),
             $dropCaps->getOutput()
        );
    }

    public function testWordMode(): void
    {
        $input = [
            '<p>First (paragraph) in, the whole text.</p>',
            '<h2>H2 heading</h2>',
            '<p>Other first paragraph under H2</p>',
        ];

        $expected = [
            '<p class="has-dropcaps"><span class="dropcaps">First (paragraph) in,</span> the whole text.</p>',
            '<h2>H2 heading</h2>',
            '<p>Other first paragraph under H2</p>',
        ];

        $dropCaps = new DropCaps(implode('', $input), DropCaps::MODE_WORD, 3);

        // must change
        $dropCaps->createForFirstParagraph();
        static::assertEquals(
             implode('', $expected),
             $dropCaps->getOutput()
        );
    }

    public function testWordModeStartWithEntity(): void
    {
        $input = [
            '<p>&raquo;First (paragraph) in, the whole text.</p>',
            '<h2>H2 heading</h2>',
            '<p>Other first paragraph under H2</p>',
        ];

        $expected = [
            '<p class="has-dropcaps"><span class="dropcaps">&raquo;First (paragraph)</span> in, the whole text.</p>',
            '<h2>H2 heading</h2>',
            '<p>Other first paragraph under H2</p>',
        ];

        $dropCaps = new DropCaps(implode('', $input), DropCaps::MODE_WORD, 3);

        // must change
        $dropCaps->createForFirstParagraph();
        static::assertEquals(
             implode('', $expected),
             $dropCaps->getOutput()
        );
    }

    public function testProcessManualMarkup(): void
    {
        $input = [
            '<p><span class="dropcaps">This</span> has dropcaps.</p>',
        ];

        $expected = [
            '<p class="has-dropcaps"><span class="dropcaps">This</span> has dropcaps.</p>',
        ];

        $dropCaps = new DropCaps(implode('', $input));

        // must change
        $dropCaps->processManualMarkup();
        static::assertEquals(
             implode('', $expected),
             $dropCaps->getOutput()
        );
    }

    public function testMarkdownStyle(): void
    {
        $input = [
            '<p>Normal paragraph.</p>',
            '<p>[[D]]rop caps paragraph.</p>',
            '<p>[[Another]] drop caps paragraph.</p>',
        ];

        $expected = [
            '<p>Normal paragraph.</p>',
            '<p class="has-dropcaps"><span class="dropcaps">D</span>rop caps paragraph.</p>',
            '<p class="has-dropcaps"><span class="dropcaps">Another</span> drop caps paragraph.</p>',
        ];

        $dropCaps = new DropCaps(implode('', $input));

        // must change
        $dropCaps->createForMarkdownStyleMarkup();
        static::assertEquals(
             implode('', $expected),
             $dropCaps->getOutput()
        );
    }
}
