<?php
namespace Trefoil\Plugins;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Easybook\Events\BaseEvent;
use Easybook\Events\EasybookEvents;
use Easybook\Events\ParseEvent;
use Trefoil\Events\TrefoilEvents;
use Trefoil\Helpers\Glossary;
use Trefoil\Helpers\GlossaryItem;
use Trefoil\Helpers\GlossaryReplacer;
use Trefoil\Helpers\GlossaryLoader;
use Trefoil\Util\SimpleReport;

/**
 * This plugin takes care of the automatic interactive glossary feature.
 *
 * Configuration:
 *
 * - Global (per book):
 *
 *     <book_dir>/
 *         Contents/
 *             auto-glossary.yml
 *
 * - Per book item:
 *
 *     <book_dir>/
 *         Contents/
 *             <item_name>-auto-glossary.yml
 *
 * @see GlossaryLoader for format details
 * @see GlossaryReplacer for contents detail
 *
 */
class AutoGlossaryPlugin extends BasePlugin implements EventSubscriberInterface
{

    /**
     * The book-wide glossary
     * @var Glossary
     */
    protected $bookGlossary;

    /**
     * The item-wide glossary
     * @var Glossary
     */
    protected $itemGlossary;

    /**
     * The glossary to apply for the current item (book + item)
     * @var Glossary
     */
    protected $glossary;

    /**
     * The processed glossary items
     * @var Glossary
     */
    protected $processedGlossary;

    /**
     * Options that govern the glossary processing
     * @var array
     */
    protected $glossaryOptions = array();

