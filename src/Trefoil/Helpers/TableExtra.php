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

use Easybook\Parsers\ParserInterface;

/**
 * This class:
 * - For headless tables, transforms the <td> cells in first column
 *   within <strong> tags into <th> cells (making a vertical head).
 * - Allows multiline cells.
 *
 * Multiline cells:
 * ---------------
 *
 * If a line inside a cell ends with '+' char (plus sign) it will be joined
 * with the next line.
 *
 * Automatic head cells for headless tables:
 * ----------------------------------------
 *
 * For a headless table (i.e. without headings in first row), cells in first
 * column that are all bold (i.e. surrounded by "**") will be rendered
 * as "<th>" tags instead of normal "<td>" tags, allowing formatting.
 *
 */
class TableExtra
{
    /** @var  ParserInterface */
    protected $markdownParser;

    /**
     * @param ParserInterface $markdownParser
     */
    public function setMarkdownParser($markdownParser): void
    {
        $this->markdownParser = $markdownParser;
    }

    /**
     * Processes all tables in the html string
     *
     * @param string $htmlString
     *
     * @return string
     */
    public function processAllTables($htmlString): string
    {
        $regExp = '/';
        $regExp .= '(?<table><table.*<\/table>)';
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        // PHP 5.3 compat
        $me = $this;

        $callback = function ($matches) use ($me) {
            $table = new Table();
            $table->fromHtml($matches['table']);

            if ($table->isEmpty()) {
                return $matches[0];
            }

            $table = $me->internalProcessExtraTable($table);

            return $table->toHtml();
        };

        return preg_replace_callback($regExp, $callback, $htmlString);
    }

    /**
     * @param Table $table
     *
     * @return Table
     * @internal Should be protected but made public for PHP 5.3 compat
     */
    public function internalProcessExtraTable(Table $table): Table
    {
        // process and adjusts table definition

        $headless = true;
        /** @var string[][] $table */
        foreach ($table['thead'] as $row) {
            /** @var array $row */
            foreach ($row as $cell) {
                if (empty($cell['contents'])) {
                    $headless = true;
                    break 2;
                }
            }
        }

        // headless table
        if ($headless && $table['tbody']) {
            $table['tbody'] = $this->processFirstColumnCells($table['tbody']);
        }

        // table with head and body
        if ($table['thead'] || $table['tbody']) {

            $table['thead'] = $this->processMultilineCells($table['thead']);
            $table['tbody'] = $this->processMultilineCells($table['tbody']);

            return $table;
        }

        // table without head or body
        $table['table'] = $this->processMultilineCells($table['table']);

        return $table;
    }

    /**
     * Join the cells that belong to multiline cells.
     *
     * @param $rows
     *
     * @return array Processed table rows
     */
    protected function processMultilineCells(array $rows): array
    {
        $newRows = $rows;
        foreach ($newRows as $rowIndex => $row) {

            /** @var array $row */
            foreach ($row as $colIndex => $col) {
                $cell = $newRows[$rowIndex][$colIndex];
                $cellText = rtrim($cell['contents']);

                if (substr($cellText, -1, 1) === '+') {
                    // continued cell
                    $newCell = [];
                    $newCell[] = substr($cellText, 0, -1);

                    // find all the continuation cells (same col)
                    for ($nextRowIndex = $rowIndex + 1; $nextRowIndex < count($newRows); $nextRowIndex++) {

                        $nextCell = $newRows[$nextRowIndex][$colIndex];
                        $cellText = rtrim($nextCell['contents']);

                        $newRows[$nextRowIndex][$colIndex]['contents'] = '';

                        // continued cell?
                        $continued = (substr($cellText, -1, 1) === '+');

                        if ($continued) {
                            // clean the ending (+)
                            $cellText = substr($cellText, 0, -1);
                        }

                        // save cleaned text
                        $newCell[] = $cellText;

                        if (!$continued) {
                            // no more continuations
                            break;
                        }
                    }

                    if ($this->markdownParser) {
                        $parsedCell = $this->markdownParser->transform(implode("\n\n", $newCell));
                    } else {
                        // safe default
                        $parsedCell = implode('<br/>', $newCell);
                    }

                    $newRows[$rowIndex][$colIndex]['contents'] = $parsedCell;
                }
            }
        }

        // remove empty rows left by the process
        $newRows2 = [];
        foreach ($newRows as $rowIndex => $row) {

            $emptyRow = true;
            foreach ($row as $colIndex => $col) {
                $cellText = trim($col['contents']);
                if (!empty($cellText)) {
                    $emptyRow = false;
                }
            }

            if (!$emptyRow) {
                $newRows2[] = $row;
            }
        }

        return $newRows2;
    }

    /**
     * Converts <td> cells into <th> for first column cells that are fully bold.
     *
     * @param array $rows
     *
     * @return array Processed rows
     */
    protected function processFirstColumnCells(array $rows): array
    {
        $newRows = $rows;
        foreach ($newRows as $rowIndex => $row) {

            // examine first cell ir row
            $cell = $newRows[$rowIndex][0];
            $cellText = rtrim($cell['contents']);

            $regExp = '/^<strong>.*<\/strong>$/Us';
            if (preg_match($regExp, $cellText, $matches)) {
                $cell['tag'] = 'th';

                // change cell to <th>  
                $newRows[$rowIndex][0]['tag'] = 'th';
            }
        }

        return $newRows;
    }

}
