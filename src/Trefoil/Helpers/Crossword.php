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


/** @internal */
class CrosswordCell
{
    public ?bool $isBlock = null;
    public ?string $letter = null;
    public ?int $definitionNumber = null;
    public ?int $downWordStartRow = null;
    public ?int $downWordEndRow = null;
}

/** @internal */
class CrosswordPlacedWord
{
    public ?string $word = null;
    public ?string $definition = null;
    public ?string $orientation = null; /* across or down */
    public ?int $definitionNumber = null;
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

    protected const ORIENTATION_ACROSS = 'across';
    protected const ORIENTATION_DOWN = 'down';

    /**
     * The board layout.
     * @var array
     */
    protected array $boardLayout = self::DEFAULT_BOARD;

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
     * @var list<CrosswordPlacedWord>
     */
    protected array $placedWords = [];

    /**
     * The used definitions (word -> definition).
     * @var array
     */
    protected $usedDefinitions = [];

    /**
     * The errors encountered during puzzle generation.
     * @var array
     */
    protected array $errors = [];

    /**
     * The warnings encountered during puzzle generation.
     * @var array
     */
    protected array $warnings = [];

    /**
     * Enable debug logging.
     * @var bool
     */
    public bool $debug = false;
    public bool $debugStatus = false;
    public bool $useDebugFile = false;

    protected $recursionDepth = 0;
    protected $tries = 0;
    protected $antTries = 0;
    protected $elapsedTime = 0;
    protected $antElapsedTime = 0;

    public function __construct()
    {
        $this->random = new PseudoRandom();
    }

