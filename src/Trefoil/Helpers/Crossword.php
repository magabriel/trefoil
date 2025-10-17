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

namespace Trefoil\Helpers;

use Trefoil\Util\Toolkit;
use Collator;

enum OrientationType: string
{
    case ACROSS = '>';
    case DOWN = 'v';
    case BOTH = '=';
}


class CrosswordCell
{
    public ?bool $isBlock = null;
    public ?string $letter = null;
    public ?string $wordDown = null; // The word that starts DOWN in this cell, if any
    public ?string $wordAcross = null; // The word that starts ACROSS in this cell, if any
    public ?int $definitionNumber = null;
    public ?OrientationType $orientation = null;
}

class PlacedWord
{
    public ?string $word = null;
    public ?string $definition = null;
}

class Crossword
{
    public const DEFAULT_BOARD = [
        ".....",
        ".....",
        ".....",
        ".....",
        "....."
    ];

    protected array $board = self::DEFAULT_BOARD;

    /**
     * The puzzle grid. Each cell is a CrosswordCell object.
     * @var CrosswordCell[][]
     */
    protected array $puzzle = [];

    protected PseudoRandom $random;

    /**
     * Dictionary of words to be placed in the puzzle (word -> definition).
     * @var array<string, string>
     */
    protected array $wordsWithDefinitions = [];

    /**
     * Summary of words, categorized by word length
     * @var array
     */
    protected array $wordList = [
        1 => [],
        2 => [],
        /* and so on */
    ];

    /**
     * List of all placed words with their definitions. The index is the definition number.
     * @var list<PlacedWord>
     */
    protected array $placedWords = [];

    protected array $errors = [];

    public function __construct()
    {
        $this->random = new PseudoRandom();
    }

    public function setRandomSeed(int $seed)
    {
        $this->random->setRandomSeed($seed);
    }

    /**
     * Generate the crossword puzzle.
     * @param array<string, string> $wordsWithDefinitions Words with their definitions (word => definition).
     * @param array<string> $board
     * @return bool
     */
    public function generate(array $wordsWithDefinitions, array $board = self::DEFAULT_BOARD): bool
    {
        print_r($this->wordsWithDefinitions);
        print_r($this->board);

        $this->board = $board;
        $this->wordsWithDefinitions = $wordsWithDefinitions;

        $this->prepareBoard();
        $this->initializePuzzle();
        $this->prepareWordList();

        $success = $this->placeWords(0, 0, $this->puzzle);

        echo "\nFinal puzzle BEFORE:\n" . $this->puzzleAsText() . "\n\n";

        if ($success) {
            $this->completePlacedWords();
        }

        echo "\nFinal puzzle:\n" . $this->puzzleAsText() . "\n\n";
        echo "Placed words:\n";
        print_r($this->placedWords);
        echo "Errors:\n";
        print_r($this->errors);

        return $success;
    }

    protected function prepareBoard(): void
    {
        $rowLength = null;
        foreach ($this->board as $y => $row) {
            $cells = str_split($row);
            if ($rowLength === null) {
                $rowLength = count($cells);
            } elseif (count($cells) !== $rowLength) {
                throw new \InvalidArgumentException('All rows in the board must have the same length');
            }
            foreach ($cells as $x => $cell) {
                if ($cell !== '.' && $cell !== ' ') {
                    throw new \InvalidArgumentException('Board can only contain "." or " " characters');
                }

            }
        }

        // Replace "." with " " for blocks
        foreach ($this->board as $y => $row) {
            $this->board[$y] = str_replace('.', ' ', $row);
        }
    }

    protected function initializePuzzle(): void
    {
        $this->puzzle = [];
        foreach ($this->board as $y => $row) {
            $this->puzzle[$y] = [];
            $cells = str_split($row);
            foreach ($cells as $x => $cell) {
                $this->puzzle[$y][$x] = new CrosswordCell();
                if ($cell === '.' || $cell === ' ') {
                    $this->puzzle[$y][$x]->isBlock = false;
                } else {
                    $this->puzzle[$y][$x]->isBlock = true;
                }
            }
        }

        # TODO: Assign definition numbers to cells
    }

    protected function prepareWordList(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            $this->wordList[$i] = [];
        }

