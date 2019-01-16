<?php
declare(strict_types=1);
/**
 * Created by PhpStorm.
 * User: miguelangel
 * Date: 26/12/18
 * Time: 9:05
 */

namespace Trefoil\Helpers;

use PHPUnit\Framework\TestCase;


/**
 * Class TrefoilMarkerProcessorTest
 *
 * @package Trefoil\Helpers
 */
class TrefoilMarkerProcessorTest extends TestCase
{

    public function testParse(): void
    {
        $processor = new TrefoilMarkerProcessor();
        $processor->registerMarker(
            'one',
            function () {
                return 'expanded1';
            });
        $processor->registerMarker(
            'two',
            function ($thing) {
                return 'expanded2-'.$thing;
            });

        $input = <<<TEXT
Lorem ipsum {@ one() @}.
Ipsum lorem.
Dolor sit amen {@ two("a") @}.
TEXT;

        $expected = <<<TEXT
Lorem ipsum expanded1.
Ipsum lorem.
Dolor sit amen expanded2-a.
TEXT;

        $actual = $processor->parse($input);

        static::assertEquals($expected, $actual);
    }

    public function testParseWithSeparators(): void
    {
        $processor = new TrefoilMarkerProcessor();
        $processor->registerMarker(
            'one',
            function () {
                return 'expanded1';
            });
        $processor->registerMarker(
            'two',
            function ($thing) {
                return 'expanded2-'.$thing;
            });

        $input = <<<TEXT
Lorem ipsum {@ ========================= one() @}.
Ipsum lorem.
Dolor sit amen 
{@ ===================== 
   two("a") @}.
TEXT;

        $expected = <<<TEXT
Lorem ipsum expanded1.
Ipsum lorem.
Dolor sit amen 
expanded2-a.
TEXT;

        $actual = $processor->parse($input);

        static::assertEquals($expected, $actual);
    }
}
