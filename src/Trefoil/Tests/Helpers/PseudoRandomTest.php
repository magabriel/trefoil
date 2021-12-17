<?php

declare(strict_types=1);

namespace Trefoil\Tests\Helpers;

use PHPUnit\Framework\TestCase;

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
}
