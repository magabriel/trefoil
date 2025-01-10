<?php

declare(strict_types=1);

namespace Trefoil\Helpers;

/**
 * A helper class to construct a Paperdle game (a Wordle's clone to be played on paper).
 * 
 * The following symbols are used to evaluate the letters in the player's guess:
 * - A letter in the correct position is marked with an equal sign (=).
 * - A letter in the wrong position is marked with a question mark (?).
 * - A letter that is not in the word is marked with a hash sign (#).
 * 
 * To get the printable game, call the generate() method with the word to guess. This will create 
 * three tables:
 * 1. **evaluation table**: a table with the evaluation of each letter in the word to guess (not 
 *    to be shown to the player). 
 * 2. **randomizer table**: a table with a cell for each letter and its position in the player's guess, 
 *    but with all the positions randomly shuffled.
 * 3. **decoder table**: a table that decodes each cell of the randomizer table to get the real 
 *    evaluation of each letter in the player's guess.
 * 
 * The printable game consist on the board, the randomizer table, and the decoder table. 
 * The player must guess the word and, on each turn, look up each letter on the randomizer table 
 * to get the randomized code ("A1", "B3", etc.), and then look up the code on the decoder table to get 
 * the evaluation of the letter ("=", "?", "#").
 * 
 */
class Paperdle
{
    public const ALPHABET_ENGLISH = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";

    protected string $alphabet;
    protected array $letters;
    protected array $positions;

    protected string $letterOk;
    protected string $letterExist;
    protected string $letterEmpty;

    protected string $wordToGuess;
    protected string $encodedSolution;

    /**
     * @var string[]
     */
    protected array $errors = [];

    /**
     * @var string[]
     */
    protected array $warnings = [];

    /**
     * @var array<string, array<string, string>>
     */
    protected array $evaluationTable = [];

    /**
     * @var array<string, array<string, string>>
     */
    protected array $randomizerTable = [];

    /**
     * @var array<string, array<string, string>>
     */
    protected array $decoderTable = [];

    /**
     * @var array<string, array<string, string>>
     */
    protected array $solutionDecoderTable = [];

    public function __construct()
    {
    }

    /**
     * Generate the game with the word to guess and the symbols to evaluate the player's guess.
     * 
     * @return bool Success or failure.
     */
    public function generate(
        string $wordToGuess = "PAPER",
        string $alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ",
        string $letterOk = "=",
        string $letterExist = "?",
        string $letterEmpty = "#"
    ): bool {
        $this->wordToGuess = strtoupper($wordToGuess);
        $this->alphabet = $alphabet;
        $this->letterOk = $letterOk;
        $this->letterExist = $letterExist;
        $this->letterEmpty = $letterEmpty;

        if (!preg_match('/^[\p{Latin}]+$/u', $this->wordToGuess)) {
            $this->errors[] = "The word to guess must contain only letters.";
            return false;
        }

        if (
            $this->letterOk === $this->letterExist ||
            $this->letterOk === $this->letterEmpty ||
            $this->letterExist === $this->letterEmpty
        ) {
            $this->errors[] = "The symbols for the evaluation must be different from each other.";
            return false;
        }

        if (mb_strlen($this->wordToGuess) < 5) {
            $this->errors[] = "The word to guess must have at least 5 letters.";
            return false;
        }

        $this->positions = array_map('strval', range(1, mb_strlen($this->wordToGuess)));
        $this->letters = mb_str_split($this->alphabet);

        $this->createEvaluationTable();
        $this->createRandomizerTable();
        $this->createDecoderTable();
        $this->createSolutionDecoderTable();

        return count($this->errors) == 0;
    }

    private function createEvaluationTable()
    {
        // Initialize the evaluation table with empty cell markers
        $this->evaluationTable = $this->initializeTable($this->letterEmpty);

        // Evaluate which letters are in the correct position
        $wordLetters = mb_str_split($this->wordToGuess);
        foreach ($wordLetters as $idx => $letter) {
            $this->evaluationTable[$idx + 1][$letter] = $this->letterOk;
        }

        // Evaluate misplaced letters
        foreach ($wordLetters as $idx => $letter) {
            foreach ($this->positions as $pos) {
                if ($this->evaluationTable[$pos][$letter] === $this->letterEmpty && $pos !== $idx + 1) {
                    $this->evaluationTable[$pos][$letter] = $this->letterExist;
                }
            }
        }
    }

