<?php
/*
 * This file is part of the trefoil application.
 *
 * (c) Miguel Angel Gabriel <magabriel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Trefoil\Plugins\Optional;

use Easybook\Events\EasybookEvents;
use Easybook\Events\ParseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Trefoil\Exception\PluginException;
use Trefoil\Helpers\TabularList;
use Trefoil\Helpers\TextPreserver;
use Trefoil\Helpers\TrefoilMarkerProcessor;
use Trefoil\Plugins\BasePlugin;

/**
 * Class TabularListsPlugin
 *
 * This plugin implements the Tabular Lists funcionality, which represents a list
 * that can be alternatively shown as a table without loosing information (and vice versa).
 *
 * Its intended use is providing an adequate representation of tables in ebooks
 * (where wide tables are not appropriate) while maintaining the table as-is for wider
 * formats like PDF.
 *
 * The provided functions use a "trefoil marker" syntax ('{@..@}' blocks).
 *
 * Expected syntax:
 *      {@ tabularlist_begin (..arguments..) @}
 *          ...the tabularlist definition
 *      {@ tabularlist_end () @}
 *
 * @see     TabularList
 *
 * @package Trefoil\Plugins\Optional
 */
class TabularListsPlugin extends BasePlugin implements EventSubscriberInterface
{
    /**
     * @var int Number of currently open tabularlist_ calls
     */
    protected $tabularlistCalls = 0;

    public static function getSubscribedEvents()
    {
        return array(
            EasybookEvents::PRE_PARSE => ['onItemPreParse', -100], // after TwigExtensionPlugin
            EasybookEvents::POST_PARSE => ['onItemPostParse', -1100], // after ParserPlugin
        );
    }

    public function onItemPreParse(ParseEvent $event)
    {
        $this->init($event);

        $content = $event->getItemProperty('original');

        $content = $this->processTrefoilMarkers($content);

        $event->setItemProperty('original', $content);

        $msg = (new \ReflectionClass($this))->getShortName() . ': ' . $this->item['config']['content'] . ': ';
        if ($this->tabularlistCalls > 0) {
            throw new PluginException($msg . 'tabularlist_begin() call without ending previous.');
        } else if ($this->tabularlistCalls < 0) {
            throw new PluginException($msg . 'tabularlist_end() call without tabularlist_begin().');
        }
    }

    protected function processTrefoilMarkers($content)
    {
        $processor = new TrefoilMarkerProcessor();

        $processor->registerMarker('tabularlist_begin',
            function ($options = []) {
                $this->tabularlistCalls++;

                $renderColumns = null;
                foreach ($options as $editionOrFormat => $columns) {
                    if (strtolower($editionOrFormat) === 'all' ||
                        strtolower($editionOrFormat) === strtolower($this->format) ||
                        strtolower($editionOrFormat) === strtolower($this->edition)) {

                        $renderColumns = $columns;
                        break;
                    }
                }

                if ($renderColumns === null) {
                    return '<div class="tabularlist tabularlist-table" markdown="1">';
                }

                return sprintf(
                    '<div class="tabularlist tabularlist-table-%s" markdown="1">',
                    $renderColumns);
            }
        );

        $processor->registerMarker('tabularlist_end',
            function () {
                $this->tabularlistCalls--;
                return '</div>';
            }
        );

        return $processor->parse($content);
    }

    public function onItemPostParse(ParseEvent $event)
    {
        $this->init($event);

        $this->processTabularLists();

        $event->setItem($this->item);
    }

    protected function processTabularLists()
    {
        // capture tabular lists elements
        $regExp = '/';
        $regExp .= '(?<div>';
        $regExp .= '<div +(?<pre>[^>]*)';
        $regExp .= 'class="(?<class>tabularlist.*)"';
        $regExp .= '.*';
        $regExp .= '(?<post>.*)<\/div>)';
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $content = preg_replace_callback(
            $regExp,
            function ($matches) {
                $tabularList = new TabularList();

                $classMatches = [];
                if (preg_match('/tabularlist-table(-(?<num>\d+))?/', $matches['class'], $classMatches)) {
                    $numCategories = isset($classMatches['num']) ? (int)$classMatches['num'] : null;

                    // zero columns means "do not render as table", so return the list
                    if ($numCategories === 0) {
                        return $matches[0];
                    }

                    $tabularList->fromHtml($matches['div'], $numCategories);
                    return $tabularList->toHtmlTable();
                }

                return $matches[0];
            },
            $this->item['content']
        );

        $this->item['content'] = $content;
    }
}