    /**
     * Terms already defined, to avoid defining them more than once if coverage mandates it
     * @var array
     */
    protected $alreadyDefinedTerms = array();

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
                TrefoilEvents::PRE_PUBLISH_AND_READY => 'onPrePublishAndReady',
                EasybookEvents::POST_PARSE => array('onItemPostParse', -100),
                EasybookEvents::PRE_DECORATE => array('onItemPreDecorate', -500),
                EasybookEvents::POST_PUBLISH => 'onPostPublish');
    }

    public function onPrePublishAndReady(BaseEvent $event)
    {
        $this->init($event);

        // get all the book-wide glossary definitions and options
        $this->loadBookGlossary();
    }

    public function onItemPostParse(ParseEvent $event)
    {
        $this->init($event);

        // get all the item-wide glossary definitions
        $this->loadItemGlossary();

        // process this item (either replacing terms into or generating the glossary)
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
     * Load the book-wide glossary and options.
     */
    protected function loadBookGlossary()
    {
        // initializations
        $this->glossary = new Glossary();
        $this->processedGlossary = new Glossary();

        // get all the book-wide glossary terms from file and create the definitions data structure
        $contentsDir = $this->app->get('publishing.dir.book') . '/Contents';
        $glossaryFile = $contentsDir . '/auto-glossary.yml';

        $loader = new GlossaryLoader($glossaryFile, $this->app->get('slugger'));
        $this->bookGlossary = $loader->load(true); // with options
        $this->glossaryOptions = $loader->getOptions();

        if (!$loader->isLoaded()) {
            $this->output->write(
                    sprintf(" <comment>No book glossary definition file '%s' found in '%s' directory.</comment>\n",
                            basename($glossaryFile), realpath($contentsDir)));
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
        $contentsDir =  $this->app->get('publishing.dir.book') . '/Contents';

        $fileName = pathinfo($this->item['config']['content'], PATHINFO_FILENAME);
        $glossaryFile = $contentsDir . '/' . $fileName . '-auto-glossary.yml';

        $loader = new GlossaryLoader($glossaryFile, $this->app->get('slugger'));
        $this->itemGlossary = $loader->load();

        // start with a fresh copy of the book-wide definitions
        $this->glossary = clone($this->bookGlossary);

        // and add the item-wide definitions
        $this->glossary->merge($this->itemGlossary);
    }

    /**
     * Performs either one of two processes:
     * <li>For a content item to be processed, replace glossary terms into the text.
     * <li>For 'auto-glossary' item, gernerate the glossary itself.
     */
    public function processItem()
    {
        // look type of processing
        if (in_array($this->item['config']['element'], $this->glossaryOptions['elements'])) {

            // replace each term with a link to its definition
            $this->replaceTerms();

            // append a copy of the processed definitions to the processed glossary
            // to avoid losing all xrefs and anchorlinks for this item
            $this->processedGlossary->merge(clone($this->glossary));

        } elseif ('auto-glossary' == $this->item['config']['element']) {

            // generate the book auto glossary
            $this->generateAutoGlossary();
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
                $this->item['content'],
                $this->item['config']['content'],
                $this->glossaryOptions);

        // do the replacements (also modifies the glossary object)
        $this->item['content'] = $replacer->replace();

        // register all anchor links for this item
        // the GlossaryReplacer has added all the new anchor links to the GlossaryItems
        foreach ($this->glossary as $term => $data /* @var $data GlossaryItem */ ) {
            foreach ($data->getAnchorLinks() as $anchorLink) {
                $this->saveInternalLinkTarget($anchorLink);
            }
        }
    }

    /**
     * Generate the auto glossary
     */
    protected function generateAutoGlossary()
    {
        $content = $this->item['content'];

        $variables = array(
                'definitions' => $this->processedGlossary,
                'item' => $this->item
                );

        $rendered = $this->app->get('twig')->render('auto-glossary-items.twig', $variables);

        // register all anchor links
        foreach ($this->processedGlossary as $term => $data) {
            foreach ($data->getAnchorLinks() as $index => $anchorLink) {
                $this->saveInternalLinkTarget('auto-glossary-' . $data->getSlug() . '-' . $index);
            }
        }

        $this->generated = true;

        // concat rendered string to content instead of replacing it to preserve user content
        $content .= $rendered;

        $this->item['content'] = $content;
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
            $this->output
            ->write(
                    " <error>No glossary has been generated, check for missing 'auto-glosssary' contents element.</error>\n");
        }

        $outputDir = $this->app['publishing.dir.output'];
        $reportFile = $outputDir . '/report-AutoGlossaryPlugin.txt';

        file_put_contents($reportFile, $report);
    }

    protected function getUsedTermsReport()
    {
        $report = new SimpleReport();
        $report->setTitle('AutoGlossaryPlugin');
        $report->setSubtitle('Used terms');

        $report->addIntroLine('Coverage: ' . $this->glossaryOptions['coverage']);
        $report->addIntroLine('Elements: ' . '"' . join('", "', $this->glossaryOptions['elements']) . '"');

        $report->setHeaders(array('Term', 'Variant', 'Item', 'Count', 'Source'));

        $report->setColumnsWidth(array(30, 30, 30, 5, 30));
        $report->setColumnsAlignment(array('', '', '', 'right', ''));

        $auxTerm = '';
        $auxVariant = '';
        foreach ($this->processedGlossary as $term => $data) {
            $auxTerm = $term;
            foreach ($data->getXref() as $variant => $items) {
                $auxVariant = $variant;
                foreach ($items as $item => $count) {
                    $report->addline(array($auxTerm, $auxVariant, $item, $count, $data->getSource()));
                    $auxTerm = '';
                    $auxVariant = '';
                }
            }
        }

        return $report->getText();
    }

    protected function getNotUsedTermsReport()
    {
        $report = new SimpleReport();
        $report->setTitle('AutoGlossaryPlugin');
        $report->setSubtitle('Not used terms');

        $report->setHeaders(array('Term', 'Source'));

        $report->setColumnsWidth(array(30, 30));

        foreach ($this->processedGlossary as $term => $data) {
            if (!count($data->getXref($term))) {
                $report->addLine(array($term, $data->getSource()));
            }
        }

        return $report->getText();
    }
}

