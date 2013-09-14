<?php
namespace Trefoil\Plugins;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Easybook\Events\EasybookEvents as Events;
use Easybook\Events\ParseEvent;

use Symfony\Component\DomCrawler\Crawler;

/**
 * This plugin replaces certain symbols with its typographic equivalents the book.
 *
 */
class TypographyPlugin implements EventSubscriberInterface
{
    protected $app;
    protected $item;
    protected $links = array();

    public static function getSubscribedEvents()
    {
        return array(
                Events::PRE_PARSE => 'onItemPreParse',
                Events::POST_PARSE => 'onItemPostParse'
        );
    }

    public function onItemPreParse(ParseEvent $event)
    {
        $this->app = $event->app;
        $this->item = $event->getItem();

        $content = $event->getOriginal();

        $content = $this->preProcessAngleQuotesForSmartypants($content);

        $content = $this->replaceSymbolsPreParse($content);

        $content = $this->smartyPantsPreParse($content);

        $event->setOriginal($content);
    }

    public function onItemPostParse(ParseEvent $event)
    {
        $this->app = $event->app;
        $this->item = $event->getItem();

        $content = $this->item['content'];

        $content = $this->smartyPantsPostParse($content);

        $content = $this->replaceSymbolsPostParse($content);

        $this->item['content'] = $content;

        $event->setItem($this->item);
    }

    protected function smartyPantsPreParse($content)
    {
        $options = '';

        // normal SmartyPants processing
        // NOTE: dashes cannot be replaced on PreParse to avoid interfering with MarkDown
        $options.= 'qbe'; // quotes, backticks and ellipses

        // refinements for SmartyPants Typographer
        $options.= 'g'; // angle quotes
        $options.= 'f-'; // do not add space inside angle quotes

        // and go!!
        $content = \SmartyPants($content, $options);

        return $content;
    }

    protected function smartyPantsPostParse($content)
    {
        $options = '';

        // normal SmartyPants processing
        // NOTE: dashes cannot be replaced on PreParse to avoid interfering with MarkDown
        $options.= 'd'; // dashes

        // and go!!
        $content = \SmartyPants($content, $options);

        return $content;
    }

    /**
     * Prepare angle quotes '<<' and '>>' to be processed by SmartyPants
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

    /*
    protected function replaceEllipsis($content)
    {
        $regExp = '/';
        $regExp .= '(?<prev>[^\.])'; // not a dot
        $regExp .= '(?<sign>\.\.\.)'; // 3 dots
        $regExp .= '(?<next>[^\.])'; // not a dot
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $me = $this;
        $content = preg_replace_callback($regExp,
                                         function ($matches) use ($me)
                                         {
                                         // PRUEBAS
                                         //print_r($matches);
                                         return $matches['prev']."&hellip;".$matches['next'];
                                         }, $content);

        return $content;
    }
    */

    /*
    protected function replaceQuotes($content)
    {
        $regExp = '/';

        $regExp .= '(?<prev>';
        $regExp .= '[^=]'; // Not preceded by equal (to avoid picking up html attributes)
        $regExp .= ')';

        $regExp .= '(?<open>';
        $regExp .= '"'; // Opening quote
        $regExp .= ')';

        $regExp .= '(?<stuff>';
        $regExp .= '[^>]'; // Not followed by > (to avoid picking up an ending html attribute)
        $regExp .= '[^="]*'; // In-between
        $regExp .= '[^="]'; // Not ended by equal or quote (to avoid picking up html attributes)
        $regExp .= ')';

        $regExp .= '(?<close>';
        $regExp .= '"'; // Closing quote
        $regExp .= ')';

        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $me = $this;
        $content = preg_replace_callback($regExp,
                                         function ($matches) use ($me)
                                         {
                                         // PRUEBAS
                                         //print_r($matches);
                                         return $matches['prev'] . "&laquo;" . $matches['stuff']
                                         . "&raquo;";
                                         }, $content);

        return $content;
    }
    */

    /*
    protected function replaceDashes($content)
    {
        $regExp = '/';
        $regExp .= '(?<sign>';
        $regExp .= '--'; // 2 dashes
        $regExp .= ')';
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $me = $this;
        $content = preg_replace_callback($regExp,
                function ($matches) use ($me)
                {
                    // PRUEBAS
                    //print_r($matches);
                    return "&mdash;";
                }, $content);

        return $content;
    }
    */

    protected function replaceSymbolsPreParse($content)
    {
        return $content;
        /*
        $regExp = '/';
        $regExp .= '(?<sign>';
        $regExp .= ' --->'; //
        $regExp .= ')';
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $me = $this;
        $content = preg_replace_callback($regExp,
                function ($matches) use ($me)
                {
                    return "&rarr;"; // ballot sign (box with checkmark)
                }, $content);

        return $content;
        */
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
                    return "&#9744;"; // ballot sign (box with checkmark)
                }, $content);

        return $content;
    }
}
