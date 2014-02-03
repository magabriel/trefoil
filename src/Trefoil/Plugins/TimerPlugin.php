<?php
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

/**
 * It registers the start and the end of the book publication
 * to measure the elapsed time.
 * 
 * == This sets the right times, taking into account trefoil actions == 
 */
class TimerPlugin extends BasePlugin implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
            EasybookEvents::PRE_PUBLISH  => array('registerPublicationStart', +2000), // before everything
            EasybookEvents::POST_PUBLISH => array('registerPublicationEnd', -2000) // after everything
        );
    }

    /**
     * It registers the timestamp of the book publication start.
     *
     * @param BaseEvent $event The event object that provides access to the application
     */
    public function registerPublicationStart(BaseEvent $event)
    {
        $event->app['app.timer.start'] = microtime(true);
    }

    /**
     * It registers the timestamp of the book publication end.
     *
     * @param BaseEvent $event The event object that provides access to the application
     */
    public function registerPublicationEnd(BaseEvent $event)
    {
        $event->app['app.timer.finish'] = microtime(true);
    }
}
