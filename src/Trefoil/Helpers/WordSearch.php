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

    public const FILLER_LETTERS_ENGLISH = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

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
    private const MIN_WORD_LENGTH = 3;
    private const DEFAULT_NUMBER_OF_WORDS = 10;
    private const MAX_NUMBER_OF_WORDS = 30;

    protected array $puzzle = [];
    protected int $randomSeed = 0;

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

    public function generate(int    $rows = 10,
                             int    $columns = 10,
                             array  $words = self::DEFAULT_WORDS,
                             string $fillerLetters = self::FILLER_LETTERS_ENGLISH,
                             int    $numberOfWords = 0): bool
    {
        $this->words = array_map('strtoupper', $words);
        $this->rows = $rows;
        $this->columns = $columns;

        if ($numberOfWords <= 0) {
            if (count($words) > self::DEFAULT_NUMBER_OF_WORDS) {
                $numberOfWords = self::DEFAULT_NUMBER_OF_WORDS;
                $this->selectRandomWords($numberOfWords);
            }
        } elseif ($numberOfWords < count($words)) {
            $this->selectRandomWords($numberOfWords);
        }

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

    protected function selectRandomWords(int $numberOfWords)
    {
        $newWords = [];

        for ($i = 0; $i < $numberOfWords; $i++) {
            $tries = 100;
            do {
                $word = $this->words[$this->getRandomInt(0, count($this->words) - 1)];
                $tries--;
                $isValid = !in_array($word, $newWords) && strlen($word) >= self::MIN_WORD_LENGTH;
            } while (!$isValid && $tries > 0);

            if ($tries >= 0) {
                $newWords[] = $word;
            }
        }

        if (count($newWords) < $numberOfWords) {
            $this->errors[] = sprintf(
                'Could not select %s random words. Just %s selected.',
                count($newWords),
                $numberOfWords);
        }

        $this->words = $newWords;
    }

    protected function checkWords(): bool
    {
        foreach ($this->words as $word) {
            if (strlen($word) > $this->rows && strlen($word) > $this->columns) {
                $this->errors[] = sprintf(
                    'Word "%s" would not fit in %s rows by %s columns.',
                    $word,
                    $this->rows,
                    $this->columns);

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
            $unplacedWords = array_flip($words);

//            echo "==== PUZZLE start try: $puzzleTries\n";

            $directions = [];

            foreach ($words as $word) {
                $direction = $this->placeWord($this->clearWord($word));

                if ($direction === self::WORD_FAIL) {
                    $puzzleTries++;
                    $this->puzzle = $backupPuzzleBeforePuzzle;
                    $puzzleDone = false;

//                    echo "==== PUZZLE failed \n";

                    break;
                }

                $directions[$direction] = true;

                unset($unplacedWords[$word]);
            }

            // Validate that we have al lthe 4 different directions in the puzzle
            if ($puzzleDone && count($words) >= 4 && count($directions) < 4) {
                $puzzleTries++;
                $this->puzzle = $backupPuzzleBeforePuzzle;
                $puzzleDone = false;

//                echo "==== PUZZLE failed 2 \n";
            }

        } while ($puzzleTries <= $maxPuzzleTries && !$puzzleDone);

//        print_r($directions);

        if (!$puzzleDone) {
            $this->errors[] = 'Some words could not be placed.';
            foreach ($unplacedWords as $word => $key) {
                $this->errors[] = sprintf('Word "%s" could not be placed.', $word);
            }
        }

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

    protected function getRandomInt(int $min = 0,
                                    int $max = 9999999): int
    {
        if ($this->randomSeed == 0) {
            $this->setRandomSeed(mt_rand());
        }
        $this->randomSeed = ($this->randomSeed * 125) % 2796203;

        if ($min > $max) {
            $min = $max;
        }

        return $this->randomSeed % ($max - $min + 1) + $min;
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

                    $maxRow = $initialRow;
                    $incCol = 1;
                    $maxCol = $initialCol + strlen($word) - 1;
                    break;
                case self::DIRECTION_VERTICAL:
                    $initialRow = $this->getRandomInt(0, $rows - strlen($word));
                    $initialCol = $this->getRandomInt(0, $columns - 1);

                    $incRow = 1;
                    $maxRow = $initialRow + strlen($word) - 1;
                    $maxCol = $initialCol;
                    break;
                case self::DIRECTION_DIAGONAL_DOWN:
                    $initialRow = $this->getRandomInt(0, $rows - strlen($word));
                    $initialCol = $this->getRandomInt(0, $columns - strlen($word));

                    $incRow = 1;
                    $maxRow = $initialRow + strlen($word) - 1;
                    $incCol = 1;
                    $maxCol = $initialCol + strlen($word) - 1;
                    break;
                case self::DIRECTION_DIAGONAL_UP:
                    $initialRow = $this->getRandomInt($rows - strlen($word) + 1, $rows - 1);
                    $initialCol = $this->getRandomInt(0, $columns - strlen($word));

                    $incRow = -1;
                    $maxRow = $initialRow + strlen($word) - 1;;
                    $incCol = 1;
                    $maxCol = $initialCol + strlen($word) - 1;
                    break;
            }

            $reverse = $this->getRandomInt(0, 1) == 1;
            $theWord = strtoupper($reverse ? strrev($word) : $word);

            $theRow = $initialRow;
            $theCol = $initialCol;

            $wordDone = true;

            for ($i = 0; $i < strlen($theWord); $i++) {

                if ($theRow < 0 || $theRow > $rows - 1 || $theRow > $maxRow ||
                    $theCol < 0 || $theCol > $columns - 1 || $theCol > $maxCol ||
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

                $theDirection = $direction;
                if ($i == 0) {
                    $theDirection .= '-begin';
                } elseif ($i == strlen($theWord) - 1) {
                    $theDirection .= '-end';
                }

                $this->puzzle[$theRow][$theCol]['directions'][] = $theDirection;

                $theRow += $incRow;
                $theCol += $incCol;
            }
        } while ($wordTries <= $maxWordTries && !$wordDone);

        return $wordDone ? $direction : self::WORD_FAIL;
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

    public function wordListAsText(bool $sorted = true): string
    {
        $words = $this->words;
        if ($sorted) {
            sort($words);
        }

        return implode("\n", $words);
    }

    public function wordListAsHtml(bool $sorted = true): string
    {
        $words = $this->words;
        if ($sorted) {
            sort($words);
        }

        return '<ul><li>'.implode("</li><li>", $words).'</li></ul>';
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    protected function clearWord($word)
    {
        return preg_replace('/[^[:alnum:]]/ui', '', $word);
    }
}