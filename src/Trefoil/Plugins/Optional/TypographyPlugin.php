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

use Easybook\Events\EasybookEvents;
use Easybook\Events\ParseEvent;
use Michelf\SmartyPantsTypographer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Trefoil\Plugins\BasePlugin;

/**
 * This plugin replaces certain symbols with its typographic equivalents.
 * - Quotes ('".."'), backtick quotes ('``..'''), ellipsis('...')
 * - Dashes ('--')
 * - Angle quotes ('<<' and '>>')
 * - Checkboxes ('[ ]' and '[/]')
 * Options are specified on an per-edition basis:
 *     editions:
 *         <edition-name>
 *             plugins:
 *                 ...
 *                 options:
 *                     Typography:
 *                         checkboxes: true
 *                         fix_spanish_style_dialog: false
 * - fix_spanish_style_dialog: Convert starting '-' (dash) in paragraphs to em-dash.
 *
 * @see http://daringfireball.net/projects/smartypants/
 */
class TypographyPlugin extends BasePlugin implements EventSubscriberInterface
{
    public const BALLOT_BOX_HTMLENTITY = '&#9744;';
    public const BALLOT_BOX_CHECKED_HTMLENTITY = '&#9745;';
    public const EMDASH_UNICODE = '\x{2014}';
    public const EMDASH_HTMLENTITY = '&#8212;';

    protected $links = [];

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EasybookEvents::PRE_PARSE  => 'onItemPreParse',
            EasybookEvents::POST_PARSE => 'onItemPostParse',
        ];
    }

    /**
     * @param ParseEvent $event
     */
    public function onItemPreParse(ParseEvent $event)
    {
        $this->init($event);

        $content = $event->getItemProperty('original');

        $content = $this->preProcessAngleQuotesForSmartypants($content);

        $event->setItemProperty('original', $content);
    }

    /**
     * @param ParseEvent $event
     */
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

    /**
     * @param $content
     * @return mixed
     */
    protected function smartyPantsPostParse($content)
    {
        $options = '';

        // normal SmartyPants processing
        $options .= 'qbed'; // quotes, backticks ellipses and dashes

        // refinements for SmartyPants Typographer
        $options .= 'g'; // angle quotes (needs preparation)
        $options .= 'f-'; // do not add space inside angle quotes

        // and go!!
        $content = SmartyPantsTypographer::defaultTransform($content, $options);

        return $content;
    }

    /**
     * Prepare angle quotes '<<' and '>>' to be processed by SmartyPants:
     * ensure there is a space before '>>' and  after '<<'
     *
     * @param string $content
     * @return string
     */
    protected function preProcessAngleQuotesForSmartypants($content): string
    {
        // opening <<
        $regExp = '/';
        $regExp .= '(?<prev>[^\<])'; // not a <
        $regExp .= '(?<sign>\<\<)'; // 2 <
        $regExp .= '(?<next>[^\< \/])'; // not a < or space or / (to avoid intereference with TabularListsPlugin)
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $content = preg_replace_callback(
            $regExp,
            function ($matches) {
                // put a space after the <<
                return $matches['prev'].'<< '.$matches['next'];
            },
            $content);

        // closing >>
        $regExp = '/';
        $regExp .= '(?<prev>[^\> ])'; // not a > or space
        $regExp .= '(?<sign>\>\>)'; // 2 >
        $regExp .= '(?<next>[^\>])'; // not a >
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $content = preg_replace_callback(
            $regExp,
            function ($matches) {
                // put a space before the <<
                return $matches['prev'].' >>'.$matches['next'];
            },
            $content);

        return $content;
    }

    /**
     * @param $content
     * @return string|string[]|null
     */
    protected function replaceSymbolsPostParse($content)
    {
        if ($this->getEditionOption('plugins.options.Typography.checkboxes', true)) {
            $content = $this->replaceCheckboxes($content);
        }

        return $content;
    }

    /**
     * @param $content
     * @return string|string[]|null
     */
    protected function replaceCheckboxes($content)
    {
        $regExp = '/';
        $regExp .= '(?<sign>';
        $regExp .= '\[ \]'; // open bracket + space + close bracket
        $regExp .= ')';
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $content = preg_replace_callback(
            $regExp,
            function () {
                return $this::BALLOT_BOX_HTMLENTITY; // ballot box (box without checkmark)
            },
            $content);

        $regExp = '/';
        $regExp .= '(?<sign>';
        $regExp .= '\[\/\]'; // open bracket + slash + close bracket
        $regExp .= ')';
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $content = preg_replace_callback(
            $regExp,
            function () {
                return $this::BALLOT_BOX_CHECKED_HTMLENTITY; // ballot box with checkmark
            },
            $content);

        return $content;
    }

    /**
     * Fix Spanish-style dialog for content
     *
     * @param string $content
     * @return string
     */
    protected function fixSpanishStyleDialog($content): string
    {
        // process all paragraphs begining with a dash or em-dash
        $regExp = '/';
        $regExp .= '<p>[-'.self::EMDASH_UNICODE.'](?<text>[^ ].*)<\/p>';
        $regExp .= '/Umsu'; // Ungreedy, multiline, dotall, unicode <= PLEASE NOTE UNICODE FLAG

        $content = preg_replace_callback(
            $regExp,
            function ($matches) {
                // replace the dialog inside the paragraph
                $text = $this->internalReplaceSpanishStyleDialog($matches['text']);

                // return the paragraph replacing the starting dash by an em-dash
                return sprintf('<p>'.$this::EMDASH_HTMLENTITY.'%s</p>', $text);
            },
            $content);

        return $content;
    }

    /**
     * Replace Spanish-style dialog inside a paragraph's text
     *
     * @param string $text
     * @return string
     */
    protected function internalReplaceSpanishStyleDialog($text): string
    {
        // replace "space and dash or em-dash character followed by something" by
        //         "space and em-dash followed by something"
        $regExp = '/';
        $regExp .= ' -(?<char>[^ -])';
        $regExp .= '/U'; // Ungreedy

        $text = preg_replace_callback(
            $regExp,
            function ($matches) {
                return sprintf(' '.$this::EMDASH_HTMLENTITY.'%s', $matches['char']);
            },
            $text);

        // replace "something followed by dash or emdash character" by
        //         "something followed by em-dash"
        $regExp = '/';
        $regExp .= '(?<char>[^ -])-(?<next>[\W])';
        $regExp .= '/U'; // Ungreedy

        $text = preg_replace_callback(
            $regExp,
            function ($matches) {
                return sprintf('%s'.$this::EMDASH_HTMLENTITY.'%s', $matches['char'], $matches['next']);
            },
            $text);

        return $text;
    }
}