        arsort($this->wordsWithDefinitions);

        foreach ($this->wordsWithDefinitions as $word => $definition) {
            $len = mb_strlen($word);
            if ($len === 0) {
                continue;
            }
            if (!array_key_exists($len, $this->wordList)) {
                $this->wordList[$len] = [];
            }
            if (!in_array($word, $this->wordList[$len], true)) {
                $this->wordList[$len][] = $word;
            }
        }
    }

    protected function placeWords(int $row, int $col, array $puzzle, array $usedWords = []): bool
    {
        $this->depth++;
        $this->log(sprintf("::ENTER placeWords row=%d, col=%d", $row, $col));
        $this->log("\n" . $this->puzzleAsText($puzzle));

        $rows = count($puzzle);
        $cols = count($puzzle[0]);

        if ($row >= $rows || $col >= $cols) {
            $this->log("All cells processed");
            $this->depth--;
            return true; // All cells processed 
        }

        if ($this->puzzle[$row][$col]->isBlock === true) {
            $this->log(sprintf("Recursive call to skip block at row %d, col %d", $row + 1, $col + 1));

            // Skip blocks
            $result = $this->placeWords($row, $col + 1, $puzzle, $usedWords);
            $this->depth--;
            return $result;
        }

        $neededWordLength = $cols;
        $candidateWords = $this->wordList[$neededWordLength];
        $wordisValid = false;


        while (count($candidateWords) > 0 && !$wordisValid) {

            // $this->log(sprintf("Candidate words:"));
            // print_r($candidateWords);

            [$word, $candidateWords] = $this->chooseHorizontalWord($candidateWords, $usedWords);

            $this->log(sprintf("Chosen word: %s", $word));

            // $this->log(sprintf("Candidate words after:"));
            // print_r($candidateWords);

            if ($word === null) {
                // Backtrack: no more words of the needed length
                $this->log(sprintf("No words of length %d available", $neededWordLength));
                $this->depth--;
                return false;
            }

            $this->placeTheWordAcross($puzzle, $row, $col, $word);

            // $this->log(sprintf("Puzzle after placing the word:"));
            // $this->log("\n" . $this->puzzleAsText($puzzle));

            $wordisValid = $this->checkVerticalWords($row, $puzzle, [$word, ...$usedWords]);
            if (!$wordisValid) {
                // Backtrack: the word placed causes vertical words to be invalid
                $this->log("Word is NOT valid");
                $this->clearTheWord($puzzle, $row, $col, $neededWordLength);
                continue;
            }

            $this->log("Word is valid");

            $nextCol = $col + $neededWordLength;
            if ($nextCol < $cols) {
                // Continue with next column in the same row
                $this->log(sprintf(">>>> Recursive call to next col at row %d, col %d", $row + 1, $nextCol + 1));
                if ($this->placeWords($row, $nextCol, $puzzle, $usedWords)) {
                    // Success
                    $this->puzzle = $puzzle;
                    $usedWords[] = $word;
                    $this->depth--;
                    return true;
                } else {
                    // Backtrack: cannot place the word
                    $this->log(sprintf("Next col:Cannot place \"%s\" at row %d, col %d", $word, $row + 1, $col + 1));
                    $this->clearTheWord($puzzle, $row, $col, $neededWordLength);
                    $wordisValid = false;
                    continue;
                }
            }

            // Continue with next row
            $this->log(sprintf(">>>> Recursive call to next row at row %d, col %d", $row + 2, 1));
            if ($this->placeWords($row + 1, 0, $puzzle, $usedWords)) {
                // Success
                $this->puzzle = $puzzle;
                $usedWords[] = $word;
                $this->depth--;
                return true;
            } else {
                // Backtrack: cannot place the word
                $this->log(sprintf("Next row: Cannot place \"%s\" at row %d, col %d", $word, $row + 1, $col + 1));
                $this->clearTheWord($puzzle, $row, $col, $neededWordLength);
                $wordisValid = false;
                continue;
            }

        }

        $this->log(sprintf("No more candidate words at row %d, col %d", $row + 1, $col + 1));
        $this->depth--;
        return false; // No valid word could be placed
    }

    /**
     * Place the word in the puzzle at the given row and column in horizontal orientation.
     * 
     * @param CrosswordCell[][] $puzzle
     * @param int $row
     * @param int $col
     * @param string $word
     * @return void
     */
    private function placeTheWordAcross(array &$puzzle, int $row, int $col, string $word): void
    {
        $neededWordLength = mb_strlen($word);
        for ($iCol = $col; $iCol < $col + $neededWordLength; $iCol++) {
            $puzzle[$row][$iCol]->letter = $word[$iCol - $col];
        }
        $puzzle[$row][$col]->orientation = OrientationType::ACROSS;
        $puzzle[$row][$col]->wordAcross = $word;
    }

    private function clearTheWord(array &$puzzle, int $row, int $col, int $len): void
    {
        for ($iCol = $col; $iCol < $col + $len; $iCol++) {
            $puzzle[$row][$iCol]->letter = null;
        }
    }

    private function checkVerticalWords(int $row, array $puzzle, array $usedWords): bool
    {
        $this->log(sprintf("::checkVerticalWords for row %d", $row + 1));
        // $this->log("Used words:");
        // print_r($usedWords);

        $rows = count($puzzle);
        $cols = count($puzzle[0]);

        // Check if there are words in the list than can be placed on all vertical columns
        for ($col = 0; $col < $cols; $col++) {
            $neededWordVerticalLength = $cols;

            // Find the word start row (row 0 or the previous non-block cell in the column)
            $wordStartRow = $row;
            $theRow = $row;
            while ($theRow >= 0) {
                if ($puzzle[$theRow][$col]->isBlock) {
                    break;
                }
                $wordStartRow = $theRow;
                $theRow--;
            }

            // $this->log(sprintf("Word start row for col %d is %d", $col, $wordStartRow));

            // Find the word end row (row rows-1 or the previous to the next non-block cell in the column)
            $wordEndRow = $row;
            $theRow = $row;
            while ($theRow <= $rows - 1) {
                if ($puzzle[$theRow][$col]->isBlock) {
                    break;
                }
                $wordEndRow = $theRow;
                $theRow++;
            }

            // $this->log(sprintf("Word end row for col %d is %d", $col, $wordEndRow));

            // Get the row
            $neededWordVerticalLength = $wordEndRow - $wordStartRow + 1;
            $wordStartsWith = '';
            for ($r = $wordStartRow; $r <= $wordEndRow; $r++) {
                if ($puzzle[$r][$col]->letter) {
                    $wordStartsWith .= $puzzle[$r][$col]->letter;
                }
            }

            // $this->log(sprintf("Needed word length for col %d is %d", $col, $neededWordVerticalLength));
            // $this->log(sprintf("Word starts with '%s'", $wordStartsWith));

            $possibleWords = array_filter(
                $this->wordList[$neededWordVerticalLength],
                function ($w) use ($wordStartsWith, $neededWordVerticalLength, $usedWords) {
                    $valid = str_starts_with($w, $wordStartsWith) && mb_strlen($w) === $neededWordVerticalLength;
                    $valid = $valid && !in_array($w, $usedWords, true);
                    return $valid;
                }
            );

            // $this->log(sprintf("Possible words for col %d (length %d, starts with '%s'):", $col, $neededWordVerticalLength, $wordStartsWith));
            // print_r($possibleWords);

            if (count($possibleWords) === 0) {
                return false;
            }
        }

        return true;
    }

    private function chooseHorizontalWord(array $candidateWords, array $usedWords): array
    {
        $this->log("::chooseHorizontalWord");
        // $this->log("Candidate words:");
        // print_r($candidateWords);
        // $this->log("Used words:");
        // print_r($usedWords);

        // Choose a random candidate word
        $word = null;
        if (count($candidateWords) < 1) {
            return [null, $candidateWords];
        }

        // Avoid using the same word again
        do {
            $wordIndex = $this->random->getRandomInt(0, count($candidateWords) - 1);
            $word = $candidateWords[$wordIndex];
            array_splice($candidateWords, $wordIndex, 1);
        } while (
            in_array($word, $usedWords, true) &&
            count($candidateWords) > 0
        );


        return [$word, $candidateWords];
    }
    /**
     * Assign orientation types and definition numbers and store placed words with definitions.
     * The ACROSS orientation types were assigned when placing the words.
     * We need to assign the DOWN orientation types. Some of them will be BOTH.
     * @return void
     */
    protected function completePlacedWords(): void
    {
        $definitionNumber = 1;

        $rows = count($this->puzzle);
        $cols = count($this->puzzle[0]);

        // First, we assign numbers to DOWN words
        for ($row = 0; $row < $rows; $row++) {
            for ($col = 0; $col < $cols; $col++) {
                $cell = $this->puzzle[$row][$col];

                if ($cell->isBlock) {
                    continue;
                }

                if ($row === 0 || $this->puzzle[$row - 1][$col]->isBlock) {
                    // This cell is the start of a DOWN word
                } else {
                    continue;
                }

                // Retrieve the word placed DOWN in this cell, if any
                $wordStartRow = $row;
                $wordStartCol = $col;
                $theWord = "";
                for ($r = $row; $r < $rows; $r++) {
                    if ($this->puzzle[$r][$col]->isBlock) {
                        break;
                    }
                    $theWord .= $this->puzzle[$r][$col]->letter;
                }

                // Save the word and orientation to the cell
                $this->puzzle[$wordStartRow][$wordStartCol]->wordDown = $theWord;
                if ($cell->orientation === OrientationType::ACROSS) {
                    $this->puzzle[$wordStartRow][$wordStartCol]->orientation = OrientationType::BOTH;
                } elseif ($cell->orientation === null) {
                    $this->puzzle[$wordStartRow][$wordStartCol]->orientation = OrientationType::DOWN;
                }

                // Save the word as placed DOWN
                $placedWord = new PlacedWord();
                $placedWord->word = $theWord;
                $this->placedWords[] = $placedWord;
                // The definition number is the index in the placedWords array
                $definitionNumber = count($this->placedWords) - 1;
                // Save it to the cell
                $this->puzzle[$wordStartRow][$wordStartCol]->definitionNumber = $definitionNumber;

            }
        }

        // Then to the ACCROSS words
        for ($row = 0; $row < $rows; $row++) {
            for ($col = 0; $col < $cols; $col++) {
                $cell = $this->puzzle[$row][$col];
                if ($cell->isBlock) {
                    continue;
                }
                if ($cell->orientation === OrientationType::ACROSS || $cell->orientation === OrientationType::BOTH) {
                    // Save the word as placed ACROSS
                    $placedWord = new PlacedWord();
                    $placedWord->word = $cell->wordAcross;
                    $this->placedWords[] = $placedWord;
                    // The definition number is the index in the placedWords array
                    $definitionNumber = count($this->placedWords) - 1;
                    // Save it to the cell
                    // TODO: Hay que usar definitionNumbers separados para ACROSS y DOWN 
                    if ($cell->orientation === OrientationType::BOTH) {
                        // Leave the same number as assigned for DOWN
                    } else {
                        $this->puzzle[$row][$col]->definitionNumber = $definitionNumber;
                    }
                    continue;
                }
            }
        }
    }

    public function puzzleAsText(array $puzzle = []): string
    {
        if (empty($puzzle)) {
            $puzzle = $this->puzzle;
        }

        $lines = [];

        foreach ($puzzle as $y => $row) {
            $cells = [];
            foreach ($row as $x => $cell) {
                $cell = $puzzle[$y][$x];
                $indicator = '   ';
                $contents = '    ';

                if ($cell->isBlock) {
                    $indicator = '  #';
                    $contents = '# ';
                }
                if ($cell->definitionNumber !== null) {
                    $indicator = sprintf('%2d', $cell->definitionNumber);
                    $indicator .= $cell->orientation->value;
                }
                if ($cell->letter !== null) {
                    $contents = strtoupper($cell->letter);
                }


                $cells[] = $indicator . $contents;

            }
            $lines[] = '|' . implode('|', $cells) . '|';
        }

        // Header and footer
        $border = '+' . str_repeat('----+', count($puzzle[0]));
        array_unshift($lines, $border);
        $lines[] = $border;

        return implode("\n", $lines);
    }

    public function __toString(): string
    {
        return $this->puzzleAsText();
    }

    protected $depth = 0;
    protected function log(string $message): void
    {
        echo sprintf("[%d] %s\n", $this->depth, $message);
    }
}
