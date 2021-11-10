<?php declare(strict_types=1);

namespace Trefoil\Helpers;

/**
 *
 */
class WordSearch
{
    public const DEFAULT_WORDS = [
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
        'Sunday',
    ];

    const FILLER_LETTERS_ENGLISH = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    private const EMPTY_CELL = '.';
    private const EMPTY_CELL_HTML = '&nbsp;';

    private const CELL_TYPE_BEGIN = 'begin';
    private const CELL_TYPE_END = 'end';
    private const CELL_TYPE_LETTER = 'letter';
    private const CELL_TYPE_FILLER = 'filler';

    private const DIRECTION_HORIZONTAL = 'horizontal';
    private const DIRECTION_HORIZONTAL_BEGIN = 'horizontal-begin';
    private const DIRECTION_HORIZONTAL_END = 'horizontal-end';
    private const DIRECTION_VERTICAL = 'vertical';
    private const DIRECTION_VERTICAL_BEGIN = 'vertical-begin';
    private const DIRECTION_VERTICAL_END = 'vertical-end';
    private const DIRECTION_DIAGONAL_DOWN = 'diagonal-down';
    private const DIRECTION_DIAGONAL_DOWN_BEGIN = 'diagonal-down-begin';
    private const DIRECTION_DIAGONAL_DOWN_END = 'diagonal-down-end';
    private const DIRECTION_DIAGONAL_UP = 'diagonal-up';
    private const DIRECTION_DIAGONAL_UP_BEGIN = 'diagonal-up-begin';
    private const DIRECTION_DIAGONAL_UP_END = 'diagonal-up-end';

    private const WORD_FAIL = 'fail';

    protected array $puzzle = [];
    protected int $randomSeed = 0;

    /**
     * @var array|string[]
     */
    protected array $words;
    protected int $rows;
    protected int $columns;

    public function generate(int    $rows = 10,
                             int    $columns = 10,
                             array  $words = self::DEFAULT_WORDS,
                             string $fillerLetters = self::FILLER_LETTERS_ENGLISH): bool
    {
        $this->words = array_map('strtoupper', $words);
        $this->rows = $rows;
        $this->columns = $columns;

        if (!$this->checkWords()) {
            return false;
        }

        $this->initializePuzzle();

        if (!$this->placeWords()) {
            return false;
        }

        $this->placeFillerLetters($fillerLetters);

        return true;
    }

    protected function checkWords(): bool
    {
        foreach ($this->words as $word) {
            if (strlen($word) > $this->rows || strlen($word) > $this->columns) {
                return false;
            }
        }

        return true;
    }

    protected function initializePuzzle()
    {
        $this->puzzle = [];
        for ($row = 0; $row < $this->rows; $row++) {
            for ($column = 0; $column < $this->columns; $column++) {
                $this->puzzle[$row][$column] = [
                    'letter'     => self::EMPTY_CELL,
                    'types'      => [],
                    'directions' => [],
                ];
            }
        }

    }

    protected function placeWords(): bool
    {
        $words = $this->words;

        // Sort words by length descending
        usort(
            $words,
            fn($a,
               $b) => strlen($b) <=> strlen($a));

        $maxPuzzleTries = 100;

        $puzzleTries = 0;

        do {
            $backupPuzzleBeforePuzzle = $this->puzzle;
            $puzzleDone = true;

//            echo "==== PUZZLE start try: $puzzleTries\n";

            $directions = [];

            foreach ($words as $word) {
                $direction = $this->placeWord($word);

                if ($direction === self::WORD_FAIL) {
                    $puzzleTries++;
                    $this->puzzle = $backupPuzzleBeforePuzzle;
                    $puzzleDone = false;

//                    echo "==== PUZZLE failed \n";

                    break;
                }

                $directions[$direction] = true;
            }

            if ($puzzleDone && count($words) >= 4 && count($directions) < 4) {
                $puzzleTries++;
                $this->puzzle = $backupPuzzleBeforePuzzle;
                $puzzleDone = false;
            }

        } while ($puzzleTries <= $maxPuzzleTries && !$puzzleDone);

        print_r($directions);

        return $puzzleDone;
    }

