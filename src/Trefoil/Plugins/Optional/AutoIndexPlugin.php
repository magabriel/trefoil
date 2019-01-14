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
use Trefoil\Events\TrefoilEvents;
use Trefoil\Helpers\Index;
use Trefoil\Helpers\IndexItem;
use Trefoil\Helpers\IndexLoader;
use Trefoil\Helpers\IndexReplacer;
use Trefoil\Helpers\TextPreserver;
use Trefoil\Plugins\BasePlugin;
use Trefoil\Util\SimpleReport;

/**
 * This plugin creates an automatic index from a list of terms.
 * Configuration:
 * - Configuration: Not used.
 * - Index definition:
 *     <book_dir>/
 *         Contents/
 *             auto-index.yml
 *
 * @see IndexLoader for format details.
 * @see IndexReplacer for contents details.
 */
class AutoIndexPlugin extends BasePlugin implements EventSubscriberInterface
{

    /**
     * The index to apply for the current item
     *
     * @var Index
     */
    protected $index;

    /**
     * The processed index items
     *
     * @var Index
     */
    protected $processedIndex;

    /**
     * Options that govern the index processing
     *
     * @var array
     */
    protected $indexOptions = [];

    /**
     * Cross-references of replaced terms for reporting
     *
     * @var array
     */
    protected $xrefs = [];

    /**
     * Whether or not the index item has been generated
     *
     * @var bool
     */
    protected $generated = false;

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
            TrefoilEvents::PRE_PUBLISH_AND_READY => 'onPrePublishAndReady',
            EasybookEvents::PRE_PARSE            => ['onItemPreParse', +100],
            EasybookEvents::POST_PARSE           => ['onItemPostParse', -1110],
            // after EbookQuizPlugin to avoid interferences
            EasybookEvents::POST_PUBLISH         => 'onPostPublish',
        ];
    }

    /**
     * @param BaseEvent $event
     */
    public function onPrePublishAndReady(BaseEvent $event)
    {
        $this->init($event);

        $this->loadIndexTerms();
    }

    /**
     * @param ParseEvent $event
     */
    public function onItemPreParse(ParseEvent $event)
    {
        $this->init($event);

        if ($this->item['config']['element'] === 'auto-index') {
            $this->saveAutoindex();
        }
    }

    /**
     * @param ParseEvent $event
     */
    public function onItemPostParse(ParseEvent $event)
    {
        $this->init($event);

        // process this item replacing terms into
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

        // create the processing report
        $this->createReport();
    }

    /* ********************************************************************************
     * Implementation
     * ********************************************************************************
     */

    /**
     * Load the book-wide index and options.
     */
    protected function loadIndexTerms()
    {
        // initializations
        $this->index = new Index();
        $this->processedIndex = new Index();

        // get all the index terms from file and create the definitions data structure
        $contentsDir = $this->app['publishing.dir.book'].'/Contents';
        $indexFile = $contentsDir.'/auto-index.yml';

        $loader = new IndexLoader($indexFile, $this->app['slugger']);
        $this->index = $loader->load();
        $this->indexOptions = $loader->getOptions();

        if (!$loader->isLoaded()) {
            $this->writeLn(
                sprintf(
                    "No book index definition file '%s' found in the book's \"Contents\" directory.",
                    basename($indexFile)),
                'warning');
        }
    }

    /**
     * For a content item to be processed for index terms, replace glossary terms into the text.
     */
    public function processItem()
    {
        // look type of processing
        if (in_array($this->item['config']['element'], $this->indexOptions['elements'], true)) {

            // replace each term with an anchor from the index entry
            $this->replaceTerms();

            // append a copy of the processed definitions to the processed index
            // to avoid losing all xrefs and anchorlinks for this item
            $this->processedIndex->merge(clone $this->index);
        }
    }

    /**
     * Replace all item terms into the current item.
     */
    protected function replaceTerms()
    {
        $replacer = new IndexReplacer(
            $this->index,
            new TextPreserver(),
            $this->item['content'],
            $this->item['config']['content'],
            $this->app['twig'],
            $this->app['slugger']);

        // do the replacements
        $this->item['content'] = $replacer->replace();
    }

    /**
     * Save the auto index definitions to be generated on item rendering
     */
    protected function saveAutoindex()
    {
        $this->app['publishing.index.definitions'] = $this->processedIndex;
        $this->generated = true;
    }

    /**
     * Writes the report with the summary of processing done.
     */
    protected function createReport()
    {
        $report = '';
        $report .= $this->getUsedTermsReport();
        $report .= "\n\n";
        $report .= $this->getNotUsedTermsReport();

        if (!$this->generated) {
            $this->writeLn(
                "No index has been generated, check for missing 'auto-index' contents element.",
                'error');
        }

        $outputDir = $this->app['publishing.dir.output'];
        $reportFile = $outputDir.'/report-AutoIndexPlugin.txt';

        file_put_contents($reportFile, $report);
    }

    /**
     * @return string
     */
    protected function getUsedTermsReport(): string
    {
        $report = new SimpleReport();
        $report->setTitle('AutoIndexPlugin');
        $report->setSubtitle('Used terms');

        $report->addIntroLine('Elements: '.'"'.implode('", "', $this->indexOptions['elements']).'"');

        $report->setHeaders(['Term', 'Variant', 'Item', 'Count', 'Source']);

        $report->setColumnsWidth([30, 30, 30, 5, 30]);
        $report->setColumnsAlignment(['', '', '', 'right', '']);

        foreach ($this->processedIndex as $processedItem) {
            /* @var indexItem $processedItem */
            $auxTerm = $processedItem->getTerm();
            foreach ($processedItem->getXref() as $variant => $items) {
                $auxVariant = $variant;
                /** @var array $items */
                foreach ($items as $item => $count) {
                    $report->addLine([$auxTerm, $auxVariant, $item, $count, $processedItem->getSource()]);
                    $auxTerm = '';
                    $auxVariant = '';
                }
            }
        }

        return $report->getText();
    }

    /**
     * @return string
     */
    protected function getNotUsedTermsReport(): string
    {
        $report = new SimpleReport();
        $report->setTitle('AutoindexPlugin');
        $report->setSubtitle('Not used terms');

        $report->setHeaders(['Term', 'Source']);

        $report->setColumnsWidth([30, 30]);

        foreach ($this->processedIndex as $item) {
            /* @var indexItem $item */
            if (!count($item->getXref())) {
                $report->addLine([$item->getTerm(), $item->getSource()]);
            }
        }

        return $report->getText();
    }
}
