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
use Trefoil\Util\Toolkit;

/**
 * Several tweaks to make the ebook more compatible with Kindle MOBI format
 *
 * - **Paragraphs inside lists:** Convert all `<p>` tags inside a `<li>` tag
 * to line breaks (`<br/>`).
 *
 * - **Table cell alignment:** Assign explicit alignment to table cell tags via style tag.
 *
 */
class KindleTweaksPlugin extends BasePlugin implements EventSubscriberInterface
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
     * ONLY replaces the first `<p>..</p>` inside the `<li>..</li>` (the one that
     * immediately follows the `<li>`) to better preserve formatting for
     * newer readers.
     *
     * @param string $content
     *
     * @return string
     */
    protected function paragraphsInsideLists($content)
    {
        // iterate 4 times to ensure embedded lists are converted
        for ($i = 0; $i < 4; $i++) {

            $oldContent = $content;

            $content = preg_replace_callback(
                '/<li(?<liatt>[^>]*)>[\w\n]*<p>(?<p1>.*)<\/p>(?<rest>.*)<\/li>/Ums',
                function ($matches) {
                    $liatt = $matches['liatt'];
                    $p1 = $matches['p1'];
                    $rest = $matches['rest'];

                    // add class "no-p" to this <li> 
                    $liattArray = Toolkit::parseHTMLAttributes($liatt);
                    if (!isset($liattArray['class'])) {
                        $liattArray['class'] = '';
                    }
                    $liattArray['class'] = trim($liattArray['class'] . ' no-p');

                    return sprintf(
                        '<li %s>%s%s</li>',
                        Toolkit::renderHTMLAttributes($liattArray),
                        $p1,
                        $rest
                    );
                },
                $content
            );

            if ($oldContent === $content) {
                // no changes
                break;
            }

        }

        return $content;
    }

    /**
     * Assign explicit alignment via style to table cells
     *
     * @param string $content
     *
     * @return string
     */
    protected function tableCellsAlignment($content)
    {
        $content = preg_replace_callback(
            '/<(?<tag>th|td) align="(?<align>.*)">/Ums',
            function ($matches) {
                $tag = $matches['tag'];
                $align = $matches['align'];

                return sprintf('<%s align="%s" style="text-align:%s">', $tag, $align, $align);
            },
            $content
        );

        return $content;
    }
}
