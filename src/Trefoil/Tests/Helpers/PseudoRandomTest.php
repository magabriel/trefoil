<?php

declare(strict_types=1);

namespace Trefoil\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use Trefoil\Helpers\PseudoRandom;

class PseudoRandomTest extends TestCase
{
    public function testGenerateKnownSequence()
    {
        $sut = new PseudoRandom();

        $sut->setRandomSeed(5);

        $expected = '8, 9, 0, 6, 5, 4, 8, 3, 4, 9';

        $numbers = [];
        for ($i = 0; $i < 10; $i++) {
            $numbers[] = $sut->getRandomInt(0, 10);
        }
        $actual = implode(', ', $numbers);

        $this->assertEquals($expected, $actual);
    }

    public function testShuffle()
    {
        $sut = new PseudoRandom();

        $sut->setRandomSeed(123);

        $input = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
        $expected = [1, 3, 2, 7, 9, 6, 8, 10, 5, 4];

        $actual = $sut->shuffle($input);

        $this->assertEquals($expected, $actual);
    }
}
