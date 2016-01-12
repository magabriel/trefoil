<?php
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
 * - Transforms a "simple" HTML table into a "complex" table,
 *   where "simple" means "without rowspan or colspan cells".
 * - For headless tables, transforms the <td> cells in first column
 *   within <strong> tags into <th> cells (making a vertical head).
 * - Allows multiline cells.
 * 
 * Complex tables functionality details:
 * ------------------------------------
 *
 * It is designed to allow HTML tables generated from Markdown content
 * to have the extra funcionality of rowspan or colspan without having
 * to modify the parser.
 *
 * For the transformations to work, cell contents must follow some simple
 * rules:
 *
 * - A cell containing only ["] (a single double quote) or ['] a single
 *   single quote => rowspanned cell(meaning it is joined with the same
 *   cell of the preceding row). The difference between using double
 *   or single quotes is the vertical alignment:
 *      - double quote: middle alignmet.
 *      - single quote: top alignment.
 *
 * - An empty cell => colspanned cell (meaning it is joined with the same
 *   cell of the preceding column.
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
    public function setMarkdownParser($markdownParser)
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
    public function processAllTables($htmlString)
    {
        $regExp = '/';
        $regExp .= '(?<table><table.*<\/table>)';
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        // PHP 5.3 compat
        $me = $this;

        $callback = function ($matches) use ($me) {
            $table = $me->internalParseTable($matches['table']);
            if (!$table) {
                return $matches[0];
            }
            $table = $me->internalProcessExtraTable($table);
            $html = $me->internalRenderTable($table);

            return $html;
        };

        $output = preg_replace_callback($regExp, $callback, $htmlString);

        return $output;
    }

    /**
     * @param $tableHtml
     *
     * @return array
     *
     * @internal Should be protected but made public for PHP 5.3 compat
     */
    public function internalParseTable($tableHtml)
    {
        $table = array();

        $table['thead'] = $this->extractRows($tableHtml, 'thead');
        $table['tbody'] = $this->extractRows($tableHtml, 'tbody');

        if (!$table['thead'] && !$table['tbody']) {
            $table['table'] = $this->extractRows($tableHtml, 'table');
        }

        return $table;
    }

    protected function extractRows($tableHtml, $tag = 'tbody')
    {
        // extract section
        $regExp = sprintf('/<%s>(?<contents>.*)<\/%s>/Ums', $tag, $tag);
        preg_match_all($regExp, $tableHtml, $matches, PREG_SET_ORDER);

        if (!isset($matches[0]['contents'])) {
            return array();
        }

        // extract all rows from section
        $thead = $matches[0]['contents'];
        $regExp = '/<tr>(?<contents>.*)<\/tr>/Ums';
        preg_match_all($regExp, $thead, $matches, PREG_SET_ORDER);

        if (!isset($matches[0]['contents'])) {
            return array();
        }

        // extract columns from each row
        $rows = array();
        foreach ($matches as $matchRow) {

            $tr = $matchRow['contents'];
            $regExp = '/<(?<tag>t[hd])(?<attr>.*)>(?<contents>.*)<\/t[hd]>/Ums';
            preg_match_all($regExp, $tr, $matchesCol, PREG_SET_ORDER);

            $cols = array();
            if ($matchesCol) {
                foreach ($matchesCol as $matchCol) {
                    $cols[] = array(
                        'tag'        => $matchCol['tag'],
                        'attributes' => $this->extractAttributes($matchCol['attr']),
                        'contents'   => $matchCol['contents']
                    );
                }
            }

            $rows[] = $cols;
        }

        return $rows;
    }

    /**
     * @param array $table
     *
     * @return array
     *
     * @internal Should be protected but made public for PHP 5.3 compat
     */
    public function internalProcessExtraTable(array $table)
    {
        // process and adjusts table definition

        $headless = true;
        foreach ($table['thead'] as $row) {
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
            $table['thead'] = $this->processSpannedCells($table['thead']);

            $table['tbody'] = $this->processMultilineCells($table['tbody']);
            $table['tbody'] = $this->processSpannedCells($table['tbody']);

            return $table;
        }

        // table without head or body
        $table['table'] = $this->processMultilineCells($table['table']);
        $table['table'] = $this->processSpannedCells($table['table']);

        return $table;
    }

    /**
     * Join the cells that belong to multiline cells.
     *
     * @param $rows
     *
     * @return array Processed table rows
     */
    protected function processMultilineCells(array $rows)
    {
        $newRows = $rows;
        foreach ($newRows as $rowIndex => $row) {

            foreach ($row as $colIndex => $col) {
                $cell = $newRows[$rowIndex][$colIndex];
                $cellText = rtrim($cell['contents']);

                if (substr($cellText, -1, 1) === '+') {
                    // continued cell
                    $newCell = array();
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
                        $parsedCell = $this->markdownParser->transform(join("\n\n", $newCell));
                    } else {
                        // safe default
                        $parsedCell = join("<br/>", $newCell);
                    }

                    $newRows[$rowIndex][$colIndex]['contents'] = $parsedCell;
                }
            }
        }

        // remove empty rows left by the process
        $newRows2 = array();
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
    protected function processFirstColumnCells(array $rows)
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

    /**
     * Process spanned rows, creating the right HTML markup.
     *
     * @param array $rows
     *
     * @return array Processed rows
     */
    protected function processSpannedCells(array $rows)
    {
        // several kinds of double quote character
        $doubleQuotes = array(
            '"',
            '&quot;',
            '&#34;',
            '&ldquo;',
            '&#8220;',
            '&rdquo;',
            '&#8221;'
        );

        // several kinds of single quote character
        $singleQuotes = array(
            "'",
            '&apos;',
            '&#39;',
            '&lsquo;',
            '&#8216;',
            '&rsquo;',
            '&#8217;',
        );
        
        $newRows = $rows;
        foreach ($rows as $rowIndex => $row) {

            foreach ($row as $colIndex => $col) {

                // an empty cell => colspanned cell
                if (trim($col['contents']) === "") {

                    // find the primary colspanned cell (same row)
                    $colspanCol = -1;
                    for ($j = $colIndex - 1; $j >= 0; $j--) {
                        if (!isset($newRows[$rowIndex][$j]['ignore']) ||
                            (isset($newRows[$rowIndex][$j]['ignore']) && $j == 0)
                        ) {
                            $colspanCol = $j;
                            break;
                        }
                    }

                    if ($colspanCol >= 0) {
                        // increment colspan counter
                        if (!isset($newRows[$rowIndex][$colspanCol]['colspan'])) {
                            $newRows[$rowIndex][$colspanCol]['colspan'] = 1;
                        }
                        $newRows[$rowIndex][$colspanCol]['colspan']++;

                        // ignore this cell
                        $newRows[$rowIndex][$colIndex]['ignore'] = true;
                    }

                    continue;
                }

                // a cell with only '"' as contents => rowspanned cell (same column)
                // consider several kind of double quote character
                // and the single quote character as a top alignment marker
                if (in_array($col['contents'], $doubleQuotes) || 
                    in_array($col['contents'], $singleQuotes)) {

                    // find the primary rowspanned cell
                    $rowspanRow = -1;
                    for ($i = $rowIndex - 1; $i >= 0; $i--) {
                        if (!isset($newRows[$i][$colIndex]['ignore'])) {
                            $rowspanRow = $i;
                            break;
                        }
                    }

                    if ($rowspanRow >= 0) {
                        // increment rowspan counter
                        if (!isset($newRows[$rowspanRow][$colIndex]['rowspan'])) {
                            $newRows[$rowspanRow][$colIndex]['rowspan'] = 1;

                            // set vertical alignement to 'middle' for double quote or
                            // 'top' for single quote 
                            if (!isset($newRows[$rowspanRow][$colIndex]['attributes']['style'])) {
                                $newRows[$rowspanRow][$colIndex]['attributes']['style'] = '';
                            } else {
                                $newRows[$rowspanRow][$colIndex]['attributes']['style'] .= ';';
                            }
                            $newRows[$rowspanRow][$colIndex]['attributes']['style'] .= 'vertical-align: middle;';
                            if (in_array($col['contents'], $singleQuotes)) {
                                $newRows[$rowspanRow][$colIndex]['attributes']['style'] .= 'vertical-align: top;';
                            }
                        }
                        $newRows[$rowspanRow][$colIndex]['rowspan']++;

                        $newRows[$rowIndex][$colIndex]['ignore'] = true;
                    }
                }
            }
        }

        return $newRows;
    }

    /**
     * @param array $table
     *
     * @return string
     *
     * @internal Should be protected but made public for PHP 5.3 compat
     */
    public function internalRenderTable(array $table)
    {
        $html = '<table>';

        if (isset($table['thead']) && $table['thead']) {
            $html .= '<thead>';
            $html .= $this->renderRows($table['thead']);
            $html .= '</thead>';
        }

        if (isset($table['tbody']) && $table['tbody']) {
            $html .= '<tbody>';
            $html .= $this->renderRows($table['tbody']);
            $html .= '</tbody>';
        }

        if (isset($table['table']) && $table['table']) {
            $html .= $this->renderRows($table['table']);
        }

        $html .= '</table>';

        return $html;
    }

    protected function renderRows($rows)
    {
        $html = '';

        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $col) {
                if (!isset($col['ignore'])) {
                    $rowspan = isset($col['rowspan']) ? sprintf('rowspan="%s"', $col['rowspan']) : '';
                    $colspan = isset($col['colspan']) ? sprintf('colspan="%s"', $col['colspan']) : '';

                    $attributes = $this->renderAttributes($col['attributes']);

                    $html .= sprintf(
                        '<%s %s %s %s>%s</%s>',
                        $col['tag'],
                        $rowspan,
                        $colspan,
                        $attributes,
                        $col['contents'],
                        $col['tag']
                    );
                }
            }
            $html .= '</tr>';
        }

        return $html;
    }

    /**
     * @param string $string
     *
     * @return array of attributes
     */
    protected function extractAttributes($string)
    {
        $regExp = '/(?<attr>.*)="(?<value>.*)"/Us';
        preg_match_all($regExp, $string, $attrMatches, PREG_SET_ORDER);

        $attributes = array();
        if ($attrMatches) {
            foreach ($attrMatches as $attrMatch) {
                $attributes[trim($attrMatch['attr'])] = $attrMatch['value'];
            }
        }

        return $attributes;
    }

    protected function renderAttributes(array $attributes)
    {
        $html = '';

        foreach ($attributes as $name => $value) {
            $html .= sprintf('%s="%s" ', $name, $value);
        }

        return $html;
    }

}
