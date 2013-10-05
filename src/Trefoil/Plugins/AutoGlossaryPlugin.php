<?php
namespace Trefoil\Plugins;

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

    protected $processedGlossary;

    /**
     * Options that govern the glossary processing
     * @var array
     */
    protected $glossaryOptions = array();

    /**
     * Book-wide term definitions
     * @var array
     */
    //protected $bookDefinitions = array();

    /**
     * Current item term definitions ($bookDefinitions will be merged in)
     * @var array
     */
    //protected $itemDefinitions = array();

    /**
     * Definitions that have been processed with its processing data (for reporting)
     * @var array
     */
    //protected $processedDefinitions = array();

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

        // MAGS
        $this->glossary = new Glossary();
        $this->processedGlossary = new Glossary();

        // get all the glossary terms from file and create the definitions data structure
        $glossary = $this->readBookGlossary();

        if ($glossary) {
            //$this->bookDefinitions = $this->extractDefinitions($glossary);
            //$this->bookDefinitions = $this->explodePluralizedTerms($this->bookDefinitions);

            $this->bookGlossary = $this->extractDefinitions($glossary);
        }
    }

    public function onItemPostParse(ParseEvent $event)
    {
        $this->init($event);

        // read item glossary and merge it with book glossary
        //$definitions = array();
        $itemGlossary = $this->readItemGlossary();

        if (isset($itemGlossary['glossary'])) {
            //$definitions = $this->extractDefinitions($itemGlossary);
            //$definitions = $this->explodePluralizedTerms($definitions);

            $this->itemGlossary = $this->extractDefinitions($itemGlossary);
            //$this->itemDefinitions = array_replace_recursive($this->bookDefinitions, $definitions);

            $this->glossary = clone($this->bookGlossary);

            // add the book-wide definitions
            $this->glossary->merge($this->itemGlossary);

            print_r($this->glossary);
        }

        // look type of processing
        if (in_array($this->item['config']['element'], $this->glossaryOptions['elements'])) {
            // replace each term with a link to its definition
            $this->replaceTerms();

            $this->processedGlossary->merge(clone($this->glossary));

        } elseif ('auto-glossary' == $this->item['config']['element']) {
            // generate the auto glossary
            $this->generateAutoGlossary();
        }

        $event->setItem($this->item);
    }

    public function onItemPreDecorate(BaseEvent $event)
    {
        $this->init($event);

        print_r( $this->internalLinksMapper);

        $this->fixInternalLinks();

        $event->setItem($this->item);
    }

    public function onPostPublish(BaseEvent $event)
    {
        $this->init($event);

        /*
        if (!$this->processedDefinitions) {
            // no definitions => do nothing
            return;
        }
        */

        echo("\n>PROCESSED==================================\n");
        print_r($this->processedGlossary);
        echo("\n<PROCESSED==================================\n");
        $this->glossary = $this->processedGlossary;

        // create the xref report
        $outputDir = $this->app['publishing.dir.output'];
        $reportFile = $outputDir . '/report-auto-glossary-xref.txt';

        $report = '';
        $report .= $this->getUsedTermsReport();
        $report .= "\n\n";
        $report .= $this->getNotUsedTermsReport();

        if (!$this->generated) {
            $this->output
                    ->write(
                            " <error>No glossary has been generated, check for missing 'auto-glosssary' contents element.</error>\n");
        }

        file_put_contents($reportFile, $report);
    }

    /**
     * Read the book-wide glossary file
     *
     * @return array
     */
    protected function readBookGlossary()
    {
        $bookDir = $this->app->get('publishing.dir.book');
        $contentsDir = $bookDir . '/Contents';
        $glossaryFile = $contentsDir . '/auto-glossary.yml';

        if (file_exists($glossaryFile)) {
            $glossaryDefinition = Yaml::parse($glossaryFile);
        } else {
            $this->output
                    ->write(
                            sprintf(" <comment>No book glossary definition file '%s' found.</comment>\n",
                                    realpath($contentsDir) . '/auto-glossary.yml'));
            //return array();
            $glossaryDefinition = array();
        }

        // defaults
        $default = array(
                'glossary' => array(
                        'options' => array(
                                'coverage' => 'all',
                                'elements' => array(
                                        'chapter'
                                        )
                                ),
                        'terms' => array()
                        )
                );
        $glossaryDefinition = array_replace_recursive($default, $glossaryDefinition);

        // set source
        $glossaryDefinition['source'] = basename($glossaryFile);

        // set book glossary options
        $this->glossaryOptions = $glossaryDefinition['glossary']['options'];

        return $glossaryDefinition;
    }

    /**
     * Read the item glossary file
     *
     * @return array
     */
    protected function readItemGlossary()
    {
        $bookDir = $this->app->get('publishing.dir.book');
        $contentsDir = $bookDir . '/Contents';

        if (!$this->item['config']['content']) {
            return array();
        }

        $fileName = pathinfo($this->item['config']['content'], PATHINFO_FILENAME);
        $glossaryFile = $contentsDir . '/' . $fileName . '-auto-glossary.yml';

        if (file_exists($glossaryFile)) {
            $glossaryDefinition = Yaml::parse($glossaryFile);
        } else {
            $glossaryDefinition = array();
        }

        // defaults
        $default = array(
                'glossary' => array(
                        'terms' => array()
                )
        );

        $glossaryDefinition = array_replace_recursive($default, $glossaryDefinition);

        // set source
        $glossaryDefinition['source'] = basename($glossaryFile);

        return $glossaryDefinition;
    }

    /**
     * Extract definitions from the glossary file contents
     */
    protected function extractDefinitions($glossaryDefinition)
    {
        $glossary = new Glossary();

        //$definitions = array();
        foreach ($glossaryDefinition['glossary']['terms'] as $term => $definition) {

            /*
            // assign an unique slug for this term
            if (!isset($this->slugs[$term])) {
                $slug = $this->app->get('slugger')->slugify($term);
                $this->slugs[$term] = $slug;
            }
            */

            $description = '';
            if (is_array($definition)) {
                $description = isset($definition['description']) ? $definition['description'] : '';
            } else {
                $description = $definition;
            }

            /*
            $definitions[$term] = array(
                    'slug' => $this->slugs[$term],
                    'description' => $description,
                    'source' =>  $glossary['source']
                    );

            */

            // MAGS
            $gi = new GlossaryItem();
            $gi->setTerm($term);
            $gi->setSlug($this->app->get('slugger')->slugify($term));
            $gi->setSource($glossaryDefinition['source']);
            $gi->setDescription($description);

            $glossary->add($gi);
        }

        return $glossary;
        //return $definitions;
    }

    /**
     * Explode pluralized terms
     */
    /*
    protected function explodePluralizedTerms($definitions)
    {
        $newDefs = array();

        $regExp = '/';
        $regExp .= '(?<root>[\w\s]*)'; // root of the term (can contain in-between spaces)
        $regExp .= '(\['; // opening square bracket
        $regExp .= '(?<suffixes>.+)'; // suffixes
        $regExp .= '\])?'; // closing square bracket
        $regExp .= '/u'; // unicode

        foreach ($definitions as $term => $data) {
            $variants = array();
            if (preg_match($regExp, $term, $parts)) {
                if (array_key_exists('suffixes', $parts)) {
                    $suffixes = explode('|', $parts['suffixes']);
                    if (1 == count($suffixes)) {
                        // exactly one suffix means root without and with suffix (i.e. 'word[s]')
                        $variants[] = $parts['root'];
                        $variants[] = $parts['root'] . $suffixes[0];
                    } else {
                        // more than one suffix means all the variations (i.e. 'entit[y|ies]')
                        foreach ($suffixes as $suffix) {
                            $variants[] = $parts['root'] . $suffix;
                        }
                    }
                } else {
                    // no suffixes, just the root definition
                    $variants[] = $parts['root'];
                }
            }
            $data['variants'] = $variants;
            $newDefs[$term] = $data;
        }

        return $newDefs;
    }
    */

    /**
     * Replace terms in item's content
     */
    protected function replaceTerms()
    {
        /*
        if (!$this->itemDefinitions) {
            return;
        }
        */
        if (!$this->glossary->count()) {
            return;
        }

        // save new definitions preserving the anchorLinks
        /*
        foreach ($this->itemDefinitions as $term => $data) {
            $anchorLinks = isset($this->processedDefinitions[$term]['anchorLinks'])
                        ? $this->processedDefinitions[$term]['anchorLinks']
                        : array();
            $this->processedDefinitions[$term] = $data;
            $this->processedDefinitions[$term]['anchorLinks'] = $anchorLinks;
        }
        */

        $content = $this->item['content'];

        // save existing values of tags contents we don't want to get modified into
        $savedTagContents = $this->saveTagContents(array('a'), $content);

        // save existing values of attributes we don't want to get modified into
        $savedAttributes = $this->saveAttributes(array('title', 'alt', 'src', 'href'), $content);

        // replace all the defined terms with a link to the definition
        $savedStrings = array();

        $anchorLinks = array();

        //foreach ($this->itemDefinitions as $term => $data) {
        foreach ($this->glossary as $term => $data) {
            foreach ($data->getVariants() as $variant) {
                $contentMod = $this
                        ->replaceTermVariant($content, $data, $variant, $savedStrings);
                if ($contentMod != $content) {
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

        print_r($this->internalLinksMapper);

        /*
        // register all anchor link for this item
        foreach ($data->getAnchorLinks() as $anchorLink) {
            $this->saveInternalLinkTarget($anchorLink);
        }
        */

        // replace back each ocurrence of the saved placeholders with the corresponding value
        $content = $this->restoreFromList($savedAttributes, $content);

        // replace back each ocurrence of the saved placeholders with the corresponding value
        $content = $this->restoreFromList($savedTagContents, $content);

        // replace each ocurrence of the placeholders with the corresponding real string
        $content = $this->restoreFromList($savedStrings, $content);

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
     * @param array $savedStrings Lis of generated slugs and its replacements
     * @return string|mixed
     */
    protected function replaceTermVariant($item, $data, $variant, array &$savedStrings)
    {
        // construct regexp to replace into certain tags
        $tags = array('p', 'li', 'dd');

        $patterns = array();
        foreach ($tags as $tag) {
            // note that the tag is the first capture group.
            $patterns[] = sprintf('/<(%s)>(.*)<\/%s>/Ums', $tag, $tag);
        }

        $count = 0;

        // replace all occurrences of $variant into text $item with a glossary link
        // return $anchorLinks and $savedStrings to be processed later
        $item = preg_replace_callback($patterns,
                function ($matches) use ($data, $variant, $item, &$count, &$savedStrings)
                {
                    // extract what to replace
                    $tag = $matches[1];
                    $tagContent = $matches[2];

                    // do the replacement
                    $newContent = $this
                            ->replaceTermVariantIntoString($tagContent, $data, $variant, $count, $savedStrings);

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
     * @param array $savedStrings Lis of generated slugs and its replacements
     */
    protected function replaceTermVariantIntoString($text, $data, $variant, &$count, &$savedStrings)
    {

        // construct the regexp to replace inside the tag content
        $regExp = '/';
        $regExp .= '(^|\W)'; // $1 = previous delimiter or start of string
        $regExp .= '(' . $variant . ')'; // $2 = the term to replace
        $regExp .= '(\W|$)'; // $3 = following delimiter or end of string
        $regExp .= '/ui'; // unicode, case-insensitive

        // replace all ocurrences of $variant into $tagContent with a glossary link
        // return $anchorLinks and $savedStrings to be processed later
        $par = preg_replace_callback($regExp,
                function ($matches) use ($data, $variant, &$count, &$savedStrings)
                {
                    // look if already replaced once in this item, so just leave it unchanged
                    if ('item' == $this->glossaryOptions['coverage'] && $count > 0) {
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
                    list($anchorLink, $num) = $this
                            ->saveProcessedDefinition($data, sprintf('auto-glossary-term-%s', $data->getSlug()));

                    // save the placeholder for this slug to be replaced later
                    $placeHolder = '#' . count($savedStrings) . '#';
                    $savedStrings[$placeHolder] = $data->getSlug() . '-' . $num;

                    $count++;

                    // save the placeholder for this term (to avoid further matches)
                    $placeHolder2 = '#' . count($savedStrings) . '#';
                    $savedStrings[$placeHolder2] = $matches[2];

                    // create replacement
                    $repl = sprintf(
                            '<span class="auto-glossary-term">'
                                    . '<a href="#auto-glossary-%s" id="auto-glossary-term-%s">%s</a>'
                                    . '</span>', $placeHolder, $placeHolder, $placeHolder2);

                    // save the anchor link to be registered later
                    //$data->addAnchorLink($anchorLink);

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
        /*
        if (!isset($this->xrefs[$term])) {
            $this->xrefs[$term] = array();
        }

        if (!isset($this->xrefs[$term][$variant])) {
            $this->xrefs[$term][$variant] = array();
        }

        $name = $this->item['config']['content'];
        if (!isset($this->xrefs[$term][$variant][$name])) {
            $this->xrefs[$term][$variant][$name] = 0;
        }

        $this->xrefs[$term][$variant][$name]++;
        */

        // MAGS
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
        /*
        if (!isset($this->processedDefinitions[$term]['anchorLinks'])) {
            $this->processedDefinitions[$term]['anchorLinks'] = array();
        }

        $count = count($this->processedDefinitions[$term]['anchorLinks']);
        $newAnchorLink = $anchorLink . '-' . $count;

        $this->processedDefinitions[$term]['anchorLinks'][] = $newAnchorLink;
        */

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

                    $placeHolder = '@' . md5($attr . count($list)) . '@';
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
        /*
        if (!$this->processedDefinitions) {
            return;
        }
        */

        //echo "==== generate =============\n";
        //print_r($this->processedDefinitions);

        $content = $this->item['content'];

        $variables = array(
                //'definitions' => $this->processedDefinitions,
                'definitions' => $this->processedGlossary,
                'item' => $this->item
                );

        $rendered = $this->app->get('twig')->render('auto-glossary-items.twig', $variables);

        // register all anchor links
        //foreach ($this->processedDefinitions as $term => $data) {
        foreach ($this->processedGlossary as $term => $data) {
            /*
            if (isset($data['anchorLinks'])) {
                foreach ($data['anchorLinks'] as $index => $anchorLink) {
                    $this->saveInternalLinkTarget('auto-glossary-' . $data['slug'] . '-' . $index);
                }
            }
            */

            foreach ($data->getAnchorLinks() as $index => $anchorLink) {
                $this->saveInternalLinkTarget('auto-glossary-' . $data->getSlug() . '-' . $index);
            }
        }

        $this->generated = true;

        // concat rendered string to content instead of replacing it to preserve user content
        $content .= $rendered;

        $this->item['content'] = $content;
    }

    protected function getUsedTermsReport()
    {
        $report = array();

        $report[] = 'Glossary terms X-Ref';
        $report[] = '====================';
        $report[] = 'Coverage: ' . $this->glossaryOptions['coverage'];
        $report[] = 'Elements: ' . '"' . join('", "', $this->glossaryOptions['elements']) . '"';
        $report[] = '';

        $report[] = $this->utf8Sprintf('%-30s %-30s %-30s %5s %-30s', 'Term', 'Variant', 'Item', 'Count', 'Source');
        $report[] = $this->utf8Sprintf("%'--30s %'--30s %'--30s %'-5s %'--30s", '', '', '', '', '');

        //print_r($this->glossary);

        $auxTerm = '';
        $auxVariant = '';
        foreach ($this->glossary as $term => $data) {
            $auxTerm = $term;
            foreach ($data->getXref() as $variant => $items) {
                $auxVariant = $variant;
                foreach ($items as $item => $count) {
                    $report[] = $this
                            ->utf8Sprintf('%-30s %-30s %-30s %5u %-30s', $auxTerm, $auxVariant, $item,
                                    $count, $data->getSource());
                    $auxTerm = '';
                    $auxVariant = '';
                }
            }
        }

        return implode("\n", $report) . "\n";
    }

    protected function getNotUsedTermsReport()
    {
        $report = array();

        $report[] = 'Glossary terms not used';
        $report[] = '=======================';
        $report[] = '';

        $report[] = $this->utf8Sprintf('%-30s %-30s', 'Term', 'Source');
        $report[] = $this->utf8Sprintf("%'--30s %'--30s", '', '');

        foreach ($this->glossary as $term => $data) {
            if (!count($data->getXref($term))) {
                $report[] = $this->utf8Sprintf('%-30s %-30s', $term, $data->getSource());
            }
        }

        return implode("\n", $report) . "\n";
    }

    protected function utf8Sprintf($format)
    {
        $args = func_get_args();

        for ($i = 1; $i < count($args); $i++) {
            $args[$i] = iconv('UTF-8', 'ISO-8859-15', $args[$i]);
        }

        return iconv('ISO-8859-15', 'UTF-8', call_user_func_array('sprintf', $args));
    }
}