    private function createRandomizerTable(): void
    {
        // Create a list of all possible LETTER + POSITION combinations
        $combinations = [];
        foreach ($this->positions as $pos) {
            foreach ($this->letters as $letter) {
                $combinations[] = $letter . $pos;
            }
        }

        // Shuffle the combinations in a pseudo-random order (reproducible)
        $random = new PseudoRandom();
        $seed = array_sum(array_map('mb_ord', mb_str_split($this->wordToGuess))) % 1000;
        $random->setRandomSeed($seed);
        $combinations = $random->shuffle($combinations);

        // Create the table initialized with empty cells
        $this->randomizerTable = $this->initializeTable();

        // Assign each combination to a cell in the table in order
        foreach ($this->positions as $pos) {
            foreach ($this->letters as $letter) {
                $this->randomizerTable[$pos][$letter] = array_shift($combinations);
            }
        }
    }

    protected function createDecoderTable(): void
    {
        // Create the table initialized with empty cells
        $this->decoderTable = $this->initializeTable();

        // Assign decoded evaluation value to each cell of the decoder table
        foreach ($this->positions as $pos) {
            foreach ($this->letters as $letter) {
                $combination = $this->randomizerTable[$pos][$letter];
                $parts = mb_str_split($combination);
                $letter2 = $parts[0];
                $pos2 = intval($parts[1]);

                $this->decoderTable[$pos2][$letter2] = $this->evaluationTable[$pos][$letter];
            }
        }
    }

    protected function createSolutionDecoderTable(): void
    {
        // Create the table initialized with empty cells
        $this->solutionDecoderTable = $this->initializeTable();
        $this->encodedSolution = "";

        // Initialize the pseudo-random number generator
        $random = new PseudoRandom();
        $seed = array_sum(array_map('mb_ord', mb_str_split($this->wordToGuess))) % 1000;
        $random->setRandomSeed($seed);

        // Assign each plain letter to a random cell of the solution decoder table
        $parts = [];

        foreach (mb_str_split($this->wordToGuess) as $pos => $letter) {
            $done = false;
            while (!$done) {
                $letter2 = $this->letters[$random->getRandomInt(0, count($this->letters) - 1)];
                $pos2 = $random->getRandomInt(1, mb_strlen($this->wordToGuess));

                if ($this->solutionDecoderTable[$pos2][$letter2] == " ") {
                    $this->solutionDecoderTable[$pos2][$letter2] = $letter;
                    $parts[] = sprintf("%s%s", $letter2, $pos2);
                    $done = true;
                }
            }
        }

        $this->encodedSolution = join("-", $parts);

        // Fill the blanks with a random letter

        foreach ($this->positions as $pos) {
            foreach ($this->letters as $letter) {
                if ($this->solutionDecoderTable[$pos][$letter] == " ") {
                    $randomLetter = $this->letters[$random->getRandomInt(0, count($this->letters) - 1)];
                    $this->solutionDecoderTable[$pos][$letter] = $randomLetter;
                }
            }
        }
    }

    /**
     * Returns a table initialized with empty cells.
     * 
     * @returs array<string, array<string, string>>
     */
    protected function initializeTable($emptyCell = " "): array
    {
        $table = [];
        foreach ($this->positions as $position) {
            $table[$position] = [];
            foreach ($this->letters as $letter) {
                $table[$position][$letter] = $emptyCell;
            }
        }

        return $table;
    }

    /**
     * Get the word to guess.
     */
    public function getWordToGuess(): string
    {
        return $this->wordToGuess;
    }

    public function getAlphabet(): string
    {
        return $this->alphabet;
    }

    /**
     * For debugging purposes. Not to be shown to the player.
     */
    public function getEvaluationTable(): array
    {
        return $this->evaluationTable;
    }

    public function getEvaluationTableAsHtml(): string
    {
        return $this->tableToHtml($this->evaluationTable);
    }

