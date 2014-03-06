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
 * Add drop caps to the book.
 *
 * For formats: all
 *
 * It provides two working modes:
 *
 * 1.- Automatic dropcaps: Depending on the options set in the plugins configuration
 *     section inside the book's config.yml:
 *
 *     editions:
 *         <edition-name>
 *             plugins:
 *                 ...
 *                 options:
 *                     DropCaps:
 *                         levels:     [1]           # 1 to 6 (default: 1)
 *                         mode:       letter        # letter, word (default: letter)
 *                         length:     1             # number of letters or words to highlight (default: 1)
 *                         coverage:   ['chapter']   # book elements to process
 *
 * 2. Manual dropcaps: Besides adding the HTML markup directly, which off course is still
 *    possible, a Markdown-like markup is provided for greater convenience:
 *
 *    [[T]]his text has first-letter dropcaps.
 *
 *    [[But]] this text has first-word dropcaps.
 *
 */
class DropCapsPlugin extends BasePlugin implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
            EasybookEvents::POST_PARSE => array('onItemPostParse', -1100)
        );
    }

    public function onItemPostParse(ParseEvent $event)
    {
        $this->init($event);

        $content = $event->getItemProperty('content');

        $content = $this->addDropCaps($content);

        $event->setItemProperty('content', $content);
    }

    /**
     * Add drop caps markup
     *
     * @param string $content
     *
     * @return string
     */
    protected function addDropCaps($content)
    {
        $length = $this->getEditionOption('plugins.options.DropCaps.length', 1);
        $mode = $this->getEditionOption('plugins.options.DropCaps.mode', 'letter');
        $levels = $this->getEditionOption('plugins.options.DropCaps.levels', array(1));
        $elements = $this->getEditionOption('plugins.options.DropCaps.elements', array('chapter'));

        // ensure levels is an array
        if (!is_array($levels)) {
            $levels = array(1);
        }
        
        if (!in_array($this->item['config']['element'], $elements)) {
            // not for this element
            return $content;
        }

        $dropCaps = new DropCaps($content, $mode, $length);

        // first of all, process the Markdown-style markup
        $dropCaps->createForMarkdownStyleMarkup();

        /* the paragraph that starts the text must be treated separately
         * because it doesn't have a preceding heading tag (it will be under
         * the H1 item title tag that is not there yet),
         */
        if (in_array(1, $levels)) {
            $dropCaps->createForFirstParagraph();
        }

        // process the other paragraphs in the text that follow a heading tag
        $dropCaps->createForHeadings($levels);

        // process the paragraphs with manually-added dropcaps markup
        $dropCaps->processManualMarkup();

        $content = $dropCaps->getOutput();

        return $content;
    }
}