    protected function placeFillerLetters(string $letters)
    {
        for ($row = 0; $row < $this->rows; $row++) {
            for ($column = 0; $column < $this->columns; $column++) {
                if ($this->puzzle[$row][$column]['letter'] === self::EMPTY_CELL) {
                    $this->puzzle[$row][$column] = [
                        'letter'     => $letters[$this->getRandomInt(0, strlen($letters) - 1)],
                        'types'      => [self::CELL_TYPE_FILLER],
                        'directions' => [],
                    ];
                }
            }
        }
    }

    /**
     * @param string $word
     * @return string Direction or self::WORD_FAIL
     */
    protected function placeWord(string $word): string
    {
        $wordTries = 0;
        $maxWordTries = 100;

        do {
//            echo "====== WORD start try: $word $wordTries\n";
            $backupPuzzleBeforeWord = $this->puzzle;

            $direction = [
                             self::DIRECTION_HORIZONTAL,
                             self::DIRECTION_VERTICAL,
                             self::DIRECTION_DIAGONAL_DOWN,
                             self::DIRECTION_DIAGONAL_UP,
                         ][$this->getRandomInt(0, 3)];

            $rows = $this->rows;
            $columns = $this->columns;

            $initialRow = 0;
            $initialCol = 0;
            $maxRow = $rows - 1;
            $maxCol = $columns - 1;
            $incRow = 0;
            $incCol = 0;

            switch ($direction) {
                case self::DIRECTION_HORIZONTAL:
                    $initialRow = $this->getRandomInt(0, $rows - 1);
                    $initialCol = $this->getRandomInt(0, $columns - strlen($word));

                    $incCol = 1;
                    $maxCol = $initialCol + strlen($word);
                    break;
                case self::DIRECTION_VERTICAL:
                    $initialRow = $this->getRandomInt(0, $rows - strlen($word));
                    $initialCol = $this->getRandomInt(0, $columns - 1);

                    $incRow = 1;
                    $maxRow = $initialRow + strlen($word);
                    break;
                case self::DIRECTION_DIAGONAL_DOWN:
                    $initialRow = $this->getRandomInt(0, $rows - strlen($word));
                    $initialCol = $this->getRandomInt(0, $columns - strlen($word));

                    $incRow = 1;
                    $maxRow = $initialRow + strlen($word);
                    $incCol = 1;
                    $maxCol = $initialCol + strlen($word);
                    break;
                case self::DIRECTION_DIAGONAL_UP:
                    $initialRow = $this->getRandomInt(strlen($word) - 1, $rows - 1);
                    $initialCol = $this->getRandomInt(0, $columns - strlen($word));

                    $incRow = -1;
                    $maxRow = 0;
                    $incCol = 1;
                    $maxCol = $initialCol - strlen($word);
                    break;
            }

            $reverse = $this->getRandomInt(0, 1) == 1;
            $theWord = strtoupper($reverse ? strrev($word) : $word);

            $theRow = $initialRow;
            $theCol = $initialCol;

            $wordDone = true;

            for ($i = 0; $i < strlen($theWord); $i++) {

                if ($theRow < 0 || $theRow > $rows - 1 || $theCol < 0 || $theCol > $columns - 1 ||
                    $this->puzzle[$theRow][$theCol]['letter'] !== self::EMPTY_CELL &&
                    $this->puzzle[$theRow][$theCol]['letter'] !== $theWord[$i]) {
                    $wordTries++;
                    $this->puzzle = $backupPuzzleBeforeWord;
                    $wordDone = false;

//                    echo "====== WORD failed: $word\n";
                    break;
                }

                $this->puzzle[$theRow][$theCol]['letter'] = $theWord[$i];
                $this->puzzle[$theRow][$theCol]['types'][] = self::CELL_TYPE_LETTER;

//                $type = '';
                $theDirection = $direction;
                if ($i == 0) {
//                    $type = $reverse ? self::CELL_TYPE_END : self::CELL_TYPE_BEGIN;
//                    $theDirection .= $reverse ? '-end' : '-begin';
                    $theDirection .= '-begin';
                } elseif ($i == strlen($theWord) - 1) {
//                    $type = $reverse ? self::CELL_TYPE_BEGIN : self::CELL_TYPE_END;
//                    $theDirection .= $reverse ? '-begin' : '-end';
                    $theDirection .=  '-end';
                }
//                $this->puzzle[$theRow][$theCol]['types'][] = $type;



                $this->puzzle[$theRow][$theCol]['directions'][] = $theDirection;

                if ($theRow !== $maxRow) {
                    $theRow += $incRow;
                }

                if ($theCol !== $maxCol) {
                    $theCol += $incCol;
                }
            }
        } while ($wordTries <= $maxWordTries && !$wordDone);

        return $wordDone ? $direction : self::WORD_FAIL;
    }

