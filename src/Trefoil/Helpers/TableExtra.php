<?php

namespace Trefoil\Helpers;

/**
 * This class transforms a "simple" HTML table into a "complex" table,
 * where "simple" means "without rowspan or colspan cells".
 *
 * It is designed to allow HTML tables generated from Markdown content
 * to have the extra funcionality of rowspan or colspan without having
 * to modify the parser.
 *
 * For the transformations to work, cell contents must follow some simple
 * rules:
 *
 * - A cell containing only '"' (a single double quote) => rowspanned cell
 *   (meaning it is joined with the same cell of the preceding row).
 *
 * - An empty cell => colspanned cell (meaning it is joined with the same
 *   cell of the preceding column.
 *
 */
class TableExtra
{

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

        $me = $this;
        $callback = function ($matches) use ($me) {
            $table = $me->parseTable($matches['table']);
            if (!$table) {
                return $matches[0];
            }
            $table = $me->processExtraTable($table);
            $html = $me->renderTable($table);

            return $html;
        };

        $output = preg_replace_callback($regExp, $callback, $htmlString);

        return $output;
    }

    protected function parseTable($tableHtml)
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

        // extract columns from each rown
        $rows = array();
        foreach ($matches as $matchRow) {

            $tr = $matchRow['contents'];
            $regExp = '/<(?<tag>t[hd])(?<attr>.*)>(?<contents>.*)<\/t[hd]>/Ums';
            preg_match_all($regExp, $tr, $matchesCol, PREG_SET_ORDER);

            $cols = array();
            foreach ($matchesCol as $matchCol) {
                $cols[] = array(
                    'tag'        => $matchCol['tag'],
                    'attributes' => $this->extractAttributes($matchCol['attr']),
                    'contents'   => $matchCol['contents']
                );
            }

            $rows[] = $cols;
        }

        return $rows;
    }

    protected function processExtraTable(array $table)
    {
        // process and adjusts table definition
        $table['thead'] = $this->processRows($table['thead']);
        $table['tbody'] = $this->processRows($table['tbody']);

        if (!$table['thead'] && !$table['tbody']) {
            $table['table'] = $this->processRows($table['table']);
        }

        return $table;
    }

    protected function processRows($rows)
    {
        $newRows = $rows;
        foreach ($rows as $rowIndex => $row) {

            foreach ($row as $colIndex => $col) {

                // an empty cell => colspanned cell
                if (!$col['contents']) {

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
                $quotes = array(
                    '"',
                    '&quot;',
                    '&#34;',
                    '&ldquo;',
                    '&#8220;',
                    '&rdquo;',
                    '&#8221;'
                );
                if (in_array($col['contents'], $quotes)) {

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

                            // set vertical alignement to 'middle'
                            if (!isset($newRows[$rowspanRow][$colIndex]['attributes']['style'])) {
                                $newRows[$rowspanRow][$colIndex]['attributes']['style'] = '';
                            } else {
                                $newRows[$rowspanRow][$colIndex]['attributes']['style'] .= ';';
                            }
                            $newRows[$rowspanRow][$colIndex]['attributes']['style'] .= 'vertical-align: middle;';
                        }
                        $newRows[$rowspanRow][$colIndex]['rowspan']++;

                        $newRows[$rowIndex][$colIndex]['ignore'] = true;
                    }
                }
            }
        }

        return $newRows;
    }

    protected function renderTable(array $table)
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
        foreach ($attrMatches as $attrMatch) {
            $attributes[trim($attrMatch['attr'])] = $attrMatch['value'];
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
