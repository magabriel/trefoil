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
use Trefoil\Plugins\BasePlugin;

/**
 * Support for literal lists.
 *
 * A literal list is an ordered list using literal other than numbers,
 * i.e.: a), b)... or I), II).
 *
 * The plugin detects the list by the type of starting literal of the
 * first item in an unordered list, and adds class "list-literal"
 * to the <ul> tag to alow styling.
 *
 * Input:
 *
 *      <ul>
 *          <li>a) First item.</li>
 *          ...
 *          <li>x) Last item.</>
 *      </ul>
 *
 * Output:
 *
 *      <ul class="list-literal">
 *          <li>a) First item.</li>
 *          ...
 *          <li>x) Last item.</>
 *      </ul>
 *
 */
class LiteralListsPlugin extends BasePlugin implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
            EasybookEvents::POST_PARSE => array('onItemPostParse', -1010) // after ParserPlugin
        );
    }

    public function onItemPostParse(ParseEvent $event)
    {
        $this->init($event);

        $content = $event->getItemProperty('content');

        $content = $this->doLiteralLists($content);

        $event->setItemProperty('content', $content);
    }

    /**
     * Add class "list-literal" to literal lists.
     *
     * @param string $content
     *
     * @return string
     */
    protected function doLiteralLists($content)
    {
        // preg to extract whole unordered lists
        $pregUl = '/<ul>(?<items>.*)<\/ul>/Ums';

        $content = preg_replace_callback(
            $pregUl,
            function ($matchesUl) {

                // preg to extract list items in list
                $pregLi = '/<li>(?<li>.*)<\/li>/Ums';

                // no detected by now
                $literalListDetected = false;

                // examine the first one looking for starting literal, like a) 
                if (preg_match($pregLi, $matchesUl['items'], $matchesLi)) {

                    // preg to get the list literal (char + ")" or ".").
                    // note that list items can be inside a <p> tag.
                    $pregLiteral = '/^(<p>)?(?<literal>[a-zA-Z])[\)\.]/Ums';

                    if (preg_match($pregLiteral, $matchesLi['li'], $matchesLiteral)) {
                        $literalListDetected = true;
                    }
                }

                // reconstruct the list with the class if this is a literal list
                $html = sprintf(
                    '<ul%s>%s</ul>',
                    $literalListDetected ? ' class="literal-list"' : '',
                    $matchesUl['items']
                );

                return $html;
            },
            $content
        );

        return $content;
    }

}
