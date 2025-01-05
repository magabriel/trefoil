<?php

declare(strict_types=1);

namespace Trefoil\Helpers;

class Paperdle
{
    public const LETTER_OK = "=";
    public const LETTER_EXIST = "?";
    public const LETTER_EMPTY = "#";
    public const ALPHABET = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";

    private string $alphabet;
    private int $wordLength;
    private array $positions;
    private string $wordToGuess;

    /**
     * @var string[]
     */
    protected array $errors = [];

    /**
     * @var string[]
     */
    protected array $warnings = [];

    protected array $evaluationTable = [];

    public function __construct($numPositions = 5)
    {
        $this->wordLength = $numPositions;
        $this->wordToGuess = "";
    }

    public function init(
        string $wordToGuess,
        string $alphabet = self::ALPHABET
    ): void {
        if (strlen($wordToGuess) !== 5 || !ctype_alpha($wordToGuess)) {
            $this->errors[] = sprintf("The word to guess must have exactly %s letters and contain only alphabetic characters.", $this->wordLength);
            return;
        }

        $this->wordToGuess = strtoupper($wordToGuess);
        $this->alphabet = $alphabet;
        $this->positions = array_map('strval', range(1, $this->wordLength));

        // Crear la tabla inicializada con valores vacíos
        $table = array_combine(
            str_split($this->alphabet),
            array_fill(0, strlen($this->alphabet), array_combine($this->positions, array_fill(0, 5, self::LETTER_EMPTY)))
        );

        // Evaluar las letras en sus posiciones correctas
        $word_array = str_split($this->wordToGuess);
        foreach ($word_array as $idx => $letter) {
            $table[$letter][$idx + 1] = self::LETTER_OK;
        }

        // Evaluar las letras descolocadas
        foreach ($word_array as $idx => $letter) {
            foreach ($this->positions as $pos) {
                if ($table[$letter][$pos] === self::LETTER_EMPTY && $pos !== $idx + 1) {
                    $table[$letter][$pos] = self::LETTER_EXIST;
                }
            }
        }

        $this->evaluationTable = $table;
    }

    public function createRandomizerTable(): array
    {
        // Crear una lista de todas las combinaciones LETRA + POSICIÓN posibles
        $combinations = [];
        foreach (str_split($this->alphabet) as $letter) {
            foreach ($this->positions as $pos) {
                $combinations[] = $letter . $pos;
            }
        }

        // Semilla para el generador de números aleatorios usando la palabra a adivinar
        $seed = array_sum(array_map('ord', str_split($this->wordToGuess))) % 1000;
        mt_srand($seed);

        // Mezclar las combinaciones en orden seudoaleatorio (repetible)
        shuffle($combinations);

        // Crear la tabla inicializada con espacios
        $table = array_combine(
            str_split($this->alphabet),
            array_fill(0, strlen($this->alphabet), array_combine($this->positions, array_fill(0, 5, " ")))
        );

        // Asignar cada combinación a una celda de la tabla en orden
        foreach (str_split($this->alphabet) as $letter) {
            foreach ($this->positions as $pos) {
                $table[$letter][$pos] = array_shift($combinations);
            }
        }

        return $table;
    }

    public function createDecoder(array $evaluation_table, array $randomizer_table): array
    {
        // Crear la tabla inicializada con espacios
        $decoder_table = array_combine(
            str_split($this->alphabet),
            array_fill(0, strlen($this->alphabet), array_combine($this->positions, array_fill(0, 5, " ")))
        );

        // Asignar a cada celda del decodificador el valor de la evaluación decodificado
        foreach (str_split($this->alphabet) as $letter) {
            foreach ($this->positions as $pos) {
                $combination = $randomizer_table[$letter][$pos];
                $letter2 = $combination[0];
                $pos2 = intval($combination[1]);

                $decoder_table[$letter2][$pos2] = $evaluation_table[$letter][$pos];
            }
        }

        return $decoder_table;
    }

    public function getEvaluationTable(): array
    {
        return $this->evaluationTable;
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

// Ejemplo de uso:
$evaluador = new Paperdle();
$PALABRA_A_ADIVINAR = "CLAVE";

// Tabla de evaluación
$evaluador->init($PALABRA_A_ADIVINAR);
$evaluation_table = $evaluador->getEvaluationTable();
echo "Evaluación de la palabra '$PALABRA_A_ADIVINAR':\n";
echo "   1 2 3 4 5\n";
echo "   ─────────\n";
foreach ($evaluation_table as $letter => $positions) {
    echo $letter . ": ";
    echo implode(" ", $positions) . "\n";
}

// Tabla aleatoria
echo "\nTabla aleatoria:\n";
$randomizer_table = $evaluador->createRandomizerTable();
echo "    1  2  3  4  5\n";
echo "   ──────────────\n";
foreach ($randomizer_table as $letter => $positions) {
    echo $letter . ": ";
    echo implode(" ", $positions) . "\n";
}

// Decodificador
echo "\nDecodificador:\n";
$decoder_table = $evaluador->createDecoder($evaluation_table, $randomizer_table);
echo "   1 2 3 4 5\n";
echo "   ─────────\n";
foreach ($decoder_table as $letter => $positions) {
    echo $letter . ": ";
    echo implode(" ", $positions) . "\n";
}