    protected function getRandomInt(int $min = 0,
                                    int $max = 9999999): int
    {
        if ($this->randomSeed == 0) {
            $this->setRandomSeed(mt_rand());
        }
        $this->randomSeed = ($this->randomSeed * 125) % 2796203;

        return $this->randomSeed % ($max - $min + 1) + $min;
    }

    public function setRandomSeed(int $seed)
    {
        $this->randomSeed = abs($seed) % 9999999 + 1;;
        $this->getRandomInt();
    }

    public function puzzleAsText(): string
    {
        $text = '';

        for ($row = 0; $row < $this->rows; $row++) {
            for ($column = 0; $column < $this->columns; $column++) {
                $text .= $this->puzzle[$row][$column]['letter'];
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
                $table->addBodyCell($this->puzzle[$row][$column]['letter'], $row, $column);
            }
        }

        return $table->toHtml();
    }

    public function solutionAsText(): string
    {
        $text = '';

        for ($row = 0; $row < $this->rows; $row++) {
            for ($column = 0; $column < $this->columns; $column++) {
                $cell = $this->puzzle[$row][$column];
                $text .= in_array(self::CELL_TYPE_FILLER, $cell['types']) ? self::EMPTY_CELL : $cell['letter'];
            }
            $text .= "\n";
        }

        return substr($text, 0, -1);
    }

    public function solutionAsHtml(): string
    {
        $table = new Table();

        for ($row = 0; $row < $this->rows; $row++) {
            for ($column = 0; $column < $this->columns; $column++) {
                $cell = $this->puzzle[$row][$column];

//                $letter = in_array(self::CELL_TYPE_FILLER, $cell['types']) ? self::EMPTY_CELL_HTML : $cell['letter'];
                $letter = $cell['letter'];

                $contents = <<<HTML
                <div class="cell-inner">
                    $letter
                    <div class="cell-border-diagonal-down"></div>
                    <div class="cell-border-diagonal-up"></div>
                    <div class="cell-border-vertical"></div>
                    <div class="cell-border-horizontal"></div>
                </div>
                HTML;

                $classes = [];

                $cellTypes = array_unique($cell['types']);
                $cellDirections = array_unique($cell['directions']);

                $attributes = [];

                if (in_array(self::CELL_TYPE_FILLER, $cellTypes)) {
                    $classes[] = 'cell-filler';
                } else {
                    $classes[] = 'cell-letter';

                    foreach ($cellDirections as $cellDirection) {
                        $classes[] = 'cell-'.$cellDirection;
                    }

                    $attributes = $classes ? ['class' => implode(' ', $classes)] : [];
                }

                $table->addBodyCell($contents, $row, $column, $attributes);
            }
        }

        return $table->toHtml();
    }

    public function wordListAsText(): string
    {
        return implode("\n", $this->words);
    }

    public function wordListAsHtml(): string
    {
        return '<ul><li>'.implode("</li><li>", $this->words).'</li></ul>';
    }

}