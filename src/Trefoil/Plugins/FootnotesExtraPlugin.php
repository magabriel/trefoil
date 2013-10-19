<?php
namespace Trefoil\Plugins;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Easybook\Events\BaseEvent;
use Easybook\Events\EasybookEvents;
use Easybook\Events\ParseEvent;
use Trefoil\Events\TrefoilEvents;
use Trefoil\Util\SimpleReport;

/**
 * This plugin processes footnotes.
 *
 */
class FootnotesExtraPlugin extends BasePlugin implements EventSubscriberInterface
{

    /**
     */
    protected $footnotes = array();

    /**
     * Cross-references of replaced terms for reporting
     * @var array
     */
    protected $xrefs = array();

    /**
     * Whether or not the glossary item has been generated
     * @var bool
     */
    protected $generated = false;

    /**
     * Whether a term has been replaced at least once into the current item
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
                EasybookEvents::POST_PARSE => array('onItemPostParse'),
                EasybookEvents::PRE_DECORATE => array('onItemPreDecorate', -500),
                EasybookEvents::POST_PUBLISH => 'onPostPublish');
    }

    public function onItemPostParse(ParseEvent $event)
    {
        $this->init($event);

        $this->processItem();

        // reload changed item
        $event->setItem($this->item);
    }

    public function onItemPreDecorate(BaseEvent $event)
    {
        $this->init($event);

        // ensure all the generated internal links have the right format
        $this->fixInternalLinks();

        $event->setItem($this->item);
    }

    public function onPostPublish(BaseEvent $event)
    {
        $this->init($event);

        // create the processing report
        $this->createReport();
    }

    /* ********************************************************************************
     * Implementation
     * ********************************************************************************
     */

    /**
     * Performs either one of two processes:
     * <li>For a content item to be processed, extract generated footnotes.
     * <li>For 'footnotes' item, generate the footnotes content.
     */
    protected function processItem()
    {
        if ('footnotes' == $this->item['config']['element']) {
            $this->generateFootnotes();
        } else {
            $this->extractFootnotes();
            $this->renumberReferences();
        }
    }

    protected function extractFootnotes()
    {
        $content = $this->item['content'];

        $regExp = '/';
        $regExp.= '<div class="footnotes">.*<ol>(?<fns>.*)<\/ol>.*<\/div>';
        $regExp.= '/Ums'; // Ungreedy, multiline, dotall

        $content = preg_replace_callback(
                $regExp,
                function ($matches)
                {
                    $regExp2 = '/';
                    $regExp2.= '<li.*id="(?<id>.*)">.*';
                    $regExp2.= '<p>(?<text>.*)&#160;<a href="#(?<backref>.*)"';
                    $regExp2.= '/Ums'; // Ungreedy, multiline, dotall

                    preg_match_all($regExp2, $matches[0], $matches2, PREG_SET_ORDER);

                    foreach ($matches2 as $match2) {
                        $this->footnotes[$match2['id']] = array(
                                'id' => $match2['id'],
                                'text' => $match2['text'],
                                'backref' => $match2['backref'],
                                'new_number' => count($this->footnotes) + 1
                                );

                        // register the backref link (because we know if is in the current item)
                        $this->saveInternalLinkTarget($match2['backref']);
                    }

                    return '';
                },
                $content);

        $this->item['content'] = $content;
    }

    protected function renumberReferences()
    {
        $content = $this->item['content'];

        $regExp = '/';
        $regExp.= '<a(?<prev>.*)href="#(?<href>fn:.*)"(?<post>.*)>(?<number>.*)<\/a>';
        $regExp.= '/Ums'; // Ungreedy, multiline, dotall

        $content = preg_replace_callback(
                $regExp,
                function ($matches)
                {
                    $newNumber = $this->footnotes[$matches['href']]['new_number'];

                    $html = sprintf('<a%shref="#%s"%s>%s</a>',
                            $matches['prev'],
                            $matches['href'],
                            $matches['post'],
                            $newNumber
                            );
                    return $html;
                },
                $content);

        $this->item['content'] = $content;
    }

    protected function generateFootnotes()
    {
        $content = $this->item['content'];

        $variables = array(
                'footnotes' => $this->footnotes
        );

        $rendered = $this->app->get('twig')->render('footnotes-items.twig', $variables);

        // register all anchor links
        foreach ($this->footnotes as $footnote) {
            $this->saveInternalLinkTarget($footnote['id']);
        }

        // concat rendered string to content instead of replacing it to preserve user content
        $content .= $rendered;

        $this->generated = true;

        $this->item['content'] = $content;
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
            $oldRef = split(':', $footnote['id'])[1];
            $newRef = $footnote['new_number'];
            $text = substr($footnote['text'], 0, 90);
            $text.= ($text == $footnote['text'] ? '' : '...');
            $report->addLine(array($oldRef, $newRef, $text));
        }

        if (!$this->generated) {
            $this->output
            ->write(
                    " <error>No footnotes element has been generated, check for missing 'footnotes' contents element.</error>\n");
        }

        $outputDir = $this->app['publishing.dir.output'];
        $reportFile = $outputDir . '/report-FootnotesExtraPlugin.txt';

        file_put_contents($reportFile, $report->getText());
    }
}
