<?php

declare(strict_types=1);

namespace Trefoil\Helpers;

use Trefoil\Util\Toolkit;

class WordFillIn {

    public const DEFAULT_WORDS = [
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
        'Sunday',
    ];
    private const EMPTY_CELL = '.';
    private const EMPTY_CELL_HTML = '&nbsp;';
    private const CELL_TYPE_BEGIN = 'begin';
    private const CELL_TYPE_END = 'end';
    private const CELL_TYPE_LETTER = 'letter';
    private const CELL_TYPE_CROSS = 'cross';
    private const CELL_TYPE_HINT = 'hint';
    private const CELL_TYPE_START_WORD = 'start-word';
    private const CELL_TYPE_REVERSE_HORIZONTAL = 'reverse-horizontal';
    private const CELL_TYPE_REVERSE_VERTICAL = 'reverse-vertical';
    private const CELL_TYPE_NO_REVERSE_HORIZONTAL = 'no-reverse-horizontal';
    private const CELL_TYPE_NO_REVERSE_VERTICAL = 'no-reverse-vertical';
    private const DIRECTION_HORIZONTAL = 'horizontal';
    private const DIRECTION_VERTICAL = 'vertical';
    private const WORD_FAIL = 'fail';
    private const MIN_WORD_LENGTH = 3;
    private const DEFAULT_NUMBER_OF_WORDS = 10;
    private const MAX_NUMBER_OF_WORDS = 30;
    // 
    public const DIFFICULTY_EASY = 'easy';
    public const DIFFICULTY_MEDIUM = 'medium';
    public const DIFFICULTY_HARD = 'hard';
    public const DIFFICULTY_VERY_HARD = 'very-hard';
    //
    private const PUZZLE_TEXT_EMPTY_CELL = "\u{20}\u{20}"; // Two spaces
    private const PUZZLE_TEXT_FILLED_CELL = "\u{20}\u{20}\u{20DE}"; // Two spaces followd by the "Combining Enclosing Square" character
    private const PUZZLE_TEXT_LETTER_CELL = "\u{20}%s\u{20DE}"; // One spacefollowd by the letter and the "Combining Enclosing Square" character

    protected array $puzzle = [];
    protected array $crosses = [];
    protected PseudoRandom $random;
    //
    protected array $difficulties = [
        self::DIFFICULTY_EASY => [
            'min-length' => 3,
            'max-length' => 10,
            'use-reverse' => false,
            'forced-reverse' => false,
            'hints' => '50%'
        ],
        self::DIFFICULTY_MEDIUM => [
            'min-length' => 3,
            'max-length' => 10,
            'use-reverse' => false,
            'forced-reverse' => false,
            'hints' => '30%'
        ],
        self::DIFFICULTY_HARD => [
            'min-length' => 6,
            'max-length' => 12,
            'use-reverse' => true,
            'forced-reverse' => false,
            'hints' => '20%'
        ],
        self::DIFFICULTY_VERY_HARD => [
            'min-length' => 6,
            'max-length' => 15,
            'use-reverse' => true,
            'forced-reverse' => true,
            'hints' => '10%'
        ],
    ];

    /**
     * @var array|string[]
     */
    protected array $words;
    protected int $rows;
    protected int $columns;

    /**
     * @var array|string[]
     */
    protected array $errors = [];

    /**
     * @var array|string[]
     */
    protected array $warnings = [];
    protected $cellsList = [];
    protected $totalTries = 0;

    public function __construct()
    {
        $this->random = new PseudoRandom();
    }

    public function setRandomSeed(int $seed)
    {
        $this->random->setRandomSeed($seed);
    }

