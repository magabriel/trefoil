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

namespace Trefoil\Plugins\Optional;

use Easybook\Events\BaseEvent;
use Easybook\Events\EasybookEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Trefoil\Helpers\TableExtra;
use Trefoil\Plugins\BasePlugin;

/**
 * Implements the extra syntax for tables, allowing colspanned and rowspanned cells.
 * - Empty cell => colspanned
 * - Cell with only a double quote => rowspanned
 */
class TableExtraPlugin extends BasePlugin implements EventSubscriberInterface
{
    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EasybookEvents::POST_DECORATE => 'onItemPostDecorate',
        ];
    }

    /**
     * @param BaseEvent $event
     */
    public function onItemPostDecorate(BaseEvent $event)
    {
        $this->init($event);

        $item = $event->getItem();
        $content = $item['content'];

        $tableExtra = new TableExtra();
        $tableExtra->setMarkdownParser($this->app['parser']);
        $content = $tableExtra->processAllTables($content);

        $item['content'] = $content;
        $event->setItem($item);
    }
}
