<?php
/**
 * Created by PhpStorm.
 * User: miguelangel
 * Date: 26/12/18
 * Time: 9:05
 */

namespace Trefoil\Helpers;


class TrefoilMarkerProcessorTest extends \PHPUnit_Framework_TestCase
{

    public function testParse()
    {
        $processor = new TrefoilMarkerProcessor();
        $processor->registerMarker("one", function (){
            return 'expanded1';
        });
        $processor->registerMarker("two", function ($thing){
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

        $this->assertEquals($expected, $actual);
    }

    public function testParseWithSeparators()
    {
        $processor = new TrefoilMarkerProcessor();
        $processor->registerMarker("one", function (){
            return 'expanded1';
        });
        $processor->registerMarker("two", function ($thing){
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

        $this->assertEquals($expected, $actual);
    }
}
