<?php

declare(strict_types=1);

namespace Trefoil\Tests\Helpers;

use Symfony\Component\Filesystem\Filesystem;
use Trefoil\DependencyInjection\Application;
use Trefoil\Helpers\Crossword;
use Trefoil\Tests\HelpersTestCase;

class CrosswordTest extends HelpersTestCase
{

    private function isDebug(): bool
    {
        return array_key_exists('debug', getopt('', ['debug']));
    }

    public function testGenerateWithDefaults()
    {
        $fixtureName = 'crossword-generate-with-defaults.txt';

        $testData = $this->readFixture($fixtureName);

        $sut = new Crossword();
        $sut->setRandomSeed(1); // Fixed seed for reproducibility

        $wordsWithDefinitions = $this->getWordListFromFixture($fixtureName);
        $board = $testData['BOARD'] ?? Crossword::DEFAULT_BOARD;

        $success = $sut->generate($wordsWithDefinitions, board: $board);
        if ($this->isDebug()) {
            echo "\nErrors:\n" . implode("\n", $sut->getErrors()) . "\n";
        }

        self::assertTrue($success, 'Generate puzzle.');

        $this->testFixture($fixtureName, $sut);
    }

    public function testGenerate7x7()
    {
        $fixtureName = 'crossword-generate-7x7.txt';

        $testData = $this->readFixture($fixtureName);

        $sut = new Crossword();
        $sut->setRandomSeed(1); // Fixed seed for reproducibility

        $wordsWithDefinitions = $this->getWordListFromFixture($fixtureName);
        $board = explode("\n", trim($testData['BOARD'] ?? Crossword::DEFAULT_BOARD));

        $success = $sut->generate($wordsWithDefinitions, board: $board);
        if ($this->isDebug()) {
            echo "\nErrors:\n" . implode("\n", $sut->getErrors()) . "\n";
        }

        self::assertTrue($success, 'Generate puzzle.');

        $this->testFixture($fixtureName, $sut);
    }

    private function getWordListFromFixture(string $fixtureName): array
    {
        $testData = $this->readFixture($fixtureName);

        $wordsWithDefinitions = [];
        $lines = explode("\n", $testData['WORD LIST']);
        foreach ($lines as $line) {
            $parts = explode(' ', $line, 2);
            $word = trim($parts[0]);
            if (empty($word)) {
                continue;
            }
            $definition = trim($parts[1] ?? '');
            $wordsWithDefinitions[$word] = $definition;
        }

        return $wordsWithDefinitions;
    }

    private function testFixture(string $fixtureName, Crossword $sut)
    {
        $actualPuzzle = $sut->puzzleAsText();

        $testData = $this->readFixture($fixtureName);

        if ($this->isDebug()) {
            $actual = '';
            $actual .= '==== BOARD TEXT' . "\n";
            $actual .= $sut->boardAsText() . "\n";
            $actual .= '==== PUZZLE TEXT' . "\n";
            $actual .= $sut->puzzleAsText() . "\n";
            $actual .= '==== DEFINITION LIST' . "\n";
            $actual .= $sut->definitionListAsText() . "\n";

            $this->saveTestData($fixtureName, $testData['text'], $actual);
        }

        self::assertEquals(
            trim($testData['EXPECTED BOARD TEXT']),
            trim($sut->boardAsText()),
            'Board TEXT not correctly generated'
        );

        self::assertEquals(
            trim($testData['EXPECTED PUZZLE TEXT']),
            trim($sut->puzzleAsText()),
            'Puzzle TEXT not correctly generated'
        );

        self::assertEquals(
            trim($testData['EXPECTED DEFINITION LIST']),
            trim($actualDefinitionList = $sut->definitionListAsText()),
            'Definition LIST not correctly generated'
        );
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
