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

class CrosswordRenderer
{
    protected array $ipuz = [];
    protected array $errors = [];
    protected array $warnings = [];

    protected array $puzzle = [];
    protected array $solution = [];
    protected array $clues = [];

    protected int $rows = 0;
    protected int $columns = 0;
    protected string $anagramWord = '';
    protected string $anagramClue = '';

    protected array $commonData = [];

    public function loadIpuz(string $ipuzFile)
    {
        if (!file_exists($ipuzFile)) {
            $this->errors[] = sprintf('IPuz file not found: %s', $ipuzFile);
            return;
        }

        $this->ipuz = json_decode(file_get_contents($ipuzFile), true);

        if (empty($this->ipuz)) {
            $this->errors[] = sprintf('Invalid IPuz file: %s', $ipuzFile);
            return;
        }
    }

    public function render(): bool
    {
        if (empty($this->ipuz)) {
            return false;
        }

        $this->parseDimensions();
        $this->parseAnagram();
        $this->parseCommonData();
        $this->parsePuzzle();
        $this->parseSolution();
        $this->parseClues();


        if (!empty($this->errors)) {
            return false;
        }

        return true;
    }

    public function getPuzzleHtml(): string
    {
        $table = new Table();

        for ($row = 0; $row < $this->rows; $row++) {
            for ($column = 0; $column < $this->columns; $column++) {
                $attributes = [];
                $cell = '&nbsp;';

                if ($this->puzzle[$row][$column]['is-block']) {
                    $attributes['class'] = 'block';
                } else {
                    if ($this->puzzle[$row][$column]['clue'] !== '0') {
                        $cell = sprintf("<span class=\"clue-number\">%s</span>", $this->puzzle[$row][$column]['clue']);
                    }
                    if ($this->puzzle[$row][$column]['has-circle']) {
                        $attributes['class'] = 'circle';
                    }
                }

                $table->addBodyCell($cell, $row, $column, $attributes);
            }
        }

        return $table->toHtml();
    }

    public function getCluesAcrossHtml(bool $asList = true): string
    {
        $html = $asList ? "<ul>" : "";
        foreach ($this->clues['across'] as $number => $clue) {
            $clueText = trim($clue['clue']);
            if (!str_ends_with($clueText, '.')) {
                $clueText .= '.';
            }

            $item = $asList ? "<li>" : "<p>";
            $item .= "<span class=\"clue-number\">" . $number . "</span>&nbsp;" . $clueText;
            $item .= $asList ? "</li>" : "</p>";

            $html .= $item;
        }
        $html .= $asList ? "</ul>" : "";
        return $html;
    }

    public function getCluesDownHtml(bool $asList = true): string
    {
        $html = $asList ? "<ul>" : "";
        foreach ($this->clues['down'] as $number => $clue) {
            $clueText = trim($clue['clue']);
            if (!str_ends_with($clueText, '.')) {
                $clueText .= '.';
            }
            $item = $asList ? "<li>" : "<p>";
            $item .= "<span class=\"clue-number\">" . $number . "</span>&nbsp;" . $clueText;
            $item .= $asList ? "</li>" : "</p>";

            $html .= $item;
        }
        $html .= $asList ? "</ul>" : "";
        return $html;
    }

    public function getSolutionHtml(): string
    {
        $table = new Table();

        for ($row = 0; $row < $this->rows; $row++) {
            for ($column = 0; $column < $this->columns; $column++) {
                $attributes = [];
                if ($this->solution[$row][$column]['is-block']) {
                    $attributes['class'] = 'block';
                    $cell = '&nbsp;';
                } else {
                    $cell = '';
                    if ($this->puzzle[$row][$column]['clue'] !== "0") {
                        $cell .= sprintf("<span class=\"clue-number\">%s</span>", $this->puzzle[$row][$column]['clue']);
                    }
                    $cell .= sprintf("<span class=\"letter\">%s</span>", $this->solution[$row][$column]['letter']);

                    if ($this->puzzle[$row][$column]['has-circle']) {
                        $attributes['class'] = 'circle';
                    }
                }


                $table->addBodyCell($cell, $row, $column, $attributes);
            }
        }

        return $table->toHtml();
    }

    public function getAnagramWord(): string
    {
        return $this->anagramWord;
    }

    public function getAnagramClue(): string
    {
        return $this->anagramClue;
    }

