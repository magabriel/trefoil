<?php
namespace Trefoil\Plugins;

use Easybook\Events\BaseEvent;
use Easybook\Events\EasybookEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Several tweaks to make the ebook more compatible with Kindle MOBI format
 *
 * <li>
 * <b>Paragraphs inside lists:</b> Convert all &lt;p&gt tags inside a &lt;li&gt; tag
 * to line breaks (&lt;br/&gt;).
 *
 * <li>
 * <b>Table cell alignment:</b> Assign explicit alignment to table cell tags via style tag.
 *
 */
class KindleTweaksPlugin extends BasePlugin implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
                EasybookEvents::POST_PARSE => array('onItemPostParse', -1000) // the latest possible
                );
    }

    public function onItemPostParse(BaseEvent $event)
    {
        $this->init($event);

        // only for epub or mobi
        if (!in_array($this->format, array('Epub', 'Mobi'))) {
            return;
        }

        $content = $event->getItemProperty('content');

        $content = $this->paragraphsInsideLists($content);
        $content = $this->tableCellsAlignment($content);

        $event->setItemProperty('content', $content);
    }

    /**
     * Convert paragraphs inside list elements to line breaks
     *
     * @param string $content
     * @return string
     */
    protected function paragraphsInsideLists($content)
    {
        $content = preg_replace_callback('/<li>(?<li>.*)<\/li>/Ums',
                function ($matches)
                {
                   $li = preg_replace_callback('/<p>(?<ptext>.*)<\/p>/Ums',
                           function ($matches2)
                           {
                               return sprintf('%s<br/>', $matches2['ptext']);
                           }, $matches['li']);

                   // strip out the last <br/> (superfluous)
                   if ('<br/>' == substr($li, -strlen('<br/>'))) {
                       $li = substr($li, 0, -strlen('<br/>'));
                   }

                   return sprintf('<li>%s</li>', $li);
                }, $content);

        return $content;
    }

    /**
     * Assing explicit alignment via style to table cells
     *
     * @param string $content
     * @return string
     */
    protected function tableCellsAlignment($content)
    {
        $content = preg_replace_callback('/<(?<tag>th|td) align="(?<align>.*)">/Ums',
                function ($matches)
                {
                    $tag = $matches['tag'];
                    $align = $matches['align'];
                    return sprintf('<%s align="%s" style="text-align:%s">', $tag, $align, $align);
                }, $content);

        return $content;
    }
}

