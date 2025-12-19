<?php

declare(strict_types=1);

namespace Trefoil\Tests\Helpers;

use Symfony\Component\Filesystem\Filesystem;
use Trefoil\DependencyInjection\Application;
use Trefoil\Helpers\CrosswordRenderer;
use Trefoil\Tests\HelpersTestCase;

class CrosswordRendererTest extends HelpersTestCase
{

    private function isDebug(): bool
    {
        return array_key_exists('debug', getopt('', ['debug']));
    }

    public function testRender()
    {
        $sut = new CrosswordRenderer();
        $sut->loadIpuz(__DIR__ . '/fixtures/crossword-renderer-render-input.ipuz');

        $success = $sut->render();
        if ($this->isDebug()) {
            echo "\nErrors:\n" . implode("\n", $sut->getErrors()) . "\n";
        }

        self::assertTrue($success);

        $this->testFixture('crossword-renderer-render-expected.txt', $sut);
    }

    private function testFixture(string $fixtureName, CrosswordRenderer $sut)
    {
        $actualPuzzle = $sut->getPuzzleHtml();
        $actualSolution = $sut->getSolutionHtml();
        $actualCluesAcross = $sut->getCluesAcrossHtml();
        $actualCluesDown = $sut->getCluesDownHtml();

        $testData = $this->readFixture($fixtureName);

        if ($this->isDebug()) {

            $actual = '';
            $actual .= '==== PUZZLE HTML' . "\n";
            $actual .= $actualPuzzle . "\n";
            $actual .= '==== CLUES ACROSS HTML' . "\n";
            $actual .= $actualCluesAcross . "\n";
            $actual .= '==== CLUES DOWN HTML' . "\n";
            $actual .= $actualCluesDown . "\n";
            $actual .= '==== SOLUTION HTML' . "\n";
            $actual .= $actualSolution . "\n";

            $this->saveTestData($fixtureName, $testData['text'], $actual);
        }

        self::assertEquals($testData['PUZZLE HTML'], $actualPuzzle, 'Puzzle HTML not correctly rendered');
        self::assertEquals($testData['CLUES ACROSS HTML'], $actualCluesAcross, 'Clues Across HTML not correctly rendered');
        self::assertEquals($testData['CLUES DOWN HTML'], $actualCluesDown, 'Clues Down  HTML not correctly rendered');
        self::assertEquals($testData['SOLUTION HTML'], $actualSolution, 'Solution HTML not correctly rendered');
    }

    private function readFixture(string $fixtureName): array
    {
        $text = file_get_contents(__DIR__ . '/fixtures/' . $fixtureName);
        $fileData = preg_split('/^==== (.*$)\n/m', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $data = [];
        for ($i = 1; $i < count($fileData); $i = $i + 2) {
            $fixtureText = $fileData[$i + 1];
            if (str_ends_with($fixtureText, "\n")) {
                $fixtureText = substr($fixtureText, 0, -1);
            }
            $data[$fileData[$i]] = $fixtureText;
        }

        $data['text'] = $text;

        return $data;
    }

}
