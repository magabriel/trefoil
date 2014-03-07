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
use Trefoil\Helpers\DropCaps;
use Trefoil\Plugins\BasePlugin;

/**
 * Add manual title labels to book items.
 *
 * For formats: all
 *
 * Item labels are rendered with different markup than the title, creating a nice effect,
 * like "Chapter 1 - The first chapter title" ("Chapter 1" is the label while "The first
 * chapter title" is the title).
 * 
 * Automatic labels can be added to item titles by easybook using its labeling mechanism, 
 * but sometimes it could be useful having a way to manually specify labels using markup
 * in the source file.
 *
 * This plugin provides simple markup to achieve just this, just enclosing the label part
 * in "[[..]]".
 *
 * Example:
 * 
 *   # [[Chapter 1]] The first chapter title
 * 
 */
class ManualTitleLabelsPlugin extends BasePlugin implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
            EasybookEvents::POST_PARSE => array('onItemPostParse', -1100) // apter ParserPlugin
        );
    }

    public function onItemPostParse(ParseEvent $event)
    {
        $this->init($event);

        $this->addTitleLabel();
        
        $event->setItem($this->item);
    }

    /**
     * Look for '[[label]] title' markup in title and update 
     * the item accordingly.
     */
    protected function addTitleLabel()
    {
        $regExp = '/';
        $regExp .= '^\[\[(?<label>.*)\]\] ?(?<title>.*)$'; 
        $regExp .= '/U'; // Ungreedy

        $me = $this;
        $this->item['title'] = preg_replace_callback(
            $regExp,
            function ($matches) use ($me) {
                
                // the new item label
                $me->item['label'] = $matches['label'];
                
                // the toc
                $me->item['toc'][0]['label'] = $matches['label'];
                $me->item['toc'][0]['title'] = $matches['title'];
                
                // the new title
                return $matches['title'];
            },
            $this->item['title']
        );
    }
}
