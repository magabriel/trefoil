<?php declare(strict_types=1);

namespace Trefoil\Helpers;

use PHPUnit\Framework\TestCase;

/**
 *
 */
class WordSearchTest extends TestCase
{
    public function testGenerateWithDefaults()
    {
        $sut = new WordSearch();

        $sut->setRandomSeed(5);

        $success = $sut->generate();
        echo "\nErrors:\n".implode("\n", $sut->getErrors())."\n";

        self::assertTrue($success);

        $this->testFixture('wordsearch-generate-with-defaults.txt', $sut);
    }

    private function testFixture(string $fixtureName, WordSearch $sut) {
        $actualPuzzle = $sut->puzzleAsText();
        $actualSolution = $sut->solutionAsText();
        $actualWordList = $sut->wordListAsText();
        $actualPuzzleHtml = tidy_repair_string($sut->puzzleAsHtml());
        $actualSolutionHtml = tidy_repair_string($sut->solutionAsHtml());
        $actualWordListHtml = tidy_repair_string($sut->wordListAsHtml());

        $testData = $this->readFixture($fixtureName);

        echo "====== PUZZLE\n";
        print_r($actualPuzzle);
        echo "\n\n";
        echo "====== WORDLIST\n";
        print_r($actualWordList);
        echo "\n\n";
        echo "====== SOLUTION\n";
        print_r($actualSolution);
        echo "\n\n";
        echo "====== PUZZLE HTML\n";
        print_r($actualPuzzleHtml);
        echo "\n\n";
        echo "====== WORDLIST HTML\n";
        print_r($actualWordListHtml);
        echo "\n\n";
        echo "====== SOLUTION HTML\n";
        print_r($actualSolutionHtml);

        self::assertEquals($testData['EXPECTED PUZZLE TEXT'], $actualPuzzle, 'Puzzle TEXT not correctly generated');
        self::assertEquals($testData['EXPECTED WORD LIST TEXT'], $actualWordList, 'Word list TEXT not correctly generated');
        self::assertEquals($testData['EXPECTED SOLUTION TEXT'], $actualSolution, 'Solution TEXT not correctly generated');
        self::assertEquals(tidy_repair_string($testData['EXPECTED PUZZLE HTML']), $actualPuzzleHtml, 'Puzzle HTML not correctly generated');
        self::assertEquals(tidy_repair_string($testData['EXPECTED WORD LIST HTML']), $actualWordListHtml, 'Word list HTML not correctly generated');
        self::assertEquals(tidy_repair_string($testData['EXPECTED SOLUTION HTML']), $actualSolutionHtml, 'Solution HTML not correctly generated');
    }

    private function readFixture(string $fixtureName): array
    {
        $text = file_get_contents(__DIR__.'/fixtures/'.$fixtureName);
        $fileData = preg_split('/^==== (.*$)\n/m', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $data = [];
        for ($i = 1; $i < count($fileData); $i = $i + 2) {
            $fixtureText = $fileData[$i + 1];
            if (str_ends_with($fixtureText, "\n")) {
                $fixtureText = substr($fixtureText, 0, -1);
            }
            $data[$fileData[$i]] = $fixtureText;
        }

        return $data;
    }

    public function testGenerateFailsOnImpossiblePuzzle()
    {
        $sut = new WordSearch();

        $words = ['wordsearch', 'puzzle', 'testing', 'phpunit', 'generator'];
        $success = $sut->generate(5, 5, $words);
        echo "\nErrors:\n".implode("\n", $sut->getErrors())."\n";

        self::assertFalse($success);
    }

    public function testGenerateCustom()
    {
        $sut = new WordSearch();
        $sut->setRandomSeed(17);

        $words = ['wordsearch', 'puzzle', 'testing', 'phpunit', 'generator'];

        $success = $sut->generate(15, 15, $words, WordSearch::FILLER_LETTERS_ENGLISH.'@%');
        echo "\nErrors:\n".implode("\n", $sut->getErrors())."\n";

        self::assertTrue($success);

        $this->testFixture('wordsearch-generate-custom.txt', $sut);
    }

    public function testGeneratCustomWithNumberOfWords()
    {
        $sut = new WordSearch();
        $sut->setRandomSeed(17);

        $words = ['wordsearch', 'puzzle', 'testing', 'phpunit', 'generator'];

        $success = $sut->generate(15, 15, $words, WordSearch::FILLER_LETTERS_ENGLISH.'@%', 3);
        echo "\nErrors:\n".implode("\n", $sut->getErrors())."\n";

        self::assertTrue($success);

        $this->testFixture('wordsearch-generate-custom-with-number-of-words.txt', $sut);
    }
}
