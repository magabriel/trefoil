<?php
namespace Trefoil\Plugins;

use Trefoil\Helpers\TextPreserver;

use Trefoil\Util\SimpleReport;

use Trefoil\Helpers\GlossaryLoader;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Easybook\Events\BaseEvent;
use Easybook\Util\Slugger;
use Easybook\Util\Toolkit;
use Easybook\Events\EasybookEvents as Events;
use Easybook\Events\ParseEvent;
use Trefoil\Events\TrefoilEvents;
use Trefoil\Helpers\Glossary;
use Trefoil\Helpers\GlossaryItem;

/**
 * This plugin takes care of the automatic interactive glossary feature.
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
     *
     * @var TextPreserver
     */
    protected $textPreserver;

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
                Events::POST_PARSE => array('onItemPostParse', -100),
                Events::PRE_DECORATE => array('onItemPreDecorate', -500),
                Events::POST_PUBLISH => 'onPostPublish');
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
     * <li>For a content item to be processed, replace glossary terms in the text.
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
     * Replace terms in item's content
     */
    protected function replaceTerms()
    {
        if (!$this->glossary->count()) {
            // no glossary terms
            return;
        }

        $content = $this->item['content'];

        // create the TextPeserver instance for this item processing
        $this->textPreserver = new TextPreserver();
        $this->textPreserver->setText($content);

        // save existing values of tags contents we don't want to get modified into
        $this->textPreserver->preserveHtmlTags(array('a'));

        // save existing values of attributes we don't want to get modified into
        $this->textPreserver->preserveHtmlTagAttributes(array('title', 'alt', 'src', 'href'));

        $content = $this->textPreserver->getText();

        // process each variant of each term
        foreach ($this->glossary as $term => $data) {

            $this->termReplaced = false;

            foreach ($data->getVariants() as $variant) {
                $contentMod = $this->replaceTermVariant($content, $data, $variant);

                if ($this->termReplaced) {
                    // at least a replacement ocurred
                    if ('item' == $this->glossaryOptions['coverage']) {
                        // already replaced once in this item, so ignore subsequent ocurrences and variants
                        $content = $contentMod;
                        break;
                    }

                }
                $content = $contentMod;
            }

            // register all anchor link for this item
            foreach ($data->getAnchorLinks() as $anchorLink) {
                $this->saveInternalLinkTarget($anchorLink);
            }
        }

        $this->textPreserver->setText($content);
        $content = $this->textPreserver->restore();

        $this->item['content'] = $content;
    }

    /**
     * Replace a term variant into the item text
     *
     * @param string $item The text to replace into
     * @param string $term The term to replace
     * @param string $variant Term variant to replace by a glossary link
     * @param string $slug The slug of the term
     * @param array $anchorLinks List of anchor links to be registered
     * @return string|mixed
     */
    protected function replaceTermVariant($item, $data, $variant)
    {
        // construct regexp to replace only into certain tags
        $tags = array('p', 'li', 'dd');

        $patterns = array();
        foreach ($tags as $tag) {
            $patterns[] = sprintf('/<(?<tag>%s)>(?<content>.*)<\/%s>/Ums', $tag, $tag);
        }

        // replace all occurrences of $variant into text $item with a glossary link
        $item = preg_replace_callback($patterns,
                function ($matches) use ($data, $variant, $item)
                {
                    // extract what to replace
                    $tag = $matches['tag'];
                    $tagContent = $matches['content'];

                    // do the replacement
                    $newContent = $this->replaceTermVariantIntoString($tagContent, $data, $variant);

                    // reconstruct the original tag with the modified text
                    return sprintf('<%s>%s</%s>', $tag, $newContent, $tag);
                }, $item);

        return $item;
    }

    /**
     * @param string $text The text to replace into
     * @param string $term The term to replace
     * @param string $variant Term variant to replace by a glossary link
     * @param string $slug The slug of the term
     * @param int $count Number of replacements already made into this item
     * @param array $anchorLinks List of anchor links to be registered
     */
    protected function replaceTermVariantIntoString($text, $data, $variant)
    {

        // construct the regexp to replace inside the tag content
        $regExp = '/';
        $regExp .= '(^|\W)'; // $1 = previous delimiter or start of string
        $regExp .= '(' . $variant . ')'; // $2 = the term to replace
        $regExp .= '(\W|$)'; // $3 = following delimiter or end of string
        $regExp .= '/ui'; // unicode, case-insensitive

        // replace all ocurrences of $variant into $tagContent with a glossary link
        $par = preg_replace_callback($regExp,
                function ($matches) use ($data, $variant)
                {
                    // look if already replaced once in this item, so just leave it unchanged
                    if ('item' == $this->glossaryOptions['coverage'] && $this->termReplaced ) {
                        return $matches[0];
                    }

                    /* if coverage type is "first" and term is already defined,
                     * don't replace the term
                     */
                    if ('first' == $this->glossaryOptions['coverage']
                            && in_array($data->getTerm(), $this->alreadyDefinedTerms)) {
                        // already replaced elsewhere, just leave it unchanged
                        return $matches[0];
                    }

                    // if not already defined, add it to array
                    if (!in_array($data->getTerm(), $this->alreadyDefinedTerms)) {
                        $this->alreadyDefinedTerms[] = $data->getTerm();
                    }

                    // create the anchor link from the slug
                    // and get the number given to the anchor link just created
                    list($anchorLink, $num) = $this->saveProcessedDefinition(
                            $data,
                            sprintf('auto-glossary-term-%s',
                                    $data->getSlug()
                                    )
                            );

                    $this->termReplaced = true;

                    // save the placeholder for this slug to be replaced later
                    $placeHolder = $this->textPreserver->createPlacehoder($data->getSlug(). '-' . $num);

                    // save the placeholder for this term (to avoid further matches)
                    $placeHolder2 = $this->textPreserver->createPlacehoder($matches[2]);

                    // create replacement
                    $repl = sprintf(
                            '<span class="auto-glossary-term">'
                                    . '<a href="#auto-glossary-%s" id="auto-glossary-term-%s">%s</a>'
                                    . '</span>', $placeHolder, $placeHolder, $placeHolder2);

                    // save xref
                    $this->saveXref($data->getTerm(), $variant);

                    // return reconstructed match
                    return $matches[1] . $repl . $matches[3];
                }, $text);

        return $par;
    }

    /**
     * Saves the xref for the replaced term for future reference
     *
     * @param string $term
     * @param string $variant
     * @param string $count
     */
    protected function saveXref($term, $variant)
    {
        $this->glossary->get($term)->addXref($variant, $this->item['config']['content']);
    }

    /**
     * Save an anchor link to be registered later
     *
     * @param string $term
     * @param string $anchorLink
     * @return string The text of the anchor link saved
     */
    protected function saveProcessedDefinition($data, $anchorLink)
    {
        $count = count($data->getAnchorLinks());

        $newAnchorLink = $anchorLink . '-' . $count;
        $data->addAnchorLink($newAnchorLink);

        return array($newAnchorLink, $count);
    }

    /**
     * Replace the contents of the atributes in item by a placeholder.
     *
     * @param array $attribute The attributes to save
     * @param string &$item The item text to modify
     * @return array $list of placeholders and original values
     */
    protected function saveAttributes(array $attribute, &$item)
    {
        $list = array();

        // replace all the contents of the attribute with a placeholder
        $regex = sprintf('/(%s)="(.*)"/Ums', implode('|', $attribute));

        $item = preg_replace_callback($regex,
                function ($matches) use (&$list, $attribute)
                {
                    $attr = $matches[1];
                    $value = $matches[2];

                    $placeHolder = '@' . md5($value . count($list)) . '@';
                    $list[$placeHolder] = $value;
                    return sprintf('%s="%s"', $attr, $placeHolder);
                }, $item);

        return $list;
    }

    /**
     * Replace the contents of the tags in item by a placeholder.
     *
     * @param array $tag The attributes to save
     * @param string &$item The item text to modify
     * @return array $list of placeholders and original values
     */
    protected function saveTagContents(array $tag, &$item)
    {
        $list = array();

        // replace all the contents of the tags with a placeholder
        $regex = sprintf('/<(?<prev>(%s)[> ].*)>(?<content>.*)</Ums', implode('|', $tag));

        $item = preg_replace_callback($regex,
                function ($matches) use (&$list, $tag)
                {
                    $prev = $matches['prev'];
                    $content = $matches['content'];

                    $placeHolder = '@' . md5($content . count($list)) . '@';
                    $list[$placeHolder] = $content;
                    return sprintf('<%s>%s<', $prev, $placeHolder);

                }, $item);

        return $list;
    }

    /**
     * Restore all attributes from the list of placeholders into item
     *
     * @param array $list of placeholders and its values
     * @param string $item to replace into
     * @return string $item with replacements
     */
    protected function restoreFromList(array $list, $item)
    {
        foreach ($list as $key => $value) {
            $key = str_replace('/', '\/', $key);
            $item = preg_replace('/' . $key . '/Ums', $value, $item);
        }

        return $item;
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