    public function __toString(): string
    {
        return $this->solutionAsText();
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function setRandomSeed(int $seed)
    {
        $this->random->setRandomSeed($seed);
    }

    /**
     * Generate the crossword puzzle.
     * @param array<string, string> $wordsWithDefinitions Words with their definitions (word => definition).
     * @param array<string> $boardLayout
     * @return bool
     */
    public function generate(array $wordsWithDefinitions, array $boardLayout = self::DEFAULT_BOARD): bool
    {
        if (empty($wordsWithDefinitions)) {
            $this->errors[] = 'The word list cannot be empty.';
            return false;
        }

        if ($boardLayout === []) {
            $this->errors[] = 'The board layout cannot be empty.';
            return false;
        }

        $this->boardLayout = $boardLayout;
        $this->wordsWithDefinitions = $wordsWithDefinitions;

        $this->tries = 0;
        $this->antTries = 0;
        $this->elapsedTime = time();
        $this->antElapsedTime = 0;

        $this->clearDebugLog();
        $this->prepareBoard();
        $this->initializePuzzle();
        $this->prepareWordList();

        $this->log($this->puzzleAsText());
        $this->writeDebugStatus($this->puzzle, true);

        $success = $this->placeWords(0, 0, $this->puzzle);

        if ($success) {
            $this->completePlacedWords();
            $this->createUsedDefinitionList();
        }

        $this->writeDebugStatus($this->puzzle, true);

        $this->log("\nFinal puzzle:\n" . $this->solutionAsText() . "\n");
        $this->log("\nDefinitions:\n" . $this->definitionListAsText() . "\n\n");

        $this->log("Errors:\n");
        $this->log(print_r($this->errors, true));

        return $success;
    }

    protected function prepareBoard(): void
    {
        $rowLength = null;
        foreach ($this->boardLayout as $y => $row) {
            $cells = str_split($row);
            if ($rowLength === null) {
                $rowLength = count($cells);
            } elseif (count($cells) !== $rowLength) {
                $this->errors[] = 'All rows in the board must have the same length';
                return;
            }
            foreach ($cells as $x => $cell) {
                if ($cell !== '.' && $cell !== ' ' && $cell !== '#') {
                    $this->errors[] = 'Board can only contain ".", " ", or "#" characters';
                    return;
                }
            }
        }

        // Replace "." with " " for blocks
        foreach ($this->boardLayout as $y => $row) {
            $this->boardLayout[$y] = str_replace('.', ' ', $row);
        }
    }

    protected function initializePuzzle(): void
    {
        $this->puzzle = [];
        foreach ($this->boardLayout as $y => $row) {
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

        // Precompute the down word start and end rows for each cell
        $rows = count($this->puzzle);
        $cols = count($this->puzzle[0]);

        $wordStartRow = -1;
        $wordEndRow = -1;

        for ($row = 0; $row < $rows; $row++) {
            for ($col = 0; $col < $cols; $col++) {
                // Find the word start row (row 0 or the previous non-block cell in the column)
                $wordStartRow = $row;
                $theRow = $row;
                while ($theRow >= 0) {
                    if ($this->puzzle[$theRow][$col]->isBlock) {
                        break;
                    }
                    $wordStartRow = $theRow;
                    $theRow--;
                }

                // Find the word end row (row rows-1 or the previous to the next non-block cell in the column)
                $wordEndRow = $row;
                $theRow = $row;
                while ($theRow <= $rows - 1) {
                    if ($this->puzzle[$theRow][$col]->isBlock) {
                        break;
                    }
                    $wordEndRow = $theRow;
                    $theRow++;
                }

                // Set the start and end rows for all cells in this column 
                foreach (range($wordStartRow, $wordEndRow) as $r) {
                    $this->puzzle[$r][$col]->downWordStartRow = $wordStartRow;
                    $this->puzzle[$r][$col]->downWordEndRow = $wordEndRow;
                }
            }
        }
    }

    /**
     * Classify and group the words by their lengths.
     * 
     * @return void
     */
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

    protected function placeWords(int $row, int $col, array &$puzzle, array $usedWords = []): bool
    {
        $this->recursionDepth++;
        $this->log(sprintf("::ENTER placeWords row=%d, col=%d", $row, $col));
        $this->log("\n" . $this->solutionAsText($puzzle));

        $this->writeDebugStatus($puzzle);

        $rows = count($puzzle);
        $cols = count($puzzle[0]);

        if ($row >= $rows || $col >= $cols) {
            $this->log("All cells processed");
            $this->recursionDepth--;
            return true; // All cells processed 
        }

        if ($this->puzzle[$row][$col]->isBlock === true) {
            $this->log(sprintf("Recursive call to skip block at row %d, col %d", $row, $col));

            // By default, try to continue to next cell in same row
            $nextCol = $col + 1;
            $newtRow = $row;

            if ($nextCol >= $cols) {
                // Continue to first cell in next row instead
                $nextCol = 0;
                $newtRow = $row + 1;
            }

            $result = $this->placeWords($newtRow, $nextCol, $puzzle, $usedWords);
            $this->recursionDepth--;
            return $result;
        }

        $neededWordLength = $this->getAvailableLengthAcross($row, $col, $puzzle);
        $candidateWords = $this->wordList[$neededWordLength];
        $wordisValid = false;


        while (count($candidateWords) > 0 && !$wordisValid) {

            // $this->log(sprintf("Candidate words:"));
            // $this->log(print_r($candidateWords, true));
            $this->tries++;

            [$word, $candidateWords] = $this->chooseWordForAcross($candidateWords, $usedWords);

            $this->log(sprintf("Chosen word: %s", $word));

            // $this->log(sprintf("Candidate words after:"));
            // $this->log(print_r($candidateWords, true));

            if ($word === null) {
                // Backtrack: no more words of the needed length
                $this->log(sprintf("No words of length %d available", $neededWordLength));
                $this->recursionDepth--;
                return false;
            }

            $this->placeTheWordAcross($puzzle, $row, $col, $word);

            $this->writeDebugStatus($puzzle);

            $this->log(sprintf("Puzzle after placing the word:"));
            $this->log("\n" . $this->solutionAsText($puzzle));

            $usedWordsIncludingCurrent = [...$usedWords, $word];

            $wordisValid = $this->checkWordsDown($row, $puzzle, $usedWordsIncludingCurrent);
            if (!$wordisValid) {
                // Backtrack: the word placed causes down words to be invalid
                $this->log("Word is NOT valid");
                $this->clearTheWordAcross($puzzle, $row, $col, $neededWordLength);
                continue;
            }

            $this->log("Word is valid");

            $nextCol = $col + $neededWordLength;
            if ($nextCol < $cols) {
                // Continue with next column in the same row
                $this->log(sprintf(">>>> Recursive call to next col at row %d, col %d", $row + 1, $nextCol + 1));
                if ($this->placeWords($row, $nextCol, $puzzle, $usedWordsIncludingCurrent)) {
                    // Success
                    $this->puzzle = $puzzle;
                    $usedWords[] = $word;
                    $this->recursionDepth--;
                    return true;
                } else {
                    // Backtrack: cannot place the word
                    $this->log(sprintf("Next col:Cannot place \"%s\" at row %d, col %d", $word, $row + 1, $col + 1));
                    $this->clearTheWordAcross($puzzle, $row, $col, $neededWordLength);
                    $wordisValid = false;
                    continue;
                }
            }

            // Continue with next row
            $this->log(sprintf(">>>> Recursive call to next row at row %d, col %d", $row + 2, 1));
            if ($this->placeWords($row + 1, 0, $puzzle, $usedWordsIncludingCurrent)) {
                // Success
                $this->puzzle = $puzzle;
                $usedWords[] = $word;
                $this->recursionDepth--;
                return true;
            } else {
                // Backtrack: cannot place the word
                $this->log(sprintf("Next row: Cannot place \"%s\" at row %d, col %d", $word, $row + 1, $col + 1));
                $this->clearTheWordAcross($puzzle, $row, $col, $neededWordLength);
                $wordisValid = false;
                continue;
            }
        }

        $this->log(sprintf("No more candidate words at row %d, col %d", $row + 1, $col + 1));
        $this->recursionDepth--;
        return false; // No valid word could be placed
    }

    protected function getAvailableLengthAcross(int $row, int $col, array $puzzle): int
    {
        $cols = count($puzzle[0]);

        $length = 0;
        for ($c = $col; $c < $cols; $c++) {
            if ($puzzle[$row][$c]->isBlock) {
                break;
            }
            $length++;
        }

        return $length;
    }

    /**
     * Place the word in the puzzle at the given row and column in across orientation.
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
    }

    private function clearTheWordAcross(array &$puzzle, int $row, int $col, int $len): void
    {
        for ($iCol = $col; $iCol < $col + $len; $iCol++) {
            $puzzle[$row][$iCol]->letter = null;
        }
    }

    protected array $wordDownValidCache = [];

    /**
     * Check if there are words in the list than can be placed on all columns of that row.
     * 
     * @param int $row
     * @param array $puzzle
     * @param array $usedWords
     * @return bool
     */
    private function checkWordsDown(int $row, array $puzzle, array $usedWords): bool
    {
        $this->log(sprintf("::checkWordsDown for row %d", $row + 1));
        $this->log("Used words:");
        $this->log(print_r($usedWords, true));

        $rows = count($puzzle);
        $cols = count($puzzle[0]);

        for ($col = 0; $col < $cols; $col++) {
            // Skip empty cells in this row (i.e. not yet filled)
            if (!$puzzle[$row][$col]->letter) {
                continue;
            }

            // Use precomputed start and end rows for this word
            $wordStartRow = $puzzle[$row][$col]->downWordStartRow;
            $wordEndRow = $puzzle[$row][$col]->downWordEndRow;

            // $this->log(sprintf("Word start row for col %d is %d", $col, $wordStartRow));
            // $this->log(sprintf("Word end row for col %d is %d", $col, $wordEndRow));

            // Get the word length needed and the starting letters
            $neededWordDownLength = $wordEndRow - $wordStartRow + 1;
            // if ($neededWordDownLength <= 1) {
            //     // No word to check
            //     continue;
            // }

            $wordStartsWith = '';
            for ($r = $wordStartRow; $r <= $wordEndRow; $r++) {
                if ($puzzle[$r][$col]->letter) {
                    $wordStartsWith .= $puzzle[$r][$col]->letter;
                }
            }

            $this->log(sprintf("Needed word length for col %d is %d", $col, $neededWordDownLength));
            $this->log(sprintf("Word starts with '%s'", $wordStartsWith));

            // Look if it is cached
            $cacheKey = sprintf(
                "row=%d,col=%d,starts=%s,used=%s",
                $row,
                $col,
                $wordStartsWith,
                md5(serialize($usedWords))
            );

            if (array_key_exists($cacheKey, $this->wordDownValidCache)) {
                $isValid = $this->wordDownValidCache[$cacheKey];
                if (!$isValid) {
                    return false;
                }
                continue;
            }

            $possibleWords = array_filter(
                $this->wordList[$neededWordDownLength],
                fn($w) => str_starts_with($w, $wordStartsWith)
                    && mb_strlen($w) === $neededWordDownLength
                    && !in_array($w, $usedWords, true)
            );

            // $this->log(sprintf("Possible words for col %d (length %d, starts with '%s'):", $col, $neededWordDownLength, $wordStartsWith));
            // $this->log(print_r($possibleWords, true));

            if (count($possibleWords) === 0) {
                $this->wordDownValidCache[$cacheKey] = false;
                return false;
            }

            $this->wordDownValidCache[$cacheKey] = true;
        }

        return true;
    }

    private function chooseWordForAcross(array $candidateWords, array $usedWords): array
    {
        $this->log("::chooseWordForAcross");

        // Filter out used words
        $availableWords = array_diff($candidateWords, $usedWords);

        if (empty($availableWords)) {
            return [null, $candidateWords];
        }

        // Choose a random word from the available words
        [$word, $wordIndex] = $this->random->choiceWithIndex($availableWords);
        array_splice($candidateWords, $wordIndex, 1);

        return [$word, $candidateWords];
    }
    /**
     * Assign orientation types and definition numbers and store placed words with definitions.
     * @return void
     */
    protected function completePlacedWords(): void
    {
        $definitionNumber = 1;

        $rows = count($this->puzzle);
        $cols = count($this->puzzle[0]);

        // Then to the ACCROSS words
        for ($row = 0; $row < $rows; $row++) {
            for ($col = 0; $col < $cols; $col++) {
                $cell = $this->puzzle[$row][$col];

                if ($cell->isBlock) {
                    continue;
                }

                if ($row === 0 || $this->puzzle[$row - 1][$col]->isBlock) {
                    // This cell is the start of a DOWN word
                    $theWord = $this->getWordAt($row, $col, self::ORIENTATION_DOWN);

                    // Save the word as placed DOWN
                    $placedWord = new CrosswordPlacedWord();
                    $placedWord->word = $theWord;
                    $placedWord->orientation = self::ORIENTATION_DOWN;
                    $placedWord->definitionNumber = $definitionNumber++;
                    $this->placedWords[] = $placedWord;
                    $this->puzzle[$row][$col]->definitionNumber = $placedWord->definitionNumber;
                }

                if ($col === 0 || $this->puzzle[$row][$col - 1]->isBlock) {
                    // This cell is the start of an ACROSS word                   
                    $theWord = $this->getWordAt($row, $col, self::ORIENTATION_ACROSS);

                    // Save the word as placed ACROSS
                    $placedWord = new CrosswordPlacedWord();
                    $placedWord->word = $theWord;
                    $placedWord->orientation = self::ORIENTATION_ACROSS;
                    $placedWord->definitionNumber = $cell->definitionNumber; // OLD, if any
                    $this->placedWords[] = $placedWord;
                    if ($cell->definitionNumber === null) {
                        $placedWord->definitionNumber = $definitionNumber++;
                        $this->puzzle[$row][$col]->definitionNumber = $placedWord->definitionNumber;
                    }
                }
            }
        }
    }

    protected function getWordAt(int $row, int $col, string $orientation): ?string
    {
        $rows = count($this->puzzle);
        $cols = count($this->puzzle[0]);

        if ($orientation === self::ORIENTATION_ACROSS) {
            $word = "";
            for ($c = $col; $c < $cols; $c++) {
                if ($this->puzzle[$row][$c]->isBlock) {
                    break;
                }
                $word .= $this->puzzle[$row][$c]->letter;
            }
            return $word;
        }

        if ($orientation === self::ORIENTATION_DOWN) {
            $word = "";
            for ($r = $row; $r < $rows; $r++) {
                if ($this->puzzle[$r][$col]->isBlock) {
                    break;
                }
                $word .= $this->puzzle[$r][$col]->letter;
            }
            return $word;
        }

        return null;
    }

    /**
     * Select one of the possible definitions for each placed word.
     * @return void
     */
    protected function createUsedDefinitionList(): void
    {
        $this->usedDefinitions = [];

        foreach ($this->placedWords as $placedWord) {
            // Get one of the possible definitions (separated by |)
            $definition = $this->wordsWithDefinitions[$placedWord->word] ?? '';
            $parts = explode("|", $definition);
            $theDefinition = $this->random->choice($parts);

            $this->usedDefinitions[$placedWord->word] = $theDefinition;
        }
    }

    public function puzzleAsText(): string
    {
        return $this->solutionAsText($this->puzzle, false);
    }

    public function solutionAsText(array $puzzle = [], $withLetters = true): string
    {
        if (empty($puzzle)) {
            $puzzle = $this->puzzle;
        }
        if (empty($puzzle)) {
            return '';
        }

        $border = '+' . str_repeat('----+', count($puzzle[0]));

        $lines = [];
        $lines[] = $border;

        foreach ($puzzle as $y => $row) {
            $cells1 = [];
            $cells2 = [];
            foreach ($row as $x => $cell) {
                $cell = $puzzle[$y][$x];
                $defNumber = '    ';
                $letter = '    ';

                if ($cell->isBlock) {
                    $defNumber = ' ## ';
                    $letter = ' ## ';
                }

                if ($cell->definitionNumber !== null) {
                    $defNumber = sprintf('%2d  ', $cell->definitionNumber);
                }
                if ($withLetters && $cell->letter !== null) {
                    $letter = '   ' . strtoupper($cell->letter);
                }

                $cells1[] = $defNumber;
                $cells2[] = $letter;
            }
            $lines[] = '|' . implode('|', $cells1) . '|';
            $lines[] = '|' . implode('|', $cells2) . '|';
            $lines[] = $border;
        }
        return implode("\n", $lines);
    }

    public function puzzleAsHtml(): string
    {
        return "<pre>" . htmlspecialchars($this->puzzleAsText()) . "</pre>";
    }

    public function solutionAsHtml(): string
    {
        return "<pre>" . htmlspecialchars($this->solutionAsText()) . "</pre>";
    }

    public function definitionListAsHtml($across = "Across", $down = "Down"): string
    {
        $text = "<pre>";
        $text .= $this->definitionListAsText($across, $down);
        $text .= "</pre>";
        return $text;
    }

    public function definitionListAsText($across = "Across", $down = "Down"): string
    {
        $definitionsFilter = fn(string $orientation) => array_filter(
            $this->placedWords,
            fn(CrosswordPlacedWord $pw) => $pw->orientation === $orientation
        );

        $downDefinitions = $definitionsFilter(self::ORIENTATION_DOWN);
        $acrossDefinitions = $definitionsFilter(self::ORIENTATION_ACROSS);

        $definitionFormatter = fn(CrosswordPlacedWord $pw) => sprintf(
            '%2d: %s.',
            $pw->definitionNumber,
            $this->usedDefinitions[$pw->word] ?? ''
        );

        $lines = [];

        $lines[] = sprintf("%s:", $down);
        foreach ($downDefinitions as $placedWord) {
            $lines[] = $definitionFormatter($placedWord);
        }

        $lines[] = "";
        $lines[] = sprintf("%s:", $across);
        foreach ($acrossDefinitions as $placedWord) {
            $lines[] = $definitionFormatter($placedWord);
        }

        return implode("\n", $lines);
    }

    private const DEBUG_LOG_FILE = '/tmp/trefoil/crossword-debug.log';
    private const DEBUG_STATUS_FILE = '/tmp/trefoil/crossword-debug-status.txt';



    protected function writeDebugStatus(array $tempPuzzle = [], bool $force = false): void
    {
        if (!$this->debugStatus) {
            return;
        }

        $puzzle = $tempPuzzle === [] ? $this->puzzle : $tempPuzzle;

        $elapsed = time() - $this->elapsedTime;
        if ($elapsed === $this->antElapsedTime && !$force) {
            return;
        }
        // if ($elapsed % 2 !== 0 && !$force) {
        //     return;
        // }

        $triesPerSecond = ($elapsed - $this->antElapsedTime) > 0
            ? ($this->tries - $this->antTries) / ($elapsed - $this->antElapsedTime)
            : 0;

        $totalTriesPerSecond = $elapsed > 0
            ? $this->tries / $elapsed
            : 0;

        $this->antTries = $this->tries;
        $this->antElapsedTime = $elapsed;

        $status = '';
        $status .= date('Y-m-d H:i:s') . "\n";
        $status .= sprintf("Recursion depth: %d\n", $this->recursionDepth);
        $status .= $this->solutionAsText($puzzle) . "\n";
        $status .= sprintf("Elapsed time: %s\n", date('H:i:s', $elapsed));
        $status .= sprintf(
            "Tries: %d  (Inst: %.2f tries/sec) (Total: %.2f tries/sec) \n",
            $this->tries,
            $triesPerSecond,
            $totalTriesPerSecond
        );

        file_put_contents(self::DEBUG_STATUS_FILE, $status);
    }

    protected function clearDebugLog(): void
    {
        if (file_exists(self::DEBUG_LOG_FILE)) {
            unlink(self::DEBUG_LOG_FILE);
        }
    }
    protected function log(string $message): void
    {
        if (!$this->debug) {
            return;
        }
        if ($this->useDebugFile) {
            file_put_contents(
                self::DEBUG_LOG_FILE,
                sprintf("[%d] %s\n", $this->recursionDepth, $message),
                FILE_APPEND
            );
        } else {
            echo sprintf("[%d] %s\n", $this->recursionDepth, $message);
        }
    }
}
