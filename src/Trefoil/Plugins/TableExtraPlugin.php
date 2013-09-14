<?php
namespace Trefoil\Plugins;
use Symfony\Component\Finder\Finder;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Easybook\Events\EasybookEvents as Events;
use Easybook\Events\BaseEvent;
use Easybook\Events\ParseEvent;

class TableExtraPlugin implements EventSubscriberInterface
{
    protected $app;
    protected $item;

    public static function getSubscribedEvents()
    {
        return array(
                Events::POST_DECORATE => 'onItemPostDecorate'
        );
    }

    public function onItemPostDecorate(BaseEvent $event)
    {
        $this->app = $event->app;
        $this->output = $this->app->get('console.output');
        $edition = $this->app['publishing.edition'];
        $this->item = $event->getItem();
        $content = $this->item['content'];

        $content = $this->processTables($content);

        $this->item['content'] = $content;
        $event->setItem($this->item);
    }

    public function processTables($content)
    {
        $regExp = '/';
        $regExp .= '(?<table><table.*<\/table>)';
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $me = $this;
        $content = preg_replace_callback(
            $regExp,
            function ($matches) use ($me)
                      {
                      // PRUEBAS
                      //print_r($matches['table']);

                      $table = $this->parseTable($matches['table']);
                      if (!$table) {
                      return $matches[0];
                      }
                      $table = $this->processExtraTable($table);
                      $html = $this->renderTable($table);

                      return $html;
                      },
                      $content);

        return $content;
    }

    protected function parseTable($tableHtml)
    {
        $table = array();

        $table['thead'] = $this->extractRows($tableHtml, 'thead');
        $table['tbody'] = $this->extractRows($tableHtml, 'tbody');

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
            $regExp = '/<t[hd](?<attr>.*)>(?<contents>.*)<\/t[hd]>/Ums';
            preg_match_all($regExp, $tr, $matchesCol, PREG_SET_ORDER);

            $cols = array();
            foreach ($matchesCol as $matchCol) {
                $cols[] = array(
                        'attributes' => $this->extractAttributes($matchCol['attr']),
                        'contents' => $matchCol['contents']
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
                        if (!isset($newRows[$rowIndex][$j]['ignore'])) {
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
                if ('&#8221;' == $col['contents']) {

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
                            }
                            $newRows[$rowspanRow][$colIndex]['attributes']['style'] .= ';vertical-align: middle;';
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

        $html .= '<thead>';
        $html .= $this->renderRows($table['thead'], 'th');
        $html .= '</thead>';

        $html .= '<tbody>';
        $html .= $this->renderRows($table['tbody'], 'td');
        $html .= '</tbody>';

        $html .= '</table>';

        return $html;
    }

    protected function renderRows($rows, $rowTag = 'tr')
    {
        $html = '';

        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $col) {
                if (!isset($col['ignore'])) {
                    $rowspan = isset($col['rowspan']) ? sprintf('rowspan="%s"', $col['rowspan']) : '';
                    $colspan = isset($col['colspan']) ? sprintf('colspan="%s"', $col['colspan']) : '';

                    $attributes = $this->renderAttributes($col['attributes']);

                    $html .= sprintf('<%s %s %s %s>%s</%s>', $rowTag, $rowspan, $colspan, $attributes, $col['contents'], $rowTag);
                }
            }
            $html .= '</tr>';
        }

        return $html;
    }

    /**
     * @param string $string
     * @return array of attribures
     */
    protected function extractAttributes($string)
    {
        $attributes = array();

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