    public function generate(
            int $rows = 20,
            int $columns = 20,
            array $words = self::DEFAULT_WORDS,
            int $numberOfWords = 0,
            string $difficulty = self::DIFFICULTY_EASY
    ): bool
    {
        $this->words = array_map('mb_strtoupper', $words);
        $this->rows = $rows;
        $this->columns = $columns;
        $this->errors = [];

        if ($numberOfWords <= 0) {
            if (count($words) > self::DEFAULT_NUMBER_OF_WORDS) {
                $numberOfWords = self::DEFAULT_NUMBER_OF_WORDS;
                $this->selectRandomWords($numberOfWords, $difficulty, max($rows, $columns));
            }
        } elseif ($numberOfWords < count($words)) {
            $this->selectRandomWords($numberOfWords, $difficulty, max($rows, $columns));
        }

        if (!$this->checkWords()) {
            return false;
        }

        $this->initializePuzzle();

        if (!$this->placeWords($difficulty)) {
            return false;
        }

        $this->placeHints($difficulty);

        $this->centerPuzzle();

        return true;
    }

    protected function selectRandomWords(
            int $numberOfWords,
            string $difficulty,
            int $maxLength
    )
    {
        $newWords = [];

        for ($i = 0; $i < $numberOfWords; $i++) {
            $tries = 100;
            do {
                $word = $this->words[$this->random->getRandomInt(0, count($this->words) - 1)];
                $tries--;
                $isValid = !in_array($word, $newWords) && mb_strlen($word) >= $this->difficulties[$difficulty]['min-length'] && mb_strlen($word)
                        <= $this->difficulties[$difficulty]['max-length'] && mb_strlen($word) <= $maxLength;
            } while (!$isValid && $tries > 0);

            if ($tries >= 0) {
                $newWords[] = $word;
            }
        }

        if (count($newWords) < $numberOfWords) {
            $this->errors[] = sprintf(
                    'Could not select %s random words. Just %s selected.', count($newWords), $numberOfWords
            );
        }

        $this->words = $newWords;
    }

    protected function checkWords(): bool
    {
        foreach ($this->words as $word) {
            if (mb_strlen($word) > $this->rows && mb_strlen($word) > $this->columns) {
                $this->errors[] = sprintf(
                        'Word "%s" would not fit in %s rows by %s columns.', $word, $this->rows, $this->columns
                );

                return false;
            }
        }

        return true;
    }

    protected function initializePuzzle()
    {
        $this->puzzle = [];
        $this->crosses = [];
        $emptyCell = $this->emptyCell();
        for ($row = 0; $row < $this->rows; $row++) {
            for ($column = 0; $column < $this->columns; $column++) {
                $this->puzzle[$row][$column] = $emptyCell;
            }
        }
    }

    protected function emptyCell(): array
    {
        return [
            'letter' => self::EMPTY_CELL,
            'types' => [],
            'directions' => [],
            'words' => []
        ];
    }

