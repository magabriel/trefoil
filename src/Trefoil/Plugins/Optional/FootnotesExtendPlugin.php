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
 * This plugin extends footnotes to support several formats.
 *
 * Options are specified on an per-edition basis:
 *
 *     editions:
 *         <edition-name>
 *             plugins:
 *                 ...
 *                 options:
 *                     FootnotesExtend:
 *                         type: end  # [end, inject, item, inline]
 * 
 * Where:
 * 
 * - type 'end': This is the normal Markdown-rendered footnotes.
 *   They will be at the end of each book item, separated by a <hr/> tag.
 *   This is the default.
 *
 * - type 'inject: This is a variant of type 'end', where each item's
 *   footnotes will be injected to a certain injection point.
 *   Just write '<div class="footnotes"></div>' anywhere in each item
 *   where the footnotes should be injected.
 *
 * - type 'item': All the footnotes in the book will be collected and
 *   rendered in a separated item called 'footnotes' that need to
 *   exist in the book.
 *
 * - type 'inline: PrinceXML support inline footnotes, where the text
 *   of the note must be inlined into the text, instead of just a
 *   reference. Prince will manage the numbering.   
 * 
 *   Note that Prince manages footnotes as:
 *
 *      "text<span class="fn">Text of the footnote</span> more text"
 *
 *   One limitation is that the footnote text cannot contain block
 *   elements (as paragraphs, tables, lists). The plugin overcomes this
 *   partially by replacing paragraph tags with <br/> tags.
 */
class FootnotesExtendPlugin extends BasePlugin implements EventSubscriberInterface
{
    const FOOTNOTES_TYPE_END = 'end';
    const FOOTNOTES_TYPE_ITEM = 'item';
    const FOOTNOTES_TYPE_INJECT = 'inject';
    const FOOTNOTES_TYPE_INLINE = 'inline';

    /**
     * @var string Type of footnotes to generate
     */
    protected $footnotesType = '';

    /**
     * @var array The extracted footnotes the current book item
     */
    protected $footnotesCurrentItem = array();
    
    /**
     * @var string The current item footnotes (as text)
     */
    protected $itemFootnotesText = '';

    /* ********************************************************************************
     * Event handlers
     * ********************************************************************************
     */
    public static function getSubscribedEvents()
    {
        return array(
            EasybookEvents::POST_PARSE => array('onItemPostParse')
        );
    }

    public function onItemPostParse(ParseEvent $event)
    {
        $this->init($event);

        $this->processItem();

        // reload changed item
        $event->setItem($this->item);
    }

    /* ********************************************************************************
     * Implementation
     * ********************************************************************************
     */

    /**
     * Process a content item
     */
    protected function processItem()
    {
        // lazy initialize
        if (!isset($this->app['publishing.footnotes.items'])) {
            $this->app['publishing.footnotes.items'] = array();
        }

        $this->footnotesCurrentItem = array();

        // options
        $this->footnotesType = $this->getEditionOption('plugins.options.FootnotesExtend.type', 'end');

        $this->fixFootnotes();

        switch ($this->footnotesType) {
            case self::FOOTNOTES_TYPE_END:
                // nothing else to do
                break;

            case self::FOOTNOTES_TYPE_INLINE:

                $this->extractFootnotes();
                $this->inlineFootnotes();
                break;

            case self::FOOTNOTES_TYPE_ITEM:

                $this->extractFootnotes();
                $this->renumberReferences();
                break;

            case self::FOOTNOTES_TYPE_INJECT:

                $this->saveInjectionTarget();
                $this->extractFootnotes();
                $this->restoreInjectionTarget();
                $this->injectFootnotes();
                break;
        }

        // look if we need to remove the footnotes book item
        $this->removeUnneededFootnotesItem();
    }

    /**
     * Replace character ':' by '-' in footnotes ids because epubcheck does not like it.
     */
    protected function fixFootnotes()
    {
        $content = $this->item['content'];

        // fix footnotes ref in text
        $content = preg_replace('/id="fnref(\d*):/', 'id="fnref$1-', $content);
        $content = str_replace('href="#fn:', 'href="#fn-', $content);

        // fix footnotes
        $content = str_replace('id="fn:', 'id="fn-', $content);
        $content = preg_replace('/href="#fnref(\d*):/', 'href="#fnref$1-', $content);

        // fix return sign used
        $content = str_replace('&#8617;', '[&crarr;]', $content);

        $this->item['content'] = $content;
    }

    /**
     * Extracts anc collects all footnotes in the item.
     */
    protected function extractFootnotes()
    {
        $content = $this->item['content'];

        $this->itemFootnotesText = '';

        $regExp = '/';
        $regExp .= '<div class="footnotes">.*<ol>(?<fns>.*)<\/ol>.*<\/div>';
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        // PHP 5.3 compat
        $me = $this;

        $content = preg_replace_callback(
            $regExp,
            function ($matches) use ($me) {

                $this->itemFootnotesText = $matches[0];

                $regExp2 = '/';
                $regExp2 .= '<li.*id="(?<id>.*)">.*';
                $regExp2 .= '<p>(?<text>.*)&#160;<a .*href="#(?<backref>.*)"';
                $regExp2 .= '/Ums'; // Ungreedy, multiline, dotall

                preg_match_all($regExp2, $matches[0], $matches2, PREG_SET_ORDER);

                if ($matches2) {
                    foreach ($matches2 as $match2) {
                        $footnote = array(
                            'item'       => $this->item['toc'][0]['slug'],
                            'text'       => $match2['text'],
                            'id'         => $match2['id'],
                            'text'       => $match2['text'],
                            'backref'    => $match2['backref'],
                            'new_number' => count($this->app['publishing.footnotes.items']) + 1
                        );

                        // save for current item
                        $this->footnotesCurrentItem[$match2['id']] = $footnote;

                        // save for all items
                        $footnotes = $this->app['publishing.footnotes.items'];
                        $footnotes[$match2['id']] = $footnote;
                        $this->app['publishing.footnotes.items'] = $footnotes;
                    }
                }

                return '';
            },
            $content
        );

        $this->item['content'] = $content;
    }

