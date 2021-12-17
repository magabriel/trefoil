<?php

declare(strict_types=1);

namespace Trefoil\Tests\Helpers;

use Symfony\Component\Filesystem\Filesystem;
use Trefoil\DependencyInjection\Application;
use Trefoil\Helpers\CrossWords;
use Trefoil\Tests\HelpersTestCase;

class CrossWordsTest extends HelpersTestCase {

    private function isDebug(): bool {
        return array_key_exists('debug', getopt('', ['debug']));
    }

    public function testGenerateWithDefaults() {
        $sut = new CrossWords();

        $sut->setRandomSeed(1);

        $success = $sut->generate();
        if ($this->isDebug()) {
            echo "\nErrors:\n" . implode("\n", $sut->getErrors()) . "\n";
        }

        self::assertTrue($success);

        $this->testFixture('crosswords-generate-with-defaults.txt', $sut);
    }

    private function testFixture(string $fixtureName, CrossWords $sut) {
        $actualPuzzle = $sut->puzzleAsText();
        $actualSolution = $sut->solutionAsText();
        $actualWordList = $sut->wordListAsText();
        $actualPuzzleHtml = tidy_repair_string($sut->puzzleAsHtml());
        $actualSolutionHtml = tidy_repair_string($sut->solutionAsHtml());
        $actualWordListHtml = tidy_repair_string($sut->wordListAsHtml());

        $testData = $this->readFixture($fixtureName);

        if ($this->isDebug()) {
                        
            $actual = '';
            $actual .= '==== PUZZLE TEXT'."\n";
            $actual .= $actualPuzzle . "\n";
            $actual .= '==== WORD LIST TEXT'."\n";
            $actual .= $actualWordList . "\n";
            $actual .= '==== SOLUTION TEXT'."\n";
            $actual .= $actualSolution . "\n";
            $actual .= '==== PUZZLE HTML'."\n";
            $actual .= $actualPuzzleHtml . "\n";
            $actual .= '==== WORD LIST HTML'."\n";
            $actual .= $actualWordListHtml . "\n";
            $actual .= '==== SOLUTION HTML'."\n";
            $actual .= $actualSolutionHtml . "\n";

            $this->saveTestData($fixtureName, $testData['text'], $actual);                      
        }

        self::assertEquals($testData['PUZZLE TEXT'], $actualPuzzle, 'Puzzle TEXT not correctly generated');
        self::assertEquals($testData['WORD LIST TEXT'], $actualWordList, 'Word list TEXT not correctly generated');
        self::assertEquals($testData['SOLUTION TEXT'], $actualSolution, 'Solution TEXT not correctly generated');
        self::assertEquals(tidy_repair_string($testData['PUZZLE HTML']), $actualPuzzleHtml, 'Puzzle HTML not correctly generated');
        self::assertEquals(tidy_repair_string($testData['WORD LIST HTML']), $actualWordListHtml, 'Word list HTML not correctly generated');
        self::assertEquals(tidy_repair_string($testData['SOLUTION HTML']), $actualSolutionHtml, 'Solution HTML not correctly generated');
    }

    private function readFixture(string $fixtureName): array {
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

    public function testGenerateFailsOnImpossiblePuzzle() {
        $sut = new CrossWords();

        $words = ['crosswords', 'puzzle', 'testing', 'phpunit', 'generator'];
        $success = $sut->generate(5, 5, $words);
        if ($this->isDebug()) {
            echo "\nErrors:\n" . implode("\n", $sut->getErrors()) . "\n";
        }

        self::assertFalse($success);
    }

    public function testGenerateCustom() {
        $sut = new CrossWords();
        $sut->setRandomSeed(1);

        $words = ['crosswords', 'puzzle', 'testing', 'phpunit', 'generator', 'another', 'word', 'important', 'filler'];

        $success = $sut->generate(20, 20, $words);
        if ($this->isDebug()) {
            echo "\nErrors:\n" . implode("\n", $sut->getErrors()) . "\n";
        }

        self::assertTrue($success);

        $this->testFixture('crosswords-generate-custom.txt', $sut);
    }

    public function testGeneratCustomWithNumberOfWords() {
        $sut = new CrossWords();
        $sut->setRandomSeed(17);

        $words = ['crosswords', 'puzzle', 'testing', 'phpunit', 'generator'];

        $success = $sut->generate(15, 15, $words, 3);
        if ($this->isDebug()) {
            echo "\nErrors:\n" . implode("\n", $sut->getErrors()) . "\n";
        }

        self::assertTrue($success);

        $this->testFixture('crosswords-generate-custom-with-number-of-words.txt', $sut);
    }

}