    protected function centerPuzzle()
    {
        $firstNonEmptyRow = 0;
        $lastNonEmptyRow = $this->rows - 1;
        $firstNonEmptyColumn = 0;
        $lastNonEmptyColumn = $this->columns - 1;

        // Find first non-empty row
        for ($row = 0; $row < $this->rows; $row++) {
            $isRowEmpty = true;
            for ($column = 0; $column < $this->columns; $column++) {
                if ($this->puzzle[$row][$column]['letter'] !== self::EMPTY_CELL) {
                    $isRowEmpty = false;
                    break;
                }
            }
            if (!$isRowEmpty) {
                $firstNonEmptyRow = $row;
                break;
            }
        }

        // Find last non-empty row
        for ($row = $this->rows - 1; $row > 0; $row--) {
            $isRowEmpty = true;
            for ($column = 0; $column < $this->columns; $column++) {
                if ($this->puzzle[$row][$column]['letter'] !== self::EMPTY_CELL) {
                    $isRowEmpty = false;
                    break;
                }
            }
            if (!$isRowEmpty) {
                $lastNonEmptyRow = $row;
                break;
            }
        }

        // Find first non-empty column
        for ($column = 0; $column < $this->columns; $column++) {
            $isColumnEmpty = true;
            for ($row = 0; $row < $this->rows; $row++) {
                if ($this->puzzle[$row][$column]['letter'] !== self::EMPTY_CELL) {
                    $isColumnEmpty = false;
                    break;
                }
            }
            if (!$isColumnEmpty) {
                $firstNonEmptyColumn = $column;
                break;
            }
        }

        // Find last non-empty column
        for ($column = $this->columns - 1; $column > 0; $column--) {
            $isColumnEmpty = true;
            for ($row = 0; $row < $this->rows; $row++) {
                if ($this->puzzle[$row][$column]['letter'] !== self::EMPTY_CELL) {
                    $isColumnEmpty = false;
                    break;
                }
            }
            if (!$isColumnEmpty) {
                $lastNonEmptyColumn = $column;
                break;
            }
        }

        // Remove empty surrounding rows and columns
        $newPuzzle = [];
        $newRow = 0;
        for ($row = $firstNonEmptyRow; $row <= $lastNonEmptyRow; $row++) {
            $newColumn = 0;
            for ($column = $firstNonEmptyColumn; $column <= $lastNonEmptyColumn; $column++) {
                $newPuzzle[$newRow][$newColumn] = $this->puzzle[$row][$column];
                $newColumn++;
            }
            $newRow++;
        }

        $newRows = $lastNonEmptyRow - $firstNonEmptyRow + 1;
        $newColumns = $lastNonEmptyColumn - $firstNonEmptyColumn + 1;

        $rowsToAdd = $this->rows - $newRows;
        $rowsToAddTop = intval(floor($rowsToAdd / 2));

        $columnsToAdd = $this->columns - $newColumns;
        $columnsToAddLeft = intval(floor($columnsToAdd / 2));

        $emptyCell = $this->emptyCell();

        // Create an empty row
        $newRowToAdd = array_pad([], $newColumns, $emptyCell);

        // Add as many rows as needed at the top and bottom
        $newPuzzle = array_pad($newPuzzle, -($newRows + $rowsToAddTop), $newRowToAdd);
        $newPuzzle = array_pad($newPuzzle, $newRows + $rowsToAdd, $newRowToAdd);

        // Add as many columns as needed at the left and right
        for ($row = 0; $row < count($newPuzzle); $row++) {
            $newPuzzle[$row] = array_pad($newPuzzle[$row], -($newColumns + $columnsToAddLeft), $emptyCell);
            $newPuzzle[$row] = array_pad($newPuzzle[$row], $newColumns + $columnsToAdd, $emptyCell);
        }

        $this->puzzle = $newPuzzle;
    }

    protected function placeHints(string $difficulty)
    {

        $crosses = array_values($this->crosses);
        $percentHints = str_replace('%', '', $this->difficulties[$difficulty]['hints']);
        $numHints = intval(floor(count($crosses) * $percentHints / 100));

        for ($i = 0; $i < $numHints; $i++) {
            /* Ensure the same word doesn't get more than one hint.
             */

            $num = $this->random->getRandomInt(0, count($crosses) - 1);
            $cross = $crosses[$num];

            foreach ($cross['words'] as $word) {
                // Remove other crosses with the same word
                foreach ($crosses as $key => $otherCross) {
                    if (in_array($word, $otherCross['words'])) {
                        unset($crosses[$key]);
                    }
                }
            }

            array_splice($crosses, $num, 1);
            $this->puzzle[$cross['row']][$cross['column']]['types'][] = self::CELL_TYPE_HINT;

            if (count($crosses) === 0) {
                break;
            }
        }
    }

