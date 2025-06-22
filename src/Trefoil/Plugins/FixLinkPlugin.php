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

namespace Trefoil\Plugins;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Easybook\Events\EasybookEvents;
use Easybook\Events\BaseEvent;
use Trefoil\Util\Toolkit;

/**
 * This plugin fixes BUG #1 in LinkPlugin.
 *
 * That plugin cannot be modified because it is part of the Easybook library.
 * This plugin is a workaround to fix the bug.
 */
class FixLinkPlugin extends BasePlugin implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            EasybookEvents::POST_PARSE => array('fixBug001', 10) // Before LinkPlugin
        ];
    }

    /**
     * This method fixes the bug identified as BUG #1 in LinkPlugin.
     * 
     * BUG #1: The internal links are not marked as internal links in the PDF edition.
     *
     * @param BaseEvent $event The event object that provides access to the application
     */
    public function fixBug001(BaseEvent $event)
    {
        // Internal links are only marked for the PDF editions
        if ('pdf' != $event->app->edition('format')) {
            return;
        }

        /*
         * LinkPlugin::markInternalLinks() method has a bug that prevents internal links 
         * from being properly rendered in PDF editions. 
         * 
         * To fix it, just perform the correct operation here, before LinkPlugin::markInternalLinks() is called.
         */
        $item = $event->getItem();

        $item['content'] = preg_replace_callback(
            '/<a (.*)>(.*)<\/a>/Us',
            function ($matches) {
                $attribs = Toolkit::parseHTMLAttributes($matches[1]);
                $linkText = $matches[2];
                if (!str_starts_with($attribs['href'], "#")) {
                    // This is not an internal link, so we don't need to fix it
                    return $matches[0];
                }

                $attribs['class'] = trim('internal ' . ($attribs['class'] ?? ''));

                // IMPORTANT: The link must be rendered with the class attribute before the href attribute,
                // to avoid triggering the bug in LinkPlugin::markInternalLinks().
                return sprintf(
                    '<a class="%s" href="%s">%s</a>',
                    $attribs['class'],
                    $attribs['href'],
                    $linkText
                );
            },
            $item['content']
        );

        $event->setItem($item);
    }
}