    public function getCommonData(): array
    {
        return $this->commonData;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    protected function parseDimensions(): void
    {
        if (!array_key_exists('dimensions', $this->ipuz)) {
            $this->errors[] = 'Invalid IPuz file: missing dimensions';
            return;
        }

        if (!array_key_exists('height', $this->ipuz['dimensions']) || !array_key_exists('width', $this->ipuz['dimensions'])) {
            $this->errors[] = 'Invalid IPuz file: missing dimensions height or width';
            return;
        }

        if ($this->ipuz['dimensions']['height'] <= 0 || $this->ipuz['dimensions']['width'] <= 0) {
            $this->errors[] = 'Invalid IPuz file: dimensions must be great  er than 0';
            return;
        }

        $this->rows = $this->ipuz['dimensions']['height'];
        $this->columns = $this->ipuz['dimensions']['width'];
    }

    protected function parseAnagram(): void
    {
        $this->anagramWord = $this->ipuz['cwgen:anagram-word'] ?? $this->ipuz['answer'] ?? '';
        $this->anagramClue = $this->ipuz['cwgen:anagram-clue'] ?? '';
    }

    protected function parseCommonData(): void
    {
        $this->commonData = [
            'uniqueid' => $this->ipuz['uniqueid'] ?? '',
            'title' => $this->ipuz['title'] ?? '',
            'annotation' => $this->ipuz['annotation'] ?? '',
            'date' => $this->ipuz['date'] ?? '',
            'origin' => $this->ipuz['origin'] ?? '',
            'version' => $this->ipuz['version'] ?? '',
            'kind' => $this->ipuz['kind'] ?? [],
            'answer' => $this->ipuz['answer'] ?? '',
        ];
    }


    protected function parsePuzzle(): void
    {
        if (!array_key_exists('puzzle', $this->ipuz) || !is_array($this->ipuz['puzzle'])) {
            $this->errors[] = 'Invalid IPuz file: missing puzzle';
            return;
        }

        if (count($this->ipuz['puzzle']) !== $this->rows) {
            $this->errors[] = 'Invalid IPuz file: puzzle height does not match dimensions height';
            return;
        }

        if (count($this->ipuz['puzzle'][0]) !== $this->columns) {
            $this->errors[] = 'Invalid IPuz file: puzzle width does not match dimensions width';
            return;
        }

        foreach ($this->ipuz['puzzle'] as $row) {
            $puzzleRow = [];
            foreach ($row as $cell) {
                $puzzleCell = [
                    "clue" => "0",
                    "is-block" => false,
                    "has-circle" => false
                ];
                if (is_numeric($cell)) {
                    // A definition number
                    $puzzleCell['clue'] = (string) $cell;
                    $puzzleRow[] = $puzzleCell;
                    continue;
                }

                if ($cell == "#") {
                    // A block cell
                    $puzzleCell['is-block'] = true;
                    $puzzleRow[] = $puzzleCell;
                    continue;
                }

                if (is_array($cell)) {
                    $puzzleCell['clue'] = (string) ($cell['cell'] ?? "0");
                    $puzzleCell['has-circle'] = $cell['style']['shapebg'] === 'circle';
                }
                $puzzleRow[] = $puzzleCell;
            }
            $this->puzzle[] = $puzzleRow;
        }
    }

    protected function parseSolution(): void
    {
        if (!array_key_exists('solution', $this->ipuz) || !is_array($this->ipuz['solution'])) {
            $this->errors[] = 'Invalid IPuz file: missing solution';
            return;
        }

        if (count($this->ipuz['solution']) !== $this->rows) {
            $this->errors[] = 'Invalid IPuz file: solution height does not match dimensions height';
            return;
        }

        if (count($this->ipuz['solution'][0]) !== $this->columns) {
            $this->errors[] = 'Invalid IPuz file: solution width does not match dimensions width';
            return;
        }

        foreach ($this->ipuz['solution'] as $row) {
            $solutionRow = [];
            foreach ($row as $cell) {
                $solutionCell = [
                    "letter" => 0,
                    "is-block" => false,
                ];
                if ($cell == "#") {
                    // A block cell
                    $solutionCell['is-block'] = true;
                    $solutionRow[] = $solutionCell;
                    continue;
                }
                $solutionCell['letter'] = $cell;
                $solutionRow[] = $solutionCell;
            }
            $this->solution[] = $solutionRow;
        }
    }

    protected function parseClues(): void
    {
        if (!array_key_exists('clues', $this->ipuz) || !is_array($this->ipuz['clues'])) {
            $this->errors[] = 'Invalid IPuz file: missing clues';
            return;
        }


        if (!array_key_exists('Across', $this->ipuz['clues']) || !is_array($this->ipuz['clues']['Across'])) {
            $this->errors[] = 'Invalid IPuz file: missing across clues';
            return;
        }

        if (!array_key_exists('Down', $this->ipuz['clues']) || !is_array($this->ipuz['clues']['Down'])) {
            $this->errors[] = 'Invalid IPuz file: missing down clues';
            return;
        }

        $this->clues = [
            "across" => [],
            "down" => []
        ];

        foreach ($this->ipuz['clues']['Across'] as $clue) {
            $this->clues['across'][$clue['number']] = [
                "clue" => $clue['clue'],
                "answer" => $clue['answer']
            ];
        }

        foreach ($this->ipuz['clues']['Down'] as $clue) {
            $this->clues['down'][$clue['number']] = [
                "clue" => $clue['clue'],
                "answer" => $clue['answer']
            ];
        }
    }
}