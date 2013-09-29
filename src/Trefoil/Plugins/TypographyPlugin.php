<?php
namespace Trefoil\Plugins;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Easybook\Events\EasybookEvents as Events;
use Easybook\Events\ParseEvent;

use Symfony\Component\DomCrawler\Crawler;

/**
 * This plugin replaces certain symbols with its typographic equivalents.
 * - Quotes ('".."'), backtick quotes ('``..'''), ellipsis('...')
 * - Dashes ('--')
 * - Angle quotes ('<<' and '>>')
 * - Checkboxes ('[ ]' and '[/]')
 * @see http://daringfireball.net/projects/smartypants/
 */
class TypographyPlugin extends BasePlugin implements EventSubscriberInterface
{
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
        $this->init($event);

        $content = $event->getOriginal();

        $content = $this->preProcessAngleQuotesForSmartypants($content);
        $content = $this->smartyPantsPreParse($content);

        $event->setOriginal($content);
    }

    public function onItemPostParse(ParseEvent $event)
    {
        $this->init($event);

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
        $options.= 'g'; // angle quotes (needs preparation)
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
}
