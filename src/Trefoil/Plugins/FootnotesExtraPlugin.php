<?php
namespace Trefoil\Plugins;

use Easybook\Events\BaseEvent;
use Easybook\Events\EasybookEvents;
use Easybook\Events\ParseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Trefoil\Util\SimpleReport;

/**
 * This plugin processes footnotes.
 *
 * It first collects all footnotes markup generated by the Markdown parser
 * (at the end of each book element) and then it generates a consolidated
 * footnotes list element with all the book's footnotes.
 *
 * It also replaces invalid character ':' in footnotes ids generated by the
 * Markdown parser (so epubcheck will not complain).
 *
 */
class FootnotesExtraPlugin extends BasePlugin implements EventSubscriberInterface
{

    /**
     * The footnotes
     *
     * @var array
     */
    protected $footnotes = array();

    /**
     * Cross-references of replaced terms for reporting
     *
     * @var array
     */
    protected $xrefs = array();

    /**
     * Whether or not the glossary item has been generated
     *
     * @var bool
     */
    protected $generated = false;

    /**
     * Whether a term has been replaced at least once into the current item
     *
     * @var bool
     */
    protected $termReplaced;

    /* ********************************************************************************
     * Event handlers
     * ********************************************************************************
     */
    static public function getSubscribedEvents()
    {
        return array(
            EasybookEvents::PRE_PARSE    => array('onItemPreParse', +100),
            EasybookEvents::POST_PARSE   => array('onItemPostParse'),
            EasybookEvents::POST_PUBLISH => 'onPostPublish');
    }

    public function onItemPostParse(ParseEvent $event)
    {
        $this->init($event);

        if (!in_array($this->format, array('Epub', 'Mobi', 'Html'))) {
            // not for this format
            return;
        }

        $this->processItem();

        // reload changed item
        $event->setItem($this->item);
    }

    public function onItemPreParse(ParseEvent $event)
    {
        $this->init($event);

        if (!in_array($this->format, array('Epub', 'Mobi', 'Html'))) {
            // not for this format
            return;
        }

        if ('footnotes' == $this->item['config']['element']) {
            $this->saveFootnotes();
        }
    }

    public function onPostPublish(BaseEvent $event)
    {
        $this->init($event);

        if (!in_array($this->format, array('Epub', 'Mobi', 'Html'))) {
            // not for this format
            return;
        }

        // create the processing report
        $this->createReport();
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
        if ('footnotes' != $this->item['config']['element']) {
            $this->extractFootnotes();
            $this->renumberReferences();
        }
    }

    protected function extractFootnotes()
    {
        $content = $this->item['content'];

        $regExp = '/';
        $regExp .= '<div class="footnotes">.*<ol>(?<fns>.*)<\/ol>.*<\/div>';
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $me = $this;
        $content = preg_replace_callback(
            $regExp,
            function ($matches) use ($me) {
                $regExp2 = '/';
                $regExp2 .= '<li.*id="(?<id>.*)">.*';
                $regExp2 .= '<p>(?<text>.*)&#160;<a href="#(?<backref>.*)"';
                $regExp2 .= '/Ums'; // Ungreedy, multiline, dotall

                preg_match_all($regExp2, $matches[0], $matches2, PREG_SET_ORDER);

                foreach ($matches2 as $match2) {
                    $me->footnotes[$match2['id']] = array(
                        'id'         => str_replace(':', '-', $match2['id']),
                        'text'       => $match2['text'],
                        'backref'    => str_replace(':', '-', $match2['backref']),
                        'new_number' => count($me->footnotes) + 1
                    );
                }

                return '';
            },
            $content
        );

        $this->item['content'] = $content;
    }

    protected function renumberReferences()
    {
        $content = $this->item['content'];

        $regExp = '/';
        $regExp .= '<sup id="(?<supid>fnref:.*)">';
        $regExp .= '<a(?<prev>.*)href="#(?<href>fn:.*)"(?<post>.*)>(?<number>.*)<\/a>';
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $me = $this;
        $content = preg_replace_callback(
            $regExp,
            function ($matches) use ($me) {
                $newNumber = $me->footnotes[$matches['href']]['new_number'];

                $html = sprintf(
                    '<sup id="%s"><a%shref="#%s"%s>%s</a>',
                    str_replace(':', '-', $matches['supid']),
                    $matches['prev'],
                    str_replace(':', '-', $matches['href']),
                    $matches['post'],
                    $newNumber
                );

                return $html;
            },
            $content
        );

        $this->item['content'] = $content;
    }

    protected function saveFootnotes()
    {
        $this->app['publishing.footnotes.items'] = $this->footnotes;

        $this->generated = true;
    }

    /**
     * Writes the report with the summary of processing done.
     */
    protected function createReport()
    {
        $report = new SimpleReport();
        $report->setTitle('FootnotesExtraPlugin');

        $report->setHeaders(array('Old reference', 'New reference', 'Text (fragment)'));

        $report->setColumnsWidth(array(13, 13, 93));
        $report->setColumnsAlignment(array('center', 'center', 'left'));

        foreach ($this->footnotes as $footnote) {
            $parts = explode('-', $footnote['id']);
            $oldRef = $parts[1];
            $newRef = $footnote['new_number'];
            $text = substr($footnote['text'], 0, 90);
            $text .= ($text == $footnote['text'] ? '' : '...');
            $report->addLine(array($oldRef, $newRef, $text));
        }

        if (!$this->generated) {
            $this->writeLn(
                 "No footnotes element has been generated, check for missing 'footnotes' contents element.",
                 'error'
            );
        }

        $outputDir = $this->app['publishing.dir.output'];
        $reportFile = $outputDir . '/report-FootnotesExtraPlugin.txt';

        file_put_contents($reportFile, $report->getText());
    }
}
