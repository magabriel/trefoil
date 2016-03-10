<?php

namespace Trefoil\Helpers;

/**
 * Class Table abstracts the representation of a table (as in "HTML table").
 *
 * This class extends ArrayObject, so an instance can be manipulated as an
 * array.
 *      Example: $table['tbody'][1][2] => cell[1][2] in the <tbody> section.
 *
 * Extended functionality:
 * - Transforms a "simple" HTML table into a "complex" table,
 *   where "simple" means "without rowspan or colspan cells".
 *
 * Complex tables functionality details:
 * ------------------------------------
 *
 * It is designed to allow HTML tables generated from Markdown content
 * to have the extra functionality of rowspan or colspan without having
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
 * @package Trefoil\Helpers
 */
class Table extends \ArrayObject
{
    /**
     * Create <tbody> section if the table does not have it
     */
    const CREATE_TBODY = 0b00000001;

    /**
     * @param $htmlTable string HTML <table> tag
     */
    public function fromHtml($htmlTable)
    {
        $this->parseHtmlTable($htmlTable);
    }

    /**
     * @return string Table rendered to HTML
     */
    public function toHtml()
    {
        return $this->renderTableToHtml();
    }

    /**
     * @param     $htmlTable
     * @param int $flags
     */
    protected function parseHtmlTable($htmlTable, $flags = self::CREATE_TBODY)
    {
        // init the ArrayObject
        $this->exchangeArray([]);

        $this['thead'] = $this->extractHtmlRows($htmlTable, 'thead');
        $this['tbody'] = $this->extractHtmlRows($htmlTable, 'tbody');

        if (!$this['thead'] && !$this['tbody']) {
            if ($flags & self::CREATE_TBODY) {
                $this['tbody'] = $this->extractHtmlRows($htmlTable, 'table');

                return;
            }

            $this['table'] = $this->extractHtmlRows($htmlTable, 'table');
        }
    }

    /**
     * @param string $contents
     * @param int    $column
     */
    public function addHeadingCell($contents, $column = null)
    {
        if ($column === null) {
            $column = count($this['thead'][0]);
        }

        $this['thead'][0][$column] = [
            'tag'      => 'th',
            'contents' => $contents
        ];
    }

    /**
     * @param int $column
     *
     * @return null|array cell
     */
    public function getHeadingCell($column)
    {
        if (isset($this['thead'][0][$column])) {
            return $this['thead'][0][$column];
        }

        return null;
    }

    /**
     * @param array $cell
     * @param int   $column
     */
    public function setHeadingCell(array $cell, $column)
    {
        $this['thead'][0][$column] = $cell;
    }

    public function addBodyRow()
    {
        if (!isset($this['tbody'])) {
            $this['tbody'] = [];
        }

        $this['tbody'][] = [];
    }

    public function getBodyRowsCount()
    {
        if (isset($this['tbody'])) {
            return count($this['tbody']);
        }

        return 0;
    }

    public function getBodyCellsCount($row)
    {
        if (isset($this['tbody'][$row])) {
            return count($this['tbody'][$row]);
        }

        return 0;
    }

    /**
     * @param      $contents
     * @param null $row
     * @param null $column
     *
     * @return array
     */
    public function addBodyCell($contents, $row = null, $column = null)
    {
        if (!isset($this['tbody'])) {
            $this['tbody'][] = [];
        }

        if ($row === null) {
            $row = count($this['tbody']) - 1;
        }

        if ($column === null) {
            $column = count($this['tbody'][$row]);
        }

        // modify if existing, new otherwise
        if (isset($this['tbody'][$row][$column])) {
            $this['tbody'][$row][$column]['contents'] = $contents;
        } else {
            $this['tbody'][$row][$column] = [
                'tag'      => 'td',
                'contents' => $contents
            ];
        }

        return ['row' => $row, 'column' => $column];
    }

    public function setBodyCellExtra($extra, $row, $column)
    {
        $this['tbody'][$row][$column]['extra'] = $extra;
    }

    public function getBodyCellExtra($row, $column)
    {
        if (!isset($this['tbody'][$row][$column]['extra'])) {
            return null;
        }

        return $this['tbody'][$row][$column]['extra'];
    }

    public function setColspan($colspan, $row, $column)
    {
        $this['tbody'][$row][$column]['colspan'] = $colspan;
    }

    public function setRowsspan($rowsspan, $row, $column)
    {
        $this['tbody'][$row][$column]['rowspan'] = $rowsspan;
    }


    /**
     * @param $row
     * @param $column
     *
     * @return null|array cell
     */
    public function getBodyCell($row, $column)
    {
        if (isset($this['tbody'][$row][$column])) {
            return $this['tbody'][$row][$column];
        }

        return null;
    }

    /**
     * @param $cell array
     * @param $row
     * @param $column
     */
    public function setBodyCell(array $cell, $row, $column)
    {
        $this['tbody'][$row][$column] = $cell;
    }

    /**
     * @param        $htmlTable
     * @param string $tag
     *
     * @return array of rows
     */
    protected function extractHtmlRows($htmlTable, $tag = 'tbody')
    {
        // extract section
        $regExp = sprintf('/<%s>(?<contents>.*)<\/%s>/Ums', $tag, $tag);
        preg_match_all($regExp, $htmlTable, $matches, PREG_SET_ORDER);

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
     * @return bool True if the table does not have any rows
     */
    public function isEmpty()
    {
        if (isset($this['thead']) && $this['thead']) {
            return false;
        }

        if (isset($this['tbody']) && $this['tbody']) {
            return false;
        }

        if (isset($this['table']) && $this['table']) {
            return false;
        }

        return true;
    }

    /**
     * @return string
     *
     */
    protected function renderTableToHtml()
    {
        $html = '';

        if (isset($this['thead']) && $this['thead']) {
            $html .= '<thead>';
            $html .= $this->renderHtmlRows($this['thead']);
            $html .= '</thead>';
        }

        if (isset($this['tbody']) && $this['tbody']) {
            $html .= '<tbody>';
            $html .= $this->renderHtmlRows($this['tbody']);
            $html .= '</tbody>';
        }

        if (isset($this['table']) && $this['table']) {
            $html .= $this->renderHtmlRows($this['table']);
        }

        if (empty($html)) {
            return '';
        }

        return '<table>' . $html . '</table>';
    }

    /**
     * @param array $rows
     *
     * @return string
     */
    protected function renderHtmlRows(array $rows)
    {
        $html = '';

        $rows = $this->processSpannedCells($rows);

        foreach ($rows as $row) {
            $html .= '<tr>';

            foreach ($row as $col) {
                if (!isset($col['ignore'])) {
                    $rowspan = isset($col['rowspan']) ? sprintf('rowspan="%s"', $col['rowspan']) : '';
                    $colspan = isset($col['colspan']) ? sprintf('colspan="%s"', $col['colspan']) : '';

                    $attributes = isset($col['attributes']) ? $this->renderAttributes($col['attributes']) : '';

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

    /**
     * @param array $attributes
     *
     * @return string rendered attributes
     */
    protected function renderAttributes(array $attributes)
    {
        $html = '';

        foreach ($attributes as $name => $value) {
            $html .= sprintf('%s="%s" ', $name, $value);
        }

        return $html;
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
                    in_array($col['contents'], $singleQuotes)
                ) {

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

                            // set vertical alignment to 'middle' for double quote or
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
}

