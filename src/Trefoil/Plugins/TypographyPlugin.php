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
 *                         fix_spanish_style_dialog: false
 *
 * - fix_spanish_style_dialog: Convert starting '-' (dash) in paragraphs to em-dash.
 *
 * @see http://daringfireball.net/projects/smartypants/
 */
class TypographyPlugin extends BasePlugin implements EventSubscriberInterface
{
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
        $regExp = '/';
        $regExp .= '(?<sign>';
        $regExp .= '\[ \]'; // open bracket + space + close bracket
        $regExp .= ')';
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $me = $this;
        $content = preg_replace_callback($regExp,
                function ($matches) use ($me)
                {
                    return "&#9744;"; // ballot box (box without checkmark)
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
                    return "&#9745;"; // ballot box with checkmark
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
        // process all paragraphs begining with a dash
        $regExp = '/';
        $regExp .= '<p>-(?<text>[^ ].*)<\/p>';
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $content = preg_replace_callback($regExp,
                function ($matches)
                {
                    // replace the dialog inside the paragraph
                    $text = $this->replaceSpanishStyleDialog($matches['text']);

                    // return the paragraph replacing the starting dash by an em-dash
                    return sprintf('<p>&#8212;%s</p>', $text);
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
        // replace "space and dash followed by something" by
        //         "space and em-dash followed by something"
        $regExp = '/';
        $regExp .= ' -(?<char>[^ -])';
        $regExp .= '/U'; // Ungreedy

        $text = preg_replace_callback($regExp,
                function ($matches)
                {
                    return sprintf(' &#8212;%s', $matches['char']);
                }, $text);

        // replace "something followed by dash" by
        //         "something followed by em-dash"
        $regExp = '/';
        $regExp .= '(?<char>[^ -])-(?<next>[\W])';
        $regExp .= '/U'; // Ungreedy

        $text = preg_replace_callback($regExp,
                function ($matches)
                {
                    return sprintf('%s&#8212;%s', $matches['char'], $matches['next']);
                }, $text);

        return $text;
    }
}