    public function getEvaluationTableAsText(): string
    {
        $lines = [];
        $lines[] = "   " . join(" ", $this->letters);
        $lines[] = "  " . str_repeat(" -", count($this->letters));

        foreach ($this->evaluationTable as $position => $letters) {
            $lines[] = "$position: " . join(" ", $letters);
        }

        return join("\n", $lines);
    }

    /**
     * Table 1 to be shown to the player.
     */
    public function getRandomizerTable(): array
    {
        return $this->randomizerTable;
    }

    public function getRandomizerTableAsHtml(): string
    {
        // $html = "<table>";
        // $html .= "<tr><th></th>";
        // foreach ($this->letters as $letter) {
        //     $html .= "<th>$letter</th>";
        // }
        // $html .= "</tr>";
        // foreach ($this->randomizerTable as $position => $letters) {
        //     $html .= "<tr><th>$position</th>";
        //     foreach ($letters as $letter) {
        //         $html .= "<td>$letter</td>";
        //     }
        //     $html .= "</tr>";
        // }
        // $html .= "</table>";

        // return $html;
        return $this->tableToHtml($this->randomizerTable);
    }


    public function getRandomizerTableAsText(): string
    {
        $lines = [];
        $lines[] = "    " . join("  ", $this->letters);
        $lines[] = "  " . str_repeat(" --", count($this->letters));

        foreach ($this->randomizerTable as $position => $letters) {
            $lines[] = "$position: " . join(" ", $letters);
        }

        return join("\n", $lines);
    }

    /**
     * Table 2 to be shown to the player.
     */
    public function getDecoderTable(): array
    {
        return $this->decoderTable;
    }

    public function getDecoderTableAsHtml()
    {
        // $html = "<table>";
        // $html .= "<tr><th></th>";
        // foreach ($this->letters as $letter) {
        //     $html .= "<th>$letter</th>";
        // }
        // $html .= "</tr>";
        // foreach ($this->decoderTable as $position => $letters) {
        //     $html .= "<tr><th>$position</th>";
        //     foreach ($letters as $letter) {
        //         $html .= "<td>$letter</td>";
        //     }
        //     $html .= "</tr>";
        // }
        // $html .= "</table>";

        // return $html;

        return $this->tableToHtml($this->decoderTable);
    }

    public function getDecoderTableAsText(): string
    {
        $lines = [];
        $lines[] = "   " . join(" ", $this->letters);
        $lines[] = "  " . str_repeat(" -", count($this->letters));

        foreach ($this->decoderTable as $position => $letters) {
            $lines[] = "$position: " . join(" ", $letters);
        }

        return join("\n", $lines);
    }

    public function getEncodedSolution(): string
    {
        return $this->encodedSolution;
    }

    public function getSolutionDecoderTable(): array
    {
        return $this->solutionDecoderTable;
    }

    public function getSolutionDecoderTableAsHtml(): string
    {
        // $html = "<table>";
        // $html .= "<tr><th></th>";
        // foreach ($this->letters as $letter) {
        //     $html .= "<th>$letter</th>";
        // }
        // $html .= "</tr>";
        // foreach ($this->solutionDecoderTable as $position => $letters) {
        //     $html .= "<tr><th>$position</th>";
        //     foreach ($letters as $letter) {
        //         $html .= "<td>$letter</td>";
        //     }
        //     $html .= "</tr>";
        // }
        // $html .= "</table>";

        // return $html;

        return $this->tableToHtml($this->solutionDecoderTable);
    }

    public function getSolutionDecoderTableAsText(): string
    {
        $lines = [];
        $lines[] = "   " . join(" ", $this->letters);
        $lines[] = "  " . str_repeat(" -", count($this->letters));

        foreach ($this->solutionDecoderTable as $position => $letters) {
            $lines[] = "$position: " . join(" ", $letters);
        }

        return join("\n", $lines);
    }

    protected function tableToHtml(array $table): string
    {
        $html = "<table>";
        $html .= "<tr><th></th>";
        foreach ($this->letters as $letter) {
            $html .= "<th>$letter</th>";
        }
        $html .= "</tr>";
        foreach ($table as $position => $letters) {
            $html .= "<tr><th>$position</th>";
            foreach ($letters as $letter) {
                $html .= "<td>$letter</td>";
            }
            $html .= "</tr>";
        }
        $html .= "</table>";

        return $html;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }
}