    /**
     * Place all the words on the board.
     * If the words cannot be placed, try removing one word each time.
     * 
     * @return bool Success
     */
    protected function placeWords(string $difficulty): bool
    {

        $this->totalTries = 0;

        $words = $this->normalizeWords($this->words);

        $wordsBeforeNormalization = array_combine($words, $this->words);

        // Sort words by length descending
        usort($words, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        do {
            $puzzleDone = $this->tryPlaceWords($words, $difficulty);
            if (!$puzzleDone) {
                // Remove the largest word
                $word = array_shift($words);
                $this->warnings[] = sprintf('Removed word "%s" for the puzzle to fit.', $word);

                // Remove it also from the original word list
                $originalWord = $wordsBeforeNormalization[$word];
                $pos = array_search($originalWord, $this->words);
                array_splice($this->words, $pos, 1);
            }
        } while (!$puzzleDone && count($words) > 0);

//        $this->words = $words;

        return $puzzleDone;
    }

    /**
     * Try placing all the words on the board. 
     * If the words cannot be placed, try moving the first word to the lastest position.
     * 
     * @return bool Success
     */
    protected function tryPlaceWords(
            array $words,
            string $difficulty
    ): bool
    {
        $backupPuzzle = $this->puzzle;
        $backupCrosses = $this->crosses;

        $puzzleDone = false;

        for ($i = 0; $i < count($words); $i++) {
            $puzzleDone = $this->tryPlaceWordsInCertainOrder($words, $difficulty);
            if ($puzzleDone) {
                break;
            }

            $this->puzzle = $backupPuzzle;
            $this->crosses = $backupCrosses;

            // Move the first word to the last position to retry
            $word = array_shift($words);
            $words[] = $word;
        }

        return $puzzleDone;
    }

    /**
     * Try placing words on the board. 
     * This could fail if one of the words cannot be placed.
     * 
     * @return bool Success
     */
    protected function tryPlaceWordsInCertainOrder(
            array $words,
            string $difficulty
    ): bool
    {
        $maxPuzzleTries = 100;
        $puzzleTries = 0;

        $this->errors = [];

        do {
            $backupPuzzle = $this->puzzle;
            $backupCrosses = $this->crosses;
            $puzzleDone = true;
            $unplacedWords = array_flip($words);

            $directions = [];

            foreach ($words as $index => $word) {

                $direction = $this->placeWord($this->clearWord($word), $difficulty, $index == 0);

                if ($direction === self::WORD_FAIL) {
                    $puzzleTries++;
                    $this->puzzle = $backupPuzzle;
                    $this->crosses = $backupCrosses;
                    $puzzleDone = false;

                    break;
                }

                $directions[$direction] = true;

                unset($unplacedWords[$word]);
            }

            // Validate that we have all the 2 different directions in the puzzle
            if ($puzzleDone && count($words) >= 2 && count($directions) < 2) {
                $puzzleTries++;
                $this->puzzle = $backupPuzzle;
                $this->crosses = $backupCrosses;
                $puzzleDone = false;
            }
        } while ($puzzleTries <= $maxPuzzleTries && !$puzzleDone);

        if (!$puzzleDone) {
            $this->errors[] = 'Some words could not be placed.';
            foreach ($unplacedWords as $word => $key) {
                $this->errors[] = sprintf('Word "%s" could not be placed.', $word);
            }
        }

        return $puzzleDone;
    }

    protected function normalizeWords(array $words): array
    {
        $newWords = [];

        foreach ($this->words as $word) {
            // "Ññ", "Çç", and "Üü" are preserved (Spanish and Catalan languages)
            $a = preg_split('//u', 'àáâãäèéêëìíîïòóôõöùúûýÿÀÁÂÃÄÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÝ');
            $b = preg_split('//u', 'aaaaaeeeeiiiiooooouuuyyAAAAAEEEEIIIIOOOOOUUUY');

            $newWords[] = str_replace($a, $b, $word);
        }

        return $newWords;
    }

    /**
     * @return string Direction or self::WORD_FAIL
     */
    protected function placeWord(
            string $word,
            string $difficulty,
            bool $isFirstWord
    ): string
    {
        $wordTries = 0;
        
        /* The optimum number of word tries has been determined experimentally
         * for optimizing the elapsed time.
         */
        $maxWordTries = 5 * $this->rows * $this->columns;

        do {
            $backupPuzzleBeforeWord = $this->puzzle;
            $backupCrossesBeforeWord = $this->crosses;

            $this->totalTries++;

            $direction = $this->tryPlaceTheWord($word, $difficulty, $isFirstWord);

            if ($direction === self::WORD_FAIL) {
                $wordTries++;
                $this->puzzle = $backupPuzzleBeforeWord;
                $this->crosses = $backupCrossesBeforeWord;
            }
        } while ($wordTries <= $maxWordTries && $direction === self::WORD_FAIL);

        return $direction;
    }

    /**
     * @return string Direction or self::WORD_FAIL
     */
    protected function tryPlaceTheWord(
            string $word,
            string $difficulty,
            bool $isFirstWord
    ): string
    {

        $direction = [self::DIRECTION_HORIZONTAL, self::DIRECTION_VERTICAL][$this->random->getRandomInt(0, 1)];

        [$initialRow, $initialCol, $maxRow, $maxCol, $incRow, $incCol] = $this->calculateWordBounds($word, $direction);

        $reverse = $this->calculateReverse($difficulty);

        $theWord = mb_strtoupper($reverse ? Toolkit::mb_strrev($word) : $word);

        $theRow = $initialRow;
        $theCol = $initialCol;

        $wordCrosses = 0;

        for ($i = 0; $i < mb_strlen($theWord); $i++) {
            $theLetter = mb_substr($theWord, $i, 1);
            $isFirstLetter = ($i === 0);
            $isLastLetter = ($i === mb_strlen($theWord) - 1);

            // Check if the letter can be placed
            if ($theRow < 0 || $theRow > $maxRow || $theCol < 0 || $theCol > $maxCol ||
                    ($this->puzzle[$theRow][$theCol]['letter'] !== self::EMPTY_CELL &&
                    $this->puzzle[$theRow][$theCol]['letter'] !== $theLetter)) {

                return self::WORD_FAIL;
            }

            // Check if it is a cross
            $isWordCross = false;
            if ($this->puzzle[$theRow][$theCol]['letter'] === $theLetter) {
                $isWordCross = true;
                $wordCrosses++;
            }

            // Check that the horizontal word is not contigous to another one
            if ($direction === self::DIRECTION_HORIZONTAL) {
                if (
                        ($theCol > 0 && $isFirstLetter && $this->puzzle[$theRow][$theCol - 1]['letter'] !== self::EMPTY_CELL) ||
                        ($theCol < $this->columns - 1 && $isLastLetter && $this->puzzle[$theRow][$theCol + 1]['letter'] !== self::EMPTY_CELL) ||
                        (!$isWordCross && $theRow > 0 && $this->puzzle[$theRow - 1][$theCol]['letter'] !== self::EMPTY_CELL) ||
                        (!$isWordCross && $theRow < $this->rows - 1 && $this->puzzle[$theRow + 1][$theCol]['letter'] !== self::EMPTY_CELL)
                ) {
                    return self::WORD_FAIL;
                }
            }

            // Check that the vertical word is not contigous to another one
            if ($direction === self::DIRECTION_VERTICAL) {
                if (
                        ($theRow > 0 && $isFirstLetter && $this->puzzle[$theRow - 1][$theCol]['letter'] !== self::EMPTY_CELL) ||
                        ($theRow < $this->rows - 1 && $isLastLetter && $this->puzzle[$theRow + 1][$theCol]['letter'] !== self::EMPTY_CELL) ||
                        (!$isWordCross && $theCol > 0 && $this->puzzle[$theRow][$theCol - 1]['letter'] !== self::EMPTY_CELL) ||
                        (!$isWordCross && $theCol < $this->columns - 1 && $this->puzzle[$theRow][$theCol + 1]['letter'] !== self::EMPTY_CELL)
                ) {
                    return self::WORD_FAIL;
                }
            }

            // Detect HERE if this is a cross BEFORE overwriting it!
            $isCross = $this->puzzle[$theRow][$theCol]['letter'] !== self::EMPTY_CELL;

            // Now set the cell
            $this->puzzle[$theRow][$theCol]['letter'] = $theLetter;
            $this->puzzle[$theRow][$theCol]['types'][] = self::CELL_TYPE_LETTER;
            $this->puzzle[$theRow][$theCol]['words'][] = $word;

            if ($isCross) {
                // Save the cross data with all words
                $this->puzzle[$theRow][$theCol]['types'][] = self::CELL_TYPE_CROSS;
                $this->crosses[sprintf('%s-%s-%s', $theRow, $theCol, $word)] = [
                    'row' => $theRow,
                    'column' => $theCol,
                    'words' => $this->puzzle[$theRow][$theCol]['words']
                ];
            }

            $theDirection = $direction;
            if ($i == 0) {
                $theDirection .= '-begin';
                if (!$reverse) {
                    $this->puzzle[$theRow][$theCol]['types'][] = self::CELL_TYPE_START_WORD;
                    $this->puzzle[$theRow][$theCol]['types'][] = ($direction === self::DIRECTION_HORIZONTAL ?
                            self::CELL_TYPE_NO_REVERSE_HORIZONTAL :
                            self::CELL_TYPE_NO_REVERSE_VERTICAL);
                } else {
                    $this->puzzle[$theRow][$theCol]['types'][] = ($direction === self::DIRECTION_HORIZONTAL ?
                            self::CELL_TYPE_REVERSE_HORIZONTAL :
                            self::CELL_TYPE_REVERSE_VERTICAL);
                }
            } elseif ($i == mb_strlen($theWord) - 1) {
                $theDirection .= '-end';
                if ($reverse) {
                    $this->puzzle[$theRow][$theCol]['types'][] = self::CELL_TYPE_START_WORD;
                    $this->puzzle[$theRow][$theCol]['types'][] = ($direction === self::DIRECTION_HORIZONTAL ?
                            self::CELL_TYPE_REVERSE_HORIZONTAL :
                            self::CELL_TYPE_REVERSE_VERTICAL);
                }
            }

            $this->puzzle[$theRow][$theCol]['directions'][] = $theDirection;

            $theRow += $incRow;
            $theCol += $incCol;
        }

        // Check that the word is crossed at least once
        if (!$isFirstWord && ($wordCrosses === 0 || $wordCrosses > 1)) {
            return self::WORD_FAIL;
        }

        return $direction;
    }

    protected function calculateWordBounds(
            string $word,
            string $direction
    )
    {
        $initialRow = 0;
        $initialCol = 0;
        $maxRow = $this->rows - 1;
        $maxCol = $this->columns - 1;
        $incRow = 0;
        $incCol = 0;

        switch ($direction) {
            case self::DIRECTION_HORIZONTAL:
                $initialRow = $this->random->getRandomInt(0, $this->rows - 1);
                $initialCol = $this->random->getRandomInt(0, $this->columns - mb_strlen($word));

                $maxRow = $initialRow;
                $incCol = 1;
                $maxCol = $initialCol + mb_strlen($word) - 1;
                break;
            case self::DIRECTION_VERTICAL:
                $initialRow = $this->random->getRandomInt(0, $this->rows - mb_strlen($word));
                $initialCol = $this->random->getRandomInt(0, $this->columns - 1);

                $incRow = 1;
                $maxRow = $initialRow + mb_strlen($word) - 1;
                $maxCol = $initialCol;
                break;
        }

        return [$initialRow, $initialCol, $maxRow, $maxCol, $incRow, $incCol];
    }

    protected function calculateReverse(string $difficulty): bool
    {
        if (!$this->difficulties[$difficulty]['use-reverse']) {
            $reverse = false;
        } elseif ($this->difficulties[$difficulty]['forced-reverse']) {
            $reverse = true;
        } else {
            $reverse = $this->random->getRandomInt(0, 1) == 1;
        }

        return $reverse;
    }

    protected function clearWord($word)
    {
        return preg_replace('/[^[:alnum:]]/ui', '', $word);
    }

    public function puzzleAsText(): string
    {
        $text = '';

        for ($row = 0; $row < $this->rows; $row++) {
            for ($column = 0; $column < $this->columns; $column++) {
                $cell = $this->puzzle[$row][$column];
                $cellText = self::PUZZLE_TEXT_EMPTY_CELL;
                if (in_array(self::CELL_TYPE_HINT, $cell['types'])) {
                    $cellText = sprintf(self::PUZZLE_TEXT_LETTER_CELL, $cell['letter']);
                } elseif ($cell['letter'] !== self::EMPTY_CELL) {
                    $cellText = self::PUZZLE_TEXT_FILLED_CELL;
                }
                $text .= $cellText;
            }
            $text .= "\n";
        }

        return substr($text, 0, -1);
    }

    public function solutionAsText(): string
    {
        $text = '';

        for ($row = 0; $row < $this->rows; $row++) {
            for ($column = 0; $column < $this->columns; $column++) {
                $cell = $this->puzzle[$row][$column];
                $text .= $cell['letter'] == self::EMPTY_CELL ?
                        self::PUZZLE_TEXT_EMPTY_CELL :
                        sprintf(self::PUZZLE_TEXT_LETTER_CELL, $cell['letter']);
            }
            $text .= "\n";
        }

        return substr($text, 0, -1);
    }

    public function puzzleAsHtml(): string
    {
        $table = new Table();

        for ($row = 0; $row < $this->rows; $row++) {
            for ($column = 0; $column < $this->columns; $column++) {
                $cell = $this->puzzle[$row][$column];

                $letter = '&nbsp;';
                if (in_array(self::CELL_TYPE_HINT, $cell['types'])) {
                    $letter = $cell['letter'];
                }

                $contents = <<<HTML
                <div class="cell-inner">$letter 
                    <div class="cell-arrow-down"></div>
                    <div class="cell-arrow-up"></div>
                    <div class="cell-arrow-right"></div>
                    <div class="cell-arrow-left"></div>
                </div>
                HTML;

                $classes = [];

                $cellTypes = array_unique($cell['types']);
                $cellDirections = array_unique($cell['directions']);

                foreach ($cellTypes as $cellType) {
                    $classes[] = 'cell-' . $cellType;
                }
                foreach ($cellDirections as $cellDirection) {
                    $classes[] = 'cell-' . $cellDirection;
                }

                $attributes = $classes ? ['class' => implode(' ', $classes)] : [];

                $table->addBodyCell($contents, $row, $column, $attributes);
            }
        }

        return $table->toHtml();
    }

    public function solutionAsHtml(): string
    {
        $table = new Table();

        for ($row = 0; $row < $this->rows; $row++) {
            for ($column = 0; $column < $this->columns; $column++) {
                $cell = $this->puzzle[$row][$column];

                $letter = '&nbsp;';

                if ($cell['letter'] !== self::EMPTY_CELL) {
                    $letter = $cell['letter'];
                }

                $contents = <<<HTML
                <div class="cell-inner">$letter 
                    <div class="cell-arrow-down"></div>
                    <div class="cell-arrow-up"></div>
                    <div class="cell-arrow-right"></div>
                    <div class="cell-arrow-left"></div>
                </div>
                HTML;

                $classes = [];

                $cellTypes = array_unique($cell['types']);
                $cellDirections = array_unique($cell['directions']);

                foreach ($cellTypes as $cellType) {
                    $classes[] = 'cell-' . $cellType;
                }
                foreach ($cellDirections as $cellDirection) {
                    $classes[] = 'cell-' . $cellDirection;
                }

                $attributes = $classes ? ['class' => implode(' ', $classes)] : [];

                $table->addBodyCell($contents, $row, $column, $attributes);
            }
        }

        return $table->toHtml();
    }

    public function wordListAsText(
            bool $sorted = true,
            string $sortLocale = "utf8"
    ): string
    {
        $words = $this->words;
        if ($sorted) {
            $collator = collator_create($sortLocale);
            $collator->sort($words);
        }

        return implode("\n", $words);
    }

    public function wordListAsHtml(
            bool $sorted = true,
            int $chunks = 1,
            string $sortLocale = "utf8"
    ): string
    {
        $words = $this->words;
        if ($sorted) {
            $collator = collator_create($sortLocale);
            $collator->sort($words);
        }

        $output = '';
        $itemChunks = array_chunk($words, intval(ceil(count($words) / $chunks)));
        foreach ($itemChunks as $index => $itemChunk) {
            $output .= sprintf('<ul class="chunk-%s-%s"><li>', $index + 1, $chunks)
                    . implode("</li><li>", $itemChunk)
                    . '</li></ul>';
        }

        return $output;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function getTotalTries(): int
    {
        return $this->totalTries;
    }

}
