<?php
namespace Trefoil\Plugins;

use Trefoil\Helpers\DropCaps;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Easybook\Events\EasybookEvents;
use Easybook\Events\BaseEvent;
use Easybook\Events\ParseEvent;
use Trefoil\Events\TrefoilEvents;
use Trefoil\Util\Toolkit;

/**
 * Add drop caps to the book.
 *
 * Options can be set in the book's config.yml:
 *
 *     editions:
 *         <edition-name>
 *             DropCaps:
 *                 levels:     [1]           # 1 to 6 (default: 1)
 *                 mode:       letter        # letter, word (default: letter)
 *                 length:     1             # number of letters or words to highlight (default: 1)
 *                 coverage:   ['chapter']   # book elements to process
 */
class DropCapsPlugin extends BasePlugin implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
                EasybookEvents::POST_PARSE => array('onItemPostParse', -1100)
                );
    }

    public function onItemPostParse(BaseEvent $event)
    {
        $this->init($event);

        $content = $event->getContent();

        $content = $this->addDropCaps($content);

        $event->setContent($content);
    }

    /**
     * Add drop caps markup
     *
     * @param string $content
     * @return string
     */
    protected function addDropCaps($content)
    {
        $length = $this->getEditionOption('DropCaps.length', 1);
        $mode = $this->getEditionOption('DropCaps.mode', 'letter');
        $levels = $this->getEditionOption('DropCaps.levels', array(1));
        $elements = $this->getEditionOption('DropCaps.elements', array('chapter'));

        if (!in_array($this->item['config']['element'], $elements)) {
            // not for this element
            return $content;
        }

        $dropCaps = new DropCaps($content, $mode, $length);

        /* the paragraph that starts the text must be treated separately
         * because it doesn't have a preceding heading tag (it will be under
         * the H1 item title tag that is not there yet),
         */
        if (in_array(1, $levels)) {
            $dropCaps->createForFirstParagraph();
        }

        // process the other paragraphs in the text that follow a heading tag
        $dropCaps->createForHeadings($levels);

        $content = $dropCaps->getOutput();

        return $content;
    }
}

