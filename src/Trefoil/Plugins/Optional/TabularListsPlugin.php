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
use Trefoil\Helpers\TabularList;
use Trefoil\Plugins\BasePlugin;

class TabularListsPlugin extends BasePlugin implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
            EasybookEvents::POST_PARSE => array('onItemPostParse', -1100), // after ParserPlugin
        );
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
        $regExp .= '<div +(?<pre>.*)';
        $regExp .= 'class="(?<class>tabularlist.*)"';
        $regExp .= '.*';
        $regExp .= '(?<post>.*)<\/div>)';
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $content = preg_replace_callback(
            $regExp,
            function ($matches) {
//                print_r($matches);

                $tabularList = new TabularList();

                $classMatches = [];
                if (preg_match('/tabularlist-table(-(?<num>\d+))?/', $matches['class'], $classMatches)) {
                    $numCategories = isset($classMatches['num']) ? $classMatches['num'] : null;
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
