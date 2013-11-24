<?php
namespace Trefoil\Tests\Helpers;

use Trefoil\Helpers\DropCaps;

use Trefoil\Helpers\TableExtra;

class DropCapsTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
    }

    public function tearDown()
    {
    }

    public function testHeadings()
    {
        $input = array(
                '<h1>H1 heading</h1>',
                '<p>First paragraph under H1</p>',
                '<h2>H2 heading</h2>',
                '<p>Other first paragraph under H2</p>',
                '<h3>H3 heading</h3>',
                '<p>Another First paragraph under H3</p>'
                );

        $expected = array(
                '<h1>H1 heading</h1>',
                '<p class="has-dropcaps"><span class="dropcaps">F</span>irst paragraph under H1</p>',
                '<h2>H2 heading</h2>',
                '<p>Other first paragraph under H2</p>',
                '<h3>H3 heading</h3>',
                '<p class="has-dropcaps"><span class="dropcaps">A</span>nother First paragraph under H3</p>'
                );


        $dropCaps = new DropCaps(implode("", $input), DropCaps::MODE_LETTER, 1);

        // must not change
        $dropCaps->createForFirstParagraph();
        $this->assertEquals(
                implode("", $input),
                $dropCaps->getOutput(),
                'Do not change first paragraph in text if preceding ');

        // must change
        $dropCaps->createForHeadings(array(1, 3));
        $this->assertEquals(
                implode("", $expected),
                $dropCaps->getOutput(),
                'Change only selected levels');
    }

    public function testNoHeading()
    {
        $input = array(
                '<p>First paragraph</p>',
                '<p>Second paragraph</p>',
                '<h2>H2 heading</h2>',
                '<p>Other first paragraph under H2</p>',
                '<h3>H3 heading</h3>',
                '<p>Another First paragraph under H3</p>'
                );

        $expected = array(
                '<p class="has-dropcaps"><span class="dropcaps">F</span>irst paragraph</p>',
                '<p>Second paragraph</p>',
                '<h2>H2 heading</h2>',
                '<p>Other first paragraph under H2</p>',
                '<h3>H3 heading</h3>',
                '<p>Another First paragraph under H3</p>'
                );

        $dropCaps = new DropCaps(implode("", $input), DropCaps::MODE_LETTER, 1);

        $dropCaps->createForFirstParagraph();
        $this->assertEquals(
                implode("", $expected),
                $dropCaps->getOutput(),
                'Change only first paragraph without heading');
    }

    public function testAlreadyHasMarkup()
    {
        $input = array(
                '<p class="has-dropcaps"><span class="dropcaps">- F</span>irst paragraph</p>',
                '<h2>H2 heading</h2>',
                '<p class="has-dropcaps"><span class="dropcaps">* O</span>Other first paragraph under H2</p>',
        );

        $dropCaps = new DropCaps(implode("", $input), DropCaps::MODE_LETTER, 1);

        // must not change
        $dropCaps->createForFirstParagraph();
        $this->assertEquals(
                implode("", $input),
                $dropCaps->getOutput(),
                'Do not change first paragraph in text if already has dropcaps markup');

        // must not change
        $dropCaps->createForHeadings(array(1, 2));
        $this->assertEquals(
                implode("", $input),
                $dropCaps->getOutput(),
                'Do not change first paragraph under headings if already has dropcaps markup');
    }

    public function testStartsWithHTMLEntity()
    {
        $input = array(
                '&nbsp;First paragraph </p>',
                '<h2>H2 heading</h2>',
                '<p>Other first paragraph under H2</p>',
        );

        $dropCaps = new DropCaps(implode("", $input), DropCaps::MODE_LETTER, 1);

        // must not change
        $dropCaps->createForFirstParagraph();
        $this->assertEquals(
                implode("", $input),
                $dropCaps->getOutput());
    }

    public function testStartsWithHTMLTag()
    {
        $input = array(
                '<p><em>First</em> paragraph </p>',
                '<h2>H2 heading</h2>',
                '<p>Other first paragraph under H2</p>',
        );

        $expected = array(
                '<p class="has-dropcaps"><em><span class="dropcaps">F</span>irst</em> paragraph </p>',
                '<h2>H2 heading</h2>',
                '<p>Other first paragraph under H2</p>',
        );

        $dropCaps = new DropCaps(implode("", $input), DropCaps::MODE_LETTER, 1);

        // must change
        $dropCaps->createForFirstParagraph();
        $this->assertEquals(
                implode("", $expected),
                $dropCaps->getOutput());
    }

    public function testWordMode()
    {
        $input = array(
                '<p>First (paragraph) in, the whole text.</p>',
                '<h2>H2 heading</h2>',
                '<p>Other first paragraph under H2</p>',
        );

        $expected = array(
                '<p class="has-dropcaps"><span class="dropcaps">First (paragraph) in,</span> the whole text.</p>',
                '<h2>H2 heading</h2>',
                '<p>Other first paragraph under H2</p>',
        );

        $dropCaps = new DropCaps(implode("", $input), DropCaps::MODE_WORD, 3);

        // must change
        $dropCaps->createForFirstParagraph();
        $this->assertEquals(
                implode("", $expected),
                $dropCaps->getOutput());
    }

    public function testWordModeStartWithEntity()
    {
        $input = array(
                '<p>&raquo;First (paragraph) in, the whole text.</p>',
                '<h2>H2 heading</h2>',
                '<p>Other first paragraph under H2</p>',
        );

        $expected = array(
                '<p class="has-dropcaps"><span class="dropcaps">&raquo;First (paragraph)</span> in, the whole text.</p>',
                '<h2>H2 heading</h2>',
                '<p>Other first paragraph under H2</p>',
        );

        $dropCaps = new DropCaps(implode("", $input), DropCaps::MODE_WORD, 3);

        // must change
        $dropCaps->createForFirstParagraph();
        $this->assertEquals(
                implode("", $expected),
                $dropCaps->getOutput());
    }

    public function testProcessManualMarkup()
    {
        $input = array(
                 '<p><span class="dropcaps">This</span> has dropcaps.</p>',
        );

        $expected = array(
                '<p class="has-dropcaps"><span class="dropcaps">This</span> has dropcaps.</p>',
        );

        $dropCaps = new DropCaps(implode("", $input));

        // must change
        $dropCaps->processManualMarkup();
        $this->assertEquals(
                implode("", $expected),
                $dropCaps->getOutput());
    }

    public function testMarkdownStyle()
    {
        $input = array(
                '<p>Normal paragraph.</p>',
                '<p>[[D]]rop caps paragraph.</p>',
                '<p>[[Another]] drop caps paragraph.</p>',
        );

        $expected = array(
                '<p>Normal paragraph.</p>',
                '<p class="has-dropcaps"><span class="dropcaps">D</span>rop caps paragraph.</p>',
                '<p class="has-dropcaps"><span class="dropcaps">Another</span> drop caps paragraph.</p>',
        );

        $dropCaps = new DropCaps(implode("", $input));

        // must change
        $dropCaps->createForMarkdownStyleMarkup();
        $this->assertEquals(
                implode("", $expected),
                $dropCaps->getOutput());
    }
}
