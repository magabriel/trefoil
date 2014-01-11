<?php
namespace Trefoil\Plugins;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Easybook\Events\EasybookEvents;
use Easybook\Events\ParseEvent;

use Symfony\Component\DomCrawler\Crawler;

/**
 * This plugin replaces certain symbols with its typographic equivalents.
 * - Quotes ('".."'), backtick quotes ('``..'''), ellipsis('...')
 * - Dashes ('--')
 * - Angle quotes ('<<' and '>>')
 * - Checkboxes ('[ ]' and '[/]')
 *
 * Options are specified on an per-edition basis:
 *
 *     editions:
 *         <edition-name>
 *             plugins:
 *                 ...
 *                 options:
 *                     Typography:
 *                         checkboxes: true
 *                         fix_spanish_style_dialog: false
 *
 * - fix_spanish_style_dialog: Convert starting '-' (dash) in paragraphs to em-dash.
 *
 * @see http://daringfireball.net/projects/smartypants/
 */
class TypographyPlugin extends BasePlugin implements EventSubscriberInterface
{
    const BALLOT_BOX_HTMLENTITY = '&#9744;';
    const BALLOT_BOX_CHECKED_HTMLENTITY = '&#9745;';
    const EMDASH_UNICODE = '\x{2014}';
    const EMDASH_HTMLENTITY = '&#8212;';

    protected $links = array();

    public static function getSubscribedEvents()
    {
        return array(
                EasybookEvents::PRE_PARSE => 'onItemPreParse',
                EasybookEvents::POST_PARSE => 'onItemPostParse'
        );
    }

    public function onItemPreParse(ParseEvent $event)
    {
        $this->init($event);

        $content = $event->getItemProperty('original');

        $content = $this->preProcessAngleQuotesForSmartypants($content);

        $event->setItemProperty('original', $content);
    }

    public function onItemPostParse(ParseEvent $event)
    {
        $this->init($event);

        $content = $this->item['content'];

        $content = $this->smartyPantsPostParse($content);
        $content = $this->replaceSymbolsPostParse($content);

        if ($this->getEditionOption('plugins.options.Typography.fix_spanish_style_dialog')) {
            $content = $this->fixSpanishStyleDialog($content);
        }

        $this->item['content'] = $content;
        $event->setItem($this->item);
    }

    protected function smartyPantsPostParse($content)
    {
        $options = '';

        // normal SmartyPants processing
        $options.= 'qbed'; // quotes, backticks ellipses and dashes

        // refinements for SmartyPants Typographer
        $options.= 'g'; // angle quotes (needs preparation)
        $options.= 'f-'; // do not add space inside angle quotes

        // and go!!
        $content = \SmartyPants($content, $options);

        return $content;
    }

    /**
     * Prepare angle quotes '<<' and '>>' to be processed by SmartyPants:
     * ensure there is a space before '>>' and  after '<<'
     *
     * @param string $content
     * @return string
     */
    protected function preProcessAngleQuotesForSmartypants($content)
    {
        // opening <<
        $regExp = '/';
        $regExp .= '(?<prev>[^\<])'; // not a <
        $regExp .= '(?<sign>\<\<)'; // 2 <
        $regExp .= '(?<next>[^\< ])'; // not a < or space
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $me = $this;
        $content = preg_replace_callback($regExp,
                function ($matches) use ($me)
                {
                    // put a space after the <<
                    return $matches['prev']."<< ".$matches['next'];
                }, $content);

        // closing >>
        $regExp = '/';
        $regExp .= '(?<prev>[^\> ])'; // not a > or space
        $regExp .= '(?<sign>\>\>)'; // 2 >
        $regExp .= '(?<next>[^\>])'; // not a >
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $me = $this;
        $content = preg_replace_callback($regExp,
                function ($matches) use ($me)
                {
                    // put a space before the <<
                    return $matches['prev']." >>".$matches['next'];
                }, $content);

        return $content;
    }

    protected function replaceSymbolsPostParse($content)
    {
        if ($this->getEditionOption('plugins.options.Typography.checkboxes', true)) {
            $content = $this->replaceCheckboxes($content);
        }

        return $content;
    }

    protected function replaceCheckboxes($content)
    {
        $regExp = '/';
        $regExp .= '(?<sign>';
        $regExp .= '\[ \]'; // open bracket + space + close bracket
        $regExp .= ')';
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $me = $this;
        $content = preg_replace_callback($regExp,
                function ($matches) use ($me)
                {
                    return self::BALLOT_BOX_HTMLENTITY; // ballot box (box without checkmark)
                }, $content);

        $regExp = '/';
        $regExp .= '(?<sign>';
        $regExp .= '\[\/\]'; // open bracket + slash + close bracket
        $regExp .= ')';
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $me = $this;
        $content = preg_replace_callback($regExp,
                function ($matches) use ($me)
                {
                    return self::BALLOT_BOX_CHECKED_HTMLENTITY; // ballot box with checkmark
                }, $content);

        return $content;
    }

    /**
     * Fix Spanish-style dialog for content
     *
     * @param string $content
     * @return string
     */
    protected function fixSpanishStyleDialog($content)
    {
        // process all paragraphs begining with a dash or em-dash
        $regExp = '/';
        $regExp .= '<p>[-' . self::EMDASH_UNICODE . '](?<text>[^ ].*)<\/p>';
        $regExp .= '/Umsu'; // Ungreedy, multiline, dotall, unicode <= PLEASE NOTE UNICODE FLAG

        $content = preg_replace_callback($regExp,
                function ($matches)
                {
                    // replace the dialog inside the paragraph
                    $text = $this->replaceSpanishStyleDialog($matches['text']);

                    // return the paragraph replacing the starting dash by an em-dash
                    return sprintf('<p>' . self::EMDASH_HTMLENTITY . '%s</p>', $text);
                }, $content);

        return $content;
    }

    /**
     * Replace Spanish-style dialog inside a paragraph's text
     *
     * @param string $text
     * @return string
     */
    protected function replaceSpanishStyleDialog($text)
    {
        // replace "space and dash or em-dash character followed by something" by
        //         "space and em-dash followed by something"
        $regExp = '/';
        $regExp .= ' -(?<char>[^ -])';
        $regExp .= '/U'; // Ungreedy

        $text = preg_replace_callback($regExp,
                function ($matches)
                {
                    return sprintf(' ' . self::EMDASH_HTMLENTITY . '%s', $matches['char']);
                }, $text);

        // replace "something followed by dash or emdash character" by
        //         "something followed by em-dash"
        $regExp = '/';
        $regExp .= '(?<char>[^ -])-(?<next>[\W])';
        $regExp .= '/U'; // Ungreedy

        $text = preg_replace_callback($regExp,
                function ($matches)
                {
                    return sprintf('%s' . self::EMDASH_HTMLENTITY . '%s', $matches['char'], $matches['next']);
                }, $text);

        return $text;
    }
}
