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
use Trefoil\Helpers\Glossary;
use Trefoil\Helpers\GlossaryItem;
use Trefoil\Helpers\GlossaryLoader;
use Trefoil\Helpers\GlossaryReplacer;
use Trefoil\Helpers\TextPreserver;
use Trefoil\Plugins\BasePlugin;
use Trefoil\Util\SimpleReport;

/**
 * This plugin takes care of the automatic interactive glossary feature.
 * Configuration:
 * - Configuration:
 *     editions:
 *         <edition-name>
 *             plugins:
 *                 ...
 *                 options:
 *                     AutoGlossary:
 *                         pagebreaks: true   # use pagebreaks between defined terms
 * - Global glossary (per book):
 *     <book_dir>/
 *         Contents/
 *             auto-glossary.yml
 * - Per book item glossary:
 *     <book_dir>/
 *         Contents/
 *             <item_name>-auto-glossary.yml
 *
 * @see GlossaryLoader for format details
 * @see GlossaryReplacer for contents detail
 */
class AutoGlossaryPlugin extends BasePlugin implements EventSubscriberInterface
{

    /**
     * The book-wide glossary
     *
     * @var Glossary
     */
    protected $bookGlossary;

    /**
     * The item-wide glossary
     *
     * @var Glossary
     */
    protected $itemGlossary;

    /**
     * The glossary to apply for the current item (book + item)
     *
     * @var Glossary
     */
    protected $glossary;

    /**
     * The processed glossary items
     *
     * @var Glossary
     */
    protected $processedGlossary;

    /**
     * Options that govern the glossary processing
     *
     * @var array
     */
    protected $glossaryOptions = [];

    /**
     * Terms already defined, to detect duplicated definitions in different files
     *
     * @var array
     */
    protected $alreadyDefinedTerms = [];

    /**
     * Cross-references of replaced terms for reporting
     *
     * @var array
     */
    protected $xrefs = [];

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
    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EasybookEvents::PRE_PARSE    => ['onItemPreParse', +100],
            EasybookEvents::POST_PARSE   => ['onItemPostParse', -1110], // after EbookQuizPlugin to avoid interferences
            EasybookEvents::POST_PUBLISH => 'onPostPublish',
        ];
    }

    /**
     * @param ParseEvent $event
     */
    public function onItemPostParse(ParseEvent $event)
    {
        $this->init($event);

        // get all the book-wide glossary definitions and options
        if (!$this->bookGlossary) {
            $this->loadBookGlossary();
        }

        // get all the item-wide glossary definitions
        $this->loadItemGlossary();

        // process this item replacing terms into
        $this->processItem();

        // reload changed item
        $event->setItem($this->item);
    }

    /**
     * @param ParseEvent $event
     */
    public function onItemPreParse(ParseEvent $event)
    {
        $this->init($event);

        if ('auto-glossary' === $this->item['config']['element']) {
            $this->saveAutoGlossary();
        }

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
     * Load the book-wide glossary and options.
     */
    protected function loadBookGlossary()
    {
        // initializations
        $this->glossary = new Glossary();
        $this->processedGlossary = new Glossary();

        // get all the book-wide glossary terms from file and create the definitions data structure
        $contentsDir = $this->app['publishing.dir.book'].'/Contents';
        $glossaryFile = $contentsDir.'/auto-glossary.yml';

        $loader = new GlossaryLoader($glossaryFile, $this->app['slugger']);
        $this->bookGlossary = $loader->load(true); // with options
        $this->glossaryOptions = $loader->getOptions();

        if (!$loader->isLoaded()) {
            $this->writeLn(
                sprintf(
                    "No book glossary definition file '%s' found in the book's \"Contents\" directory.",
                    basename($glossaryFile)),
                'warning');
        }
    }

    /**
     * Load the item-wide glossary and merge it with the book-wide glossary
     * to get the definitions to be applied to the item.
     */
    protected function loadItemGlossary()
    {
        if (!$this->item['config']['content']) {
            // no content, nothing to do
            return;
        }

        // read item glossary and merge it with book glossary
        $contentsDir = $this->app['publishing.dir.book'].'/Contents';

        $fileName = pathinfo($this->item['config']['content'], PATHINFO_FILENAME);
        $glossaryFile = $contentsDir.'/'.$fileName.'-auto-glossary.yml';

        $loader = new GlossaryLoader($glossaryFile, $this->app['slugger']);
        $this->itemGlossary = $loader->load();

        // start with a fresh copy of the book-wide definitions
        $this->glossary = clone $this->bookGlossary;

        // and add the item-wide definitions
        $this->glossary->merge($this->itemGlossary);
    }

    /**
     * For a content item to be processed for glossary terms, replace glossary terms into the text.
     */
    public function processItem()
    {
        // look type of processing
        if (in_array($this->item['config']['element'], $this->glossaryOptions['elements'], true)) {

            // replace each term with a link to its definition
            $this->replaceTerms();

            // append a copy of the processed definitions to the processed glossary
            // to avoid losing all xrefs and anchorlinks for this item
            $this->processedGlossary->merge(clone $this->glossary);
        }
    }

    /**
     * Replace all glossary terms into the current item.
     */
    protected function replaceTerms()
    {
        // instantiate the GlossaryReplacer object
        $replacer = new GlossaryReplacer(
            $this->glossary,
            new TextPreserver(),
            $this->item['content'],
            $this->item['config']['content'],
            $this->glossaryOptions,
            $this->app['twig']);

        // do the replacements (also modifies the glossary object)
        $this->item['content'] = $replacer->replace();
    }

    /**
     * Save the auto glossary definitions to be generated on item rendering
     */
    protected function saveAutoGlossary()
    {
        $this->app['publishing.glossary.definitions'] = $this->processedGlossary;
        $this->app['publishing.glossary.pagebreaks'] = $this->getEditionOption(
            'plugins.options.AutoGlossary.pagebreaks',
            true);

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
                "No glossary has been generated, check for missing 'auto-glosssary' contents element.",
                'error');
        }

        $outputDir = $this->app['publishing.dir.output'];
        $reportFile = $outputDir.'/report-AutoGlossaryPlugin.txt';

        file_put_contents($reportFile, $report);
    }

    /**
     * @return string
     */
    protected function getUsedTermsReport(): string
    {
        $report = new SimpleReport();
        $report->setTitle('AutoGlossaryPlugin');
        $report->setSubtitle('Used terms');

        $report->addIntroLine('Coverage: '.$this->glossaryOptions['coverage']);
        $report->addIntroLine('Elements: '.'"'.implode('", "', $this->glossaryOptions['elements']).'"');

        $report->setHeaders(['Term', 'Variant', 'Item', 'Count', 'Source']);

        $report->setColumnsWidth([30, 30, 30, 5, 30]);
        $report->setColumnsAlignment(['', '', '', 'right', '']);

        foreach ($this->processedGlossary as $processedItem) {
            /* @var GlossaryItem $processedItem */
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
        $report->setTitle('AutoGlossaryPlugin');
        $report->setSubtitle('Not used terms');

        $report->setHeaders(['Term', 'Source']);

        $report->setColumnsWidth([30, 30]);

        foreach ($this->processedGlossary as $item) {
            /* @var GlossaryItem $item */
            if (!count($item->getXref())) {
                $report->addLine([$item->getTerm(), $item->getSource()]);
            }
        }

        return $report->getText();
    }
}
