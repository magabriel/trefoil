<?php

namespace Trefoil\Helpers;

/**
 * Class Table abstracts the representation of a table (as in "HTML table").
 * 
 * This class extends ArrayObject, so an instance can be manipulated as an
 * array.
 *      Example: $table['tbody'][1][2] => cell[1][2] in the <tbody> section.
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
     * @param $htmlTable HTML <table> tag
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
        $output = $this->renderTableToHtml();

        return $output;
    }

    /**
     * @param     $htmlTable
     * @param int $flags
     */
    public function parseHtmlTable($htmlTable, $flags = self::CREATE_TBODY)
    {
        $this->exchangeArray(array());

        $this['thead'] = $this->extractRows($htmlTable, 'thead');
        $this['tbody'] = $this->extractRows($htmlTable, 'tbody');

        if (!$this['thead'] && !$this['tbody']) {
            if ($flags & self::CREATE_TBODY) {
                $this['tbody'] = $this->extractRows($htmlTable, 'table');

                return;
            }

            $this['table'] = $this->extractRows($htmlTable, 'table');
        }
    }

    /**
     * @param        $htmlTable
     * @param string $tag
     *
     * @return array of rows
     */
    protected function extractRows($htmlTable, $tag = 'tbody')
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
     * @return bool True if the table have some rows
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
            $html .= $this->renderRows($this['thead']);
            $html .= '</thead>';
        }

        if (isset($this['tbody']) && $this['tbody']) {
            $html .= '<tbody>';
            $html .= $this->renderRows($this['tbody']);
            $html .= '</tbody>';
        }

        if (isset($this['table']) && $this['table']) {
            $html .= $this->renderRows($this['table']);
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
    protected function renderRows(array $rows)
    {
        $html = '';

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

}