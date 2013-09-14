<?php
namespace Trefoil\Plugins;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Easybook\Events\EasybookEvents as Events;
use Easybook\Events\ParseEvent;

use Symfony\Component\DomCrawler\Crawler;

/**
 * This plugin allows configuration options to appear in the book contents
 * @deprecated
 */
class ConfigOptionsInContentPlugin implements EventSubscriberInterface
{
    protected $app;
    protected $item;

    public static function getSubscribedEvents()
    {
        return array(
                Events::PRE_PARSE => 'onItemPreParse'
        );
    }

    public function onItemPreParse(ParseEvent $event)
    {
        $this->app = $event->app;
        $this->item = $event->getItem();

        $content = $event->getOriginal();

        # avoid problems with markdown extra syntax for ids in headers
        $content = str_replace('{#', '{@', $content);

        $content = $this->replaceBookOptions($content);

        $content = str_replace('{@', '{#', $content);

        $event->setOriginal($content);
    }

    protected function replaceBookOptions($content)
    {
        // 'book' and 'edition' are already set. We only need to set 'item'.
        $vars = array('item' => $this->item);
        $content = $this->app->renderString($content, $vars);

        return $content;
    }

}