    /**
     * Inline footnotes in the text, after the note reference.
     *
     * This is only useful for renderers that support automatic
     * inline footnotes, like PrinceXML.
     */
    protected function inlineFootnotes()
    {
        $content = $this->item['content'];

        $regExp = '/';
        $regExp .= '<sup id="(?<supid>fnref.?-.*)">';
        $regExp .= '<a(?<prev>.*)href="#(?<href>fn-.*)"(?<post>.*)>(?<number>.*)<\/a><\/sup>';
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        // PHP 5.3 compat
        $me = $this;

        $content = preg_replace_callback(
            $regExp,
            function ($matches) use ($me) {
                $footnotes = $me->footnotesCurrentItem;
                $footnote = $footnotes[$matches['href']];
                $text = $footnote['text'];

                // replace <p>...</p> with <br/> because no block elements are 
                // allowed inside a <span>.
                // The paragraph contents are also put inside a fake paragraph <span>
                // so they can be styled.

                $text = str_replace(
                    ['<p>', '</p>'],
                    ['<span class="p">', '<br/></span>'],
                    $text
                );
                $text = '<span class="p" >' . $text . '</span>';

                $html = sprintf(
                    '<span class="fn">%s</span>',
                    $text
                );

                return $html;
            },
            $content
        );

        $this->item['content'] = $content;
    }

    /**
     * Renumber all footnotes references to be correlative for the whole book.
     */
    protected function renumberReferences()
    {
        $content = $this->item['content'];

        $regExp = '/';
        $regExp .= '<sup id="(?<supid>fnref.?-.*)">';
        $regExp .= '<a(?<prev>.*)href="#(?<href>fn-.*)"(?<post>.*)>(?<number>.*)<\/a>';
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        // PHP 5.3 compat
        $me = $this;

        $content = preg_replace_callback(
            $regExp,
            function ($matches) use ($me) {
                $newNumber = $this->app['publishing.footnotes.items'][$matches['href']]['new_number'];

                $html = sprintf(
                    '<sup id="%s"><a%shref="#%s"%s>%s</a>',
                    $matches['supid'],
                    $matches['prev'],
                    $matches['href'],
                    $matches['post'],
                    $newNumber
                );

                return $html;
            },
            $content
        );

        $this->item['content'] = $content;
    }

    /**
     * Replace the footnotes injection target to keep extractFootnotes() from removing it.
     */
    protected function saveInjectionTarget()
    {
        $content = $this->item['content'];

        $content = preg_replace('/<div class="footnotes">\s*<\/div>/', '<div class="__footnotes"></div>', $content);

        $this->item['content'] = $content;
    }

    /**
     * Restore the injection target
     */
    protected function restoreInjectionTarget()
    {
        $content = $this->item['content'];

        $content = preg_replace('/<div class="__footnotes">\s*<\/div>/', '<div class="footnotes"></div>', $content);

        $this->item['content'] = $content;
    }

    /**
     * Inject footnotes at the injection target.
     * 
     * The injection target is a '<div class="footnotes"></div>' placed anywhere in the item text.
     */
    protected function injectFootnotes()
    {
        $content = $this->item['content'];

        $regExp = '/';
        $regExp .= '<div class="footnotes">\s*<\/div>';
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        // PHP 5.3 compat
        $me = $this;

        $content = preg_replace_callback(
            $regExp,
            function ($matches) use ($me) {

                $footnotes = $this->app->render(
                    '_footnotes.twig',
                    array('footnotes' => $this->footnotesCurrentItem)
                );

                return Toolkit::renderHTMLTag(
                    'div',
                    $footnotes,
                    array('class' => 'footnotes')
                );
            },
            $content
        );

        $this->item['content'] = $content;
    }

    /**
     * Ensure the footnotes item is removed from the book if it is not needed.
     */
    protected function removeUnneededFootnotesItem()
    {
        // only for footnotes item
        if ($this->item['config']['element'] !== 'footnotes') {
            return;
        }

        // instruct the publisher to remove 'footnotes' item from book
        // if footnotes type is not 'item'
        if ($this->footnotesType !== self::FOOTNOTES_TYPE_ITEM) {

            $this->item['remove'] = true;

            return;
        }

        // instruct the publisher to remove 'footnotes' item from book
        // if footnotes type is 'item' but not footnotes
        if (count($this->app['publishing.footnotes.items']) == 0) {
            $this->item['remove'] = true;
            $this->writeLn("No footnotes found in text.", 'info');
        }

    }
}
