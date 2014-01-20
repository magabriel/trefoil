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

use Easybook\Events\BaseEvent;
use Easybook\Events\EasybookEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Trefoil\Helpers\TableExtra;

/**
 * Implements the extra syntax for tables, allowing colspanned and rowspanned cells.
 *
 * - Empty cell => colspanned
 * - Cell with only a double quote => rowspanned
 *
 */
class TableExtraPlugin extends BasePlugin implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
            EasybookEvents::POST_DECORATE => 'onItemPostDecorate'
        );
    }

    public function onItemPostDecorate(BaseEvent $event)
    {
        $this->init($event);

        $item = $event->getItem();
        $content = $item['content'];

        $tableExtra = new TableExtra();
        $content = $tableExtra->processAllTables($content);

        $item['content'] = $content;
        $event->setItem($item);
    }
}
