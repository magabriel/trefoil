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
use Easybook\Events\ParseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Trefoil\Plugins\BasePlugin;

/**
 * This plugin transforms Markdown footnotes markup into inline footnotes.
 *
 * @deprecated
 * PrinceXMl manages footnotes as:
 *      "text<span class="fn">Text of the footnote</span> more text"
 * This plugin transforms the Markdown-generated footnotes to the PrinceXML
 * format.
 * Note that one limitation is that the footnote text cannot contain block
 * elements (as paragraphs, tables, lists). The plugin overcomes this
 * partially by replacing paragraph tags with <br/> tags.
 */
class FootnotesInlinePlugin extends BasePlugin implements EventSubscriberInterface
{

    /**
     * The footnotes
     *
     * @var array
     */
    protected $footnotes = [];

    /**
     * @param array $footnotes
     */
    protected function setFootnotes($footnotes)
    {
        $this->footnotes = $footnotes;
    }

    /**
     * @return array
     */
    protected function getFootnotes(): array
    {
        return $this->footnotes;
    }


    /* ********************************************************************************
     * Event handlers
     * ********************************************************************************
     */
    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EasybookEvents::PRE_PARSE    => ['onItemPreParse', +100],
            EasybookEvents::POST_PARSE   => ['onItemPostParse'],
            EasybookEvents::POST_PUBLISH => 'onPostPublish',
        ];
    }

    /**
     * @param ParseEvent $event
     */
    public function onItemPreParse(ParseEvent $event)
    {
        $this->init($event);

        if ($this->item['config']['element'] === 'footnotes') {

            $this->app['publishing.footnotes.items'] = [];

            return;
        }

        $this->saveFootnotes();
    }

    /**
     * @param ParseEvent $event
     */
    public function onItemPostParse(ParseEvent $event)
    {
        $this->init($event);

        $this->processItem();

        // reload changed item
        $event->setItem($this->item);
    }

    /**
     * @param BaseEvent $event
     */
    public function onPostPublish(BaseEvent $event)
    {
        $this->init($event);

        $this->deprecationNotice();
    }

    /* ********************************************************************************
     * Implementation
     * ********************************************************************************
     */

    /**
     * For a content item to be processed, extract generated footnotes.
     */
    protected function processItem()
    {
        $this->extractFootnotes();
        $this->inlineFootnotes();
    }

    protected function extractFootnotes()
    {
        $content = $this->item['content'];

        $regExp = '/';
        $regExp .= '<div class="footnotes"[^>]*>.*<ol>(?<fns>.*)<\/ol>.*<\/div>';
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $content = preg_replace_callback(
            $regExp,
            function ($matches) {

                $regExp2 = '/';
                $regExp2 .= '<li.*id="(?<id>.*)"[^>]*>.*';
                $regExp2 .= '<p>(?<text>.*)&#160;<a .*href="#(?<backref>.*)"';
                $regExp2 .= '/Ums'; // Ungreedy, multiline, dotall

                preg_match_all($regExp2, $matches[0], $matches2, PREG_SET_ORDER);

                /** @var string[] $matches2 */
                if ($matches2) {
                    /** @var string[] $match2 */
                    foreach ($matches2 as $match2) {
                        $footnotes = $this->getFootnotes();
                        $footnotes[$match2['id']] = [
                            'text' => $match2['text'],
                        ];
                        $this->setFootnotes($footnotes);
                    }
                }

                return '';
            },
            $content);

        $this->item['content'] = $content;
    }

    protected function inlineFootnotes()
    {
        $content = $this->item['content'];

        $regExp = '/';
        $regExp .= '<sup id="(?<supid>fnref.?:.*)">';
        $regExp .= '<a(?<prev>.*)href="#(?<href>fn:.*)"(?<post>.*)>(?<number>.*)<\/a><\/sup>';
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $content = preg_replace_callback(
            $regExp,
            function ($matches) {
                $footnotes = $this->getFootnotes();
                $footnote = $footnotes[$matches['href']];
                $text = $footnote['text'];

                // replace <p>...</p> with <br/> because no block elements are 
                // allowed inside a <span>.
                // The paragraph contents are also put inside a fake paragraph <span>
                // so they can be styled.

                $text = str_replace(
                    ['<p>', '</p>'],
                    ['<span class="p">', '<br/></span>'],
                    $text);
                $text = '<span class="p" >'.$text.'</span>';

                $html = sprintf(
                    '<span class="fn">%s</span>',
                    $text);

                return $html;
            },
            $content);

        $this->item['content'] = $content;
    }

    protected function saveFootnotes()
    {
        $this->app['publishing.footnotes.items'] = $this->footnotes;
    }
}
