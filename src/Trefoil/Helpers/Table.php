<?php
declare(strict_types=1);

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
    public const CREATE_TBODY = 0b00000001;

    /**
     * @param string $htmlTable HTML <table> tag
     */
    public function fromHtml($htmlTable): void
    {
        $this->parseHtmlTable($htmlTable);
    }

    /**
     * @param     $htmlTable
     * @param int $flags
     */
    protected function parseHtmlTable($htmlTable,
                                      $flags = self::CREATE_TBODY): void
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
     * @param        $htmlTable
     * @param string $tag
     *
     * @return array of rows
     */
    protected function extractHtmlRows($htmlTable,
                                       $tag = 'tbody'): array
    {
        // extract section
        $regExp = sprintf('/<%s>(?<contents>.*)<\/%s>/Ums', $tag, $tag);
        preg_match_all($regExp, $htmlTable, $matches, PREG_SET_ORDER);

        if (!isset($matches[0]['contents'])) {
            return [];
        }

        // extract all rows from section
        $thead = $matches[0]['contents'];
        $regExp = '/<tr>(?<contents>.*)<\/tr>/Ums';
        preg_match_all($regExp, $thead, $matches, PREG_SET_ORDER);

        if (!isset($matches[0]['contents'])) {
            return [];
        }

        // extract columns from each row
        $rows = [];
        /** @var array $matches */
        foreach ($matches as $matchRow) {

            $tr = $matchRow['contents'];
            $regExp = '/<(?<tag>t[hd])(?<attr>.*)>(?<contents>.*)<\/t[hd]>/Ums';
            preg_match_all($regExp, $tr, $matchesCol, PREG_SET_ORDER);

            $cols = [];
            /** @var array $matchesCol */
            if ($matchesCol) {
                foreach ($matchesCol as $matchCol) {
                    $cols[] = [
                        'tag'        => $matchCol['tag'],
                        'attributes' => $this->extractAttributes($matchCol['attr']),
                        'contents'   => $matchCol['contents'],
                    ];
                }
            }

            $rows[] = $cols;
        }

        return $rows;
    }

    /**
     * @param string $string
     *
     * @return array of attributes
     */
    protected function extractAttributes($string): array
    {
        $regExp = '/(?<attr>.*)="(?<value>.*)"/Us';
        preg_match_all($regExp, $string, $attrMatches, PREG_SET_ORDER);

        $attributes = [];
        /** @var array $attrMatches */
        if ($attrMatches) {
            foreach ($attrMatches as $attrMatch) {
                $attributes[trim($attrMatch['attr'])] = $attrMatch['value'];
            }
        }

        return $attributes;
    }

    /**
     * @return string Table rendered to HTML
     */
    public function toHtml(): string
    {
        return $this->renderTableToHtml();
    }

    /**
     * @return string
     *
     */
    protected function renderTableToHtml(): string
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

        return '<table>'.$html.'</table>';
    }

    /**
     * @param array $rows
     *
     * @return string
     */
    protected function renderHtmlRows(array $rows): string
    {
        $html = '';

        $rows = $this->processSpannedCells($rows);

        foreach ($rows as $row) {
            $html .= '<tr>';

            /** @var array $row */
            foreach ($row as $col) {
                if (!isset($col['ignore'])) {
                    $rowspan = isset($col['rowspan']) ? sprintf(' rowspan="%s"', $col['rowspan']) : '';
                    $colspan = isset($col['colspan']) ? sprintf(' colspan="%s"', $col['colspan']) : '';

                    $attributes = isset($col['attributes']) && $col['attributes']? ' '.$this->renderAttributes($col['attributes']) : '';

                    $html .= sprintf(
                        '<%s%s%s%s>%s</%s>',
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
     * Process spanned rows, creating the right HTML markup.
     *
     * @param array $rows
     *
     * @return array Processed rows
     */
    protected function processSpannedCells(array $rows): array
    {
        // several kinds of double quote character
        $doubleQuotes = [
            '"',
            '&quot;',
            '&#34;',
            '&ldquo;',
            '&#8220;',
            '&rdquo;',
            '&#8221;',
        ];

        // several kinds of single quote character
        $singleQuotes = [
            "'",
            '&apos;',
            '&#39;',
            '&lsquo;',
            '&#8216;',
            '&rsquo;',
            '&#8217;',
        ];

        $newRows = $rows;
        foreach ($rows as $rowIndex => $row) {

            /** @var array $row */
            foreach ($row as $colIndex => $col) {

                // an empty cell => colspanned cell
                if (trim($col['contents']) === '') {

                    // find the primary colspanned cell (same row)
                    $colspanCol = -1;
                    for ($j = $colIndex - 1; $j >= 0; $j--) {
                        if (!isset($newRows[$rowIndex][$j]['ignore']) ||
                            (isset($newRows[$rowIndex][$j]['ignore']) && $j === 0)
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
                if (in_array($col['contents'], $doubleQuotes, true) || in_array($col['contents'], $singleQuotes, true)
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
                            if (in_array($col['contents'], $singleQuotes, true)) {
                                $newRows[$rowspanRow][$colIndex]['attributes']['style'] .= 'vertical-align: top;';
                            } else {
                                $newRows[$rowspanRow][$colIndex]['attributes']['style'] .= 'vertical-align: middle;';
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
     * @param array $attributes
     *
     * @return string rendered attributes
     */
    protected function renderAttributes(array $attributes): string
    {
        $html = '';

        foreach ($attributes as $name => $value) {
            $html .= sprintf('%s="%s" ', $name, $value);
        }

        return $html;
    }

    /**
     * @param string   $contents
     * @param int|null $column
     * @param array    $attributes
     */
    public function addHeadingCell(string $contents,
                                   int    $column = null,
                                   array  $attributes = []): void
    {
        if ($column === null) {
            $column = count($this['thead'][0]);
        }

        $this['thead'][0][$column] = [
            'tag'      => 'th',
            'contents' => $contents,
            'attributes' => $attributes
        ];
    }

    /**
     * @param int $column
     *
     * @return null|array cell
     */
    public function getHeadingCell($column): ?array
    {
        return $this['thead'][0][$column] ?? null;
    }

    /**
     * @param array $cell
     * @param int   $column
     */
    public function setHeadingCell(array $cell,
                                         $column): void
    {
        $this['thead'][0][$column] = $cell;
    }

    public function addBodyRow(): void
    {
        if (!isset($this['tbody'])) {
            $this['tbody'] = [];
        }

        $this['tbody'][] = [];
    }

    /**
     * @return int
     */
    public function getBodyRowsCount()
    {
        if (isset($this['tbody'])) {
            return count($this['tbody']);
        }

        return 0;
    }

    /**
     * @param $row
     * @return int
     */
    public function getBodyCellsCount($row)
    {
        if (isset($this['tbody'][$row])) {
            return count($this['tbody'][$row]);
        }

        return 0;
    }

    /**
     * @param string   $contents
     * @param int|null $row
     * @param int|null $column
     * @param array    $attributes
     * @return array
     */
    public function addBodyCell(string $contents,
                                int    $row = null,
                                int    $column = null,
                                array  $attributes = []): array
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
            $this['tbody'][$row][$column]['attributes'] = $attributes;
        } else {
            $this['tbody'][$row][$column] = [
                'tag'        => 'td',
                'contents'   => $contents,
                'attributes' => $attributes,
            ];
        }

        return ['row' => $row, 'column' => $column];
    }

    /**
     * @param $extra
     * @param $row
     * @param $column
     */
    public function setBodyCellExtra($extra,
                                     $row,
                                     $column): void
    {
        $this['tbody'][$row][$column]['extra'] = $extra;
    }

    /**
     * @param $row
     * @param $column
     * @return |null
     */
    public function getBodyCellExtra($row,
                                     $column)
    {
        if (!isset($this['tbody'][$row][$column]['extra'])) {
            return null;
        }

        return $this['tbody'][$row][$column]['extra'];
    }

    /**
     * @param $colspan
     * @param $row
     * @param $column
     */
    public function setColspan($colspan,
                               $row,
                               $column): void
    {
        $this['tbody'][$row][$column]['colspan'] = $colspan;
    }

    /**
     * @param $rowsspan
     * @param $row
     * @param $column
     */
    public function setRowsspan($rowsspan,
                                $row,
                                $column): void
    {
        $this['tbody'][$row][$column]['rowspan'] = $rowsspan;
    }

    /**
     * @param $row
     * @param $column
     *
     * @return null|array cell
     */
    public function getBodyCell($row,
                                $column): ?array
    {
        return $this['tbody'][$row][$column] ?? null;
    }

    /**
     * @param array $cell
     * @param       $row
     * @param       $column
     */
    public function setBodyCell(array $cell,
                                      $row,
                                      $column): void
    {
        $this['tbody'][$row][$column] = $cell;
    }

    /**
     * @return bool True if the table does not have any rows
     */
    public function isEmpty(): bool
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
}

