<?php

declare(strict_types=1);

namespace Trefoil\Helpers;

use Trefoil\Util\Toolkit;

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
    private const CELL_TYPE_START_WORD = 'start-word';

    private const DIRECTION_HORIZONTAL = 'horizontal';
    private const DIRECTION_VERTICAL = 'vertical';
    private const DIRECTION_DIAGONAL_DOWN = 'diagonal-down';
    private const DIRECTION_DIAGONAL_UP = 'diagonal-up';

    private const WORD_FAIL = 'fail';
    private const MIN_WORD_LENGTH = 3;
    private const DEFAULT_NUMBER_OF_WORDS = 10;
    private const MAX_NUMBER_OF_WORDS = 30;

    public const DIFFICULTY_EASY = 'easy';
    public const DIFFICULTY_MEDIUM = 'medium';
    public const DIFFICULTY_HARD = 'hard';
    public const DIFFICULTY_VERY_HARD = 'very-hard';

    protected array $puzzle = [];

    protected PseudoRandom $random;

    protected array $difficulties = [
        self::DIFFICULTY_EASY      => [
            'min-length'     => 3,
            'max-length'     => 10,
            'use-reverse'    => false,
            'forced-reverse' => false,
        ],
        self::DIFFICULTY_MEDIUM    => [
            'min-length'     => 3,
            'max-length'     => 10,
            'use-reverse'    => true,
            'forced-reverse' => false,
        ],
        self::DIFFICULTY_HARD      => [
            'min-length'     => 6,
            'max-length'     => 99,
            'use-reverse'    => true,
            'forced-reverse' => false,
        ],
        self::DIFFICULTY_VERY_HARD => [
            'min-length'     => 6,
            'max-length'     => 99,
            'use-reverse'    => true,
            'forced-reverse' => true,
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

    public function __construct()
    {
        $this->random = new PseudoRandom();
    }

    public function generate(
        int    $rows = 10,
        int    $columns = 10,
        array  $words = self::DEFAULT_WORDS,
        string $fillerLetters = self::FILLER_LETTERS_ENGLISH,
        int    $numberOfWords = 0,
        string $difficulty = self::DIFFICULTY_MEDIUM
    ): bool {
        $this->words = array_map('mb_strtoupper', $words);
        $this->rows = $rows;
        $this->columns = $columns;

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

        $this->placeFillerLetters($fillerLetters);

        return true;
    }

    protected function selectRandomWords(
        int    $numberOfWords,
        string $difficulty,
        int $maxLength
    ) {
        $newWords = [];

        for ($i = 0; $i < $numberOfWords; $i++) {
            $tries = 100;
            do {
                $word = $this->words[$this->random->getRandomInt(0, count($this->words) - 1)];
                $tries--;
                $isValid = !in_array($word, $newWords)
                    && mb_strlen($word) >= $this->difficulties[$difficulty]['min-length']
                    && mb_strlen($word) <= $this->difficulties[$difficulty]['max-length']
                    && mb_strlen($word) <= $maxLength;
            } while (!$isValid && $tries > 0);

            if ($tries >= 0) {
                $newWords[] = $word;
            }
        }

        if (count($newWords) < $numberOfWords) {
            $this->errors[] = sprintf(
                'Could not select %s random words. Just %s selected.',
                count($newWords),
                $numberOfWords
            );
        }

        $this->words = $newWords;
    }

    protected function checkWords(): bool
    {
        foreach ($this->words as $word) {
            if (mb_strlen($word) > $this->rows && mb_strlen($word) > $this->columns) {
                $this->errors[] = sprintf(
                    'Word "%s" would not fit in %s rows by %s columns.',
                    $word,
                    $this->rows,
                    $this->columns
                );

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

    protected function placeWords(string $difficulty): bool
    {
        $words = $this->normalizeWords($this->words);

        // Sort words by length descending
        usort(
            $words,
            fn (
                $a,
                $b
            ) => mb_strlen($b) <=> mb_strlen($a)
        );

        $maxPuzzleTries = 100;

        $puzzleTries = 0;

        do {
            $backupPuzzleBeforePuzzle = $this->puzzle;
            $puzzleDone = true;
            $unplacedWords = array_flip($words);

            //            echo "==== PUZZLE start try: $puzzleTries\n";

            $directions = [];

            foreach ($words as $word) {
                $direction = $this->placeWord($this->clearWord($word), $difficulty);

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
                        'letter'     => mb_substr($letters, $this->random->getRandomInt(0, mb_strlen($letters) - 1), 1),
                        'types'      => [self::CELL_TYPE_FILLER],
                        'directions' => [],
                    ];
                }
            }
        }
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
     * @param string $word
     * @return string Direction or self::WORD_FAIL
     */
    protected function placeWord(
        string $word,
        string $difficulty
    ): string {
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
            ][$this->random->getRandomInt(0, 3)];

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
                    $initialRow = $this->random->getRandomInt(0, $rows - 1);
                    $initialCol = $this->random->getRandomInt(0, $columns - mb_strlen($word));

                    $maxRow = $initialRow;
                    $incCol = 1;
                    $maxCol = $initialCol + mb_strlen($word) - 1;
                    break;
                case self::DIRECTION_VERTICAL:
                    $initialRow = $this->random->getRandomInt(0, $rows - mb_strlen($word));
                    $initialCol = $this->random->getRandomInt(0, $columns - 1);

                    $incRow = 1;
                    $maxRow = $initialRow + mb_strlen($word) - 1;
                    $maxCol = $initialCol;
                    break;
                case self::DIRECTION_DIAGONAL_DOWN:
                    $initialRow = $this->random->getRandomInt(0, $rows - mb_strlen($word));
                    $initialCol = $this->random->getRandomInt(0, $columns - mb_strlen($word));

                    $incRow = 1;
                    $maxRow = $initialRow + mb_strlen($word) - 1;
                    $incCol = 1;
                    $maxCol = $initialCol + mb_strlen($word) - 1;
                    break;
                case self::DIRECTION_DIAGONAL_UP:
                    $initialRow = $this->random->getRandomInt($rows - mb_strlen($word) + 1, $rows - 1);
                    $initialCol = $this->random->getRandomInt(0, $columns - mb_strlen($word));

                    $incRow = -1;
                    $maxRow = $initialRow + mb_strlen($word) - 1;;
                    $incCol = 1;
                    $maxCol = $initialCol + mb_strlen($word) - 1;
                    break;
            }

            if (!$this->difficulties[$difficulty]['use-reverse']) {
                $reverse = false;
            } elseif ($this->difficulties[$difficulty]['forced-reverse']) {
                $reverse = true;
            } else {
                $reverse = $this->random->getRandomInt(0, 1) == 1;
            }

            $theWord = mb_strtoupper($reverse ? Toolkit::mb_strrev($word) : $word);

            $theRow = $initialRow;
            $theCol = $initialCol;

            $wordDone = true;

            for ($i = 0; $i < mb_strlen($theWord); $i++) {

                if (
                    $theRow < 0 || $theRow > $rows - 1 || $theRow > $maxRow ||
                    $theCol < 0 || $theCol > $columns - 1 || $theCol > $maxCol ||
                    $this->puzzle[$theRow][$theCol]['letter'] !== self::EMPTY_CELL &&
                    $this->puzzle[$theRow][$theCol]['letter'] !== mb_substr($theWord, $i, 1)
                ) {

                    $wordTries++;
                    $this->puzzle = $backupPuzzleBeforeWord;
                    $wordDone = false;

                    //                    echo "====== WORD failed: $word\n";
                    break;
                }

                $this->puzzle[$theRow][$theCol]['letter'] = mb_substr($theWord, $i, 1);
                $this->puzzle[$theRow][$theCol]['types'][] = self::CELL_TYPE_LETTER;

                $theDirection = $direction;
                if ($i == 0) {
                    $theDirection .= '-begin';
                    if (!$reverse) {
                        $this->puzzle[$theRow][$theCol]['types'][] = self::CELL_TYPE_START_WORD;
                    }
                } elseif ($i == mb_strlen($theWord) - 1) {
                    $theDirection .= '-end';
                    if ($reverse) {
                        $this->puzzle[$theRow][$theCol]['types'][] = self::CELL_TYPE_START_WORD;
                    }
                }

                $this->puzzle[$theRow][$theCol]['directions'][] = $theDirection;

                $theRow += $incRow;
                $theCol += $incCol;
            }
        } while ($wordTries <= $maxWordTries && !$wordDone);

        return $wordDone ? $direction : self::WORD_FAIL;
    }

    protected function clearWord($word)
    {
        return preg_replace('/[^[:alnum:]]/ui', '', $word);
    }

    public function setRandomSeed(int $seed)
    {
        $this->random->setRandomSeed($seed);
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

    public function wordListAsText(bool $sorted = true): string
    {
        $words = $this->words;
        if ($sorted) {
            sort($words);
        }

        return implode("\n", $words);
    }

    public function wordListAsHtml(
        bool $sorted = true,
        int  $chunks = 1
    ): string {
        $words = $this->words;
        if ($sorted) {
            sort($words);
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
}
