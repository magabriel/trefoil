<?php
namespace Trefoil\Plugins;
use Easybook\Events\BaseEvent;
use Easybook\Util\Slugger;
use Easybook\Util\Toolkit;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Easybook\Events\EasybookEvents as Events;
use Easybook\Events\ParseEvent;
use Trefoil\Events\TrefoilEvents;

/**
 * This plugin takes care of the automatic interactive glossary feature.
 */
class AutoGlossaryPlugin extends EpubInteractivePluginBase implements EventSubscriberInterface
{
    protected $slugs = array();
    protected $output;
    protected $options = array();
    protected $bookDefinitions = array();
    protected $itemDefinitions = array();

    protected static $definitions = array();
    protected static $anchorLinks = array();
    protected static $alreadyDefinedTerms = array();
    protected static $xrefs = array();
    protected static $generated = false;

    static public function getSubscribedEvents()
    {
        return array(TrefoilEvents::PRE_PUBLISH_AND_READY => 'onPrePublishAndReady',
                Events::POST_PARSE => array('onItemPostParse', -100),
                Events::POST_PUBLISH => 'onPostPublish');
    }

    public function onPrePublishAndReady(BaseEvent $event)
    {
        $this->app = $event->app;
        $this->output = $this->app->get('console.output');

        // reset static variables to allow for functional testing
        static::$definitions = array();
        static::$anchorLinks = array();
        static::$alreadyDefinedTerms = array();
        static::$xrefs = array();
        static::$generated = false;

        // get all the glossary terms from file and create the definitions data structure
        $glossary = $this->readBookGlossary();
        if ($glossary) {
            $this->bookDefinitions = $this->extractDefinitions($glossary);
            $this->bookDefinitions = $this->explodePluralizedTerms($this->bookDefinitions);
        }
    }

    public function onItemPostParse(ParseEvent $event)
    {
        $this->init($event);
        $this->app['book.logger']->debug('onItemPostParse:begin', get_class(), $this->item['config']['content']);

        $this->output = $event->app->get('console.output');

        // read item glossary and merge it with book glossary
        $definitions = array();
        $itemGlossary = $this->readItemGlossary();
        if (isset($itemGlossary['glossary'])) {
            $definitions = $this->extractDefinitions($itemGlossary);
            $definitions = $this->explodePluralizedTerms($definitions);
        }
        $this->itemDefinitions = array_replace_recursive($this->bookDefinitions, $definitions);

        // look type of processing
        if (in_array($this->item['config']['element'], $this->options['elements'])) {
            // replace each term with a link to its definition
            $this->replaceTerms();
        } elseif ('auto-glossary' == $this->item['config']['element']) {
            // generate the auto glossary
            $this->generateAutoGlossary();
        }

        $event->setItem($this->item);

        $this->wrapUp();
        $this->app['book.logger']->debug('onItemPostParse:end', get_class(), $this->item['config']['content']);
    }

    public function onPostPublish(BaseEvent $event)
    {
        $this->app = $event->app;
        $this->output = $event->app->get('console.output');

        $this->app['book.logger']->debug('onPostPublish:begin', get_class());

        if (!static::$definitions) {
            // no definitions => do nothing
            return;
        }

        // create the xref report
        $outputDir = $this->app['publishing.dir.output'];
        $reportFile = $outputDir . '/report-auto-glossary-xref.txt';

        $report = '';
        $report .= $this->getUsedTermsReport();
        $report .= "\n\n";
        $report .= $this->getNotUsedTermsReport();

        if (!static::$generated) {
            $this->output
                    ->write(
                            " <error>No glossary has been generated, check for missing 'auto-glosssary' contents element.</error>\n");
        }

        file_put_contents($reportFile, $report);

        $this->app['book.logger']->debug('onPostPublish:end', get_class());
    }

    /**
     * Read the glossary file
     *
     * @return array
     */
    protected function readBookGlossary()
    {
        $bookDir = $this->app->get('publishing.dir.book');
        $contentsDir = $bookDir . '/Contents';
        $glossaryFile = $contentsDir . '/auto-glossary.yml';

        if (file_exists($glossaryFile)) {
            $glossary = Yaml::parse($glossaryFile);
        } else {
            $this->output
                    ->write(
                            sprintf(" <comment>No glossary definition file '%s' found.</comment>\n",
                                    realpath($contentsDir) . '/auto-glossary.yml'));
            //return array();
            $glossary = array();
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
        $glossary = array_replace_recursive($default, $glossary);

        // set source
        $glossary['source'] = basename($glossaryFile);

        // set options
        $this->options = $glossary['glossary']['options'];

        return $glossary;
    }

    /**
     * Read the glossary file
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
            $glossary = Yaml::parse($glossaryFile);
        } else {
            $glossary = array();
        }

        // defaults
        $default = array(
                'glossary' => array(
                        'terms' => array()
                )
        );

        $glossary = array_replace_recursive($default, $glossary);

        // set source
        $glossary['source'] = basename($glossaryFile);

        return $glossary;
    }

    /**
     * Extract definitions from the glossary file contents
     */
    protected function extractDefinitions($glossary)
    {
        $slugger = new Slugger($this->app);

        $definitions = array();
        foreach ($glossary['glossary']['terms'] as $term => $definition) {

            // assign an unique slug for this term
            if (!isset($this->slugs[$term])) {
                $slug = $slugger->slugify($term);
                $this->slugs[$term] = $slug;
            }

            $forceIn = array();
            $neverIn = array();
            $onlyIn = array();
            $description = '';
            if (is_array($definition)) {
                $forceIn = isset($definition['force-in']) ? $definition['force-in'] : array();
                $neverIn = isset($definition['never-in']) ? $definition['never-in'] : array();
                $onlyIn = isset($definition['only-in']) ? $definition['only-in'] : array();
                $description = isset($definition['description']) ? $definition['description'] : '';
            } else {
                $description = $definition;
            }

            $definitions[$term] = array(
                    'slug' => $this->slugs[$term],
                    'description' => $description,
                    'force-in' => $forceIn,
                    'never-in' => $neverIn,
                    'only-in' => $onlyIn,
                    'source' =>  $glossary['source']
                    );
        }

        return $definitions;
    }

    /**
     * Explode pluralized terms
     */
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

    /**
     * Replace terms in item's content
     */
    protected function replaceTerms()
    {
        if (!$this->itemDefinitions) {
            return;
        }

        // save new definitions preserving the anchorLinks
        foreach ($this->itemDefinitions as $term => $data) {
            $anchorLinks = isset(static::$definitions[$term]['anchorLinks'])
                        ? static::$definitions[$term]['anchorLinks']
                        : array();
            static::$definitions[$term] = $data;
            static::$definitions[$term]['anchorLinks'] = $anchorLinks;
        }

        $content = $this->item['content'];

        // save existing values of tags contents we don't want to get modified into
        $savedTagContents = $this->saveTagContents(array('a'), $content);

        // save existing values of attributes we don't want to get modified into
        $savedAttributes = $this->saveAttributes(array('title', 'alt', 'src', 'href'), $content);

        // replace all the defined terms with a link to the definition
        $savedStrings = array();

        $anchorLinks = array();

        foreach ($this->itemDefinitions as $term => $data) {

            // look if the term must be ignored into this item
            $name = substr($this->item['config']['content'], 0, -3);

            if (!in_array($name, $data['never-in'])) {

                // look if must be replaced only in this item (or in each item)
                if (!$data['only-in'] || ($data['only-in'] && in_array($name, $data['only-in']))) {

                    foreach ($data['variants'] as $variant) {
                        $contentMod = $this
                                ->replaceTermVariant($content, $term, $variant, $data['slug'],
                                        $data['force-in'], $anchorLinks, $savedStrings);
                        if ($contentMod != $content) {
                            // at least a replacement ocurred
                            if ('item' == $this->options['coverage']) {
                                // already replaced once in this item, so ignore subsequent ocurrences and variants
                                $content = $contentMod;
                                break;
                            }

                        }
                        $content = $contentMod;
                    }
                }
            }
        }

        // register all anchor link for this item
        foreach ($anchorLinks as $anchorLink) {
            $this->registerAnchorLink($anchorLink);
        }

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
    protected function replaceTermVariant($item, $term, $variant, $slug, $forceIn,
            array &$anchorLinks, array &$savedStrings)
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
                function ($matches) use ($term, $variant, $slug, $item, $forceIn, &$count,
                        &$anchorLinks, &$savedStrings)
                {
                    // extract what to replace
                    $tag = $matches[1];
                    $tagContent = $matches[2];

                    // do the replacement
                    $newContent = $this
                            ->replaceTermVariantIntoString($tagContent, $term, $variant, $slug,
                                    $forceIn, $count, $anchorLinks, $savedStrings);

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
     * @param array $forceIn List of items where replacement needs to be forced
     * @param int $count Number of replacements already made into this item
     * @param array $anchorLinks List of anchor links to be registered
     * @param array $savedStrings Lis of generated slugs and its replacements
     */
    protected function replaceTermVariantIntoString($text, $term, $variant, $slug, $forceIn,
            &$count, &$anchorLinks, &$savedStrings)
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
                function ($matches) use ($term, $variant, $slug, $forceIn, &$count, &$anchorLinks,
                        &$savedStrings)
                {
                    // look if already replaced once in this item, so just leave it unchanged
                    if ('item' == $this->options['coverage'] && $count > 0) {
                        return $matches[0];
                    }

                    /* if coverage type is "first" and term is already defined,
                     * only replace the term if the current publishing item
                     */
                    if ('first' == $this->options['coverage']
                            && in_array($term, static::$alreadyDefinedTerms)) {

                        $name = substr($this->item['config']['content'], 0, -3);
                        if (in_array($name, $forceIn)) {
                            if ($count >= 0) {
                                return $matches[0];
                            }
                        } else {
                            // already replaced elsewhere, just leave it unchanged
                            return $matches[0];
                        }
                    }

                    // if not already defined, add it to array
                    if (!in_array($term, static::$alreadyDefinedTerms)) {
                        static::$alreadyDefinedTerms[] = $term;
                    }

                    // create the anchor link from the slug
                    // and get the number given to the anchor link just created
                    list($anchorLink, $num) = $this
                            ->saveAnchorLink($term, sprintf('auto-glossary-term-%s', $slug));

                    // get the number given to the anchor link just created
                    //$count = count(static::$definitions[$term]['anchorLinks']) - 1;

                    // save the placeholder for this slug to be replaced later
                    $placeHolder = '#' . count($savedStrings) . '#';
                    $savedStrings[$placeHolder] = $slug . '-' . $num;

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
                    $anchorLinks[] = $anchorLink;

                    // save xref
                    $this->saveXref($term, $variant);

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
        if (!isset(static::$xrefs[$term])) {
            static::$xrefs[$term] = array();
        }

        if (!isset(static::$xrefs[$term][$variant])) {
            static::$xrefs[$term][$variant] = array();
        }

        $name = $this->item['config']['content'];
        if (!isset(static::$xrefs[$term][$variant][$name])) {
            static::$xrefs[$term][$variant][$name] = 0;
        }

        static::$xrefs[$term][$variant][$name]++;

    }

    /**
     * Save an anchor link to be registered later
     *
     * @param string $term
     * @param string $anchorLink
     * @return string The text of the anchor link saved
     */
    protected function saveAnchorLink($term, $anchorLink)
    {
        if (!isset(static::$definitions[$term]['anchorLinks'])) {
            static::$definitions[$term]['anchorLinks'] = array();
        }

        $count = count(static::$definitions[$term]['anchorLinks']);
        $newAnchorLink = $anchorLink . '-' . $count;

        static::$definitions[$term]['anchorLinks'][] = $newAnchorLink;

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
        if (!static::$definitions) {
            return;
        }

        //echo "==== generate =============\n";
        //print_r(static::$definitions);

        $content = $this->item['content'];

        $variables = array('definitions' => static::$definitions, 'item' => $this->item);

        $rendered = $this->app->get('twig')->render('auto-glossary-items.twig', $variables);

        // register all anchor links
        foreach (static::$definitions as $term => $data) {
            if (isset($data['anchorLinks'])) {
                foreach ($data['anchorLinks'] as $index => $anchorLink) {
                    $this->registerAnchorLink('auto-glossary-' . $data['slug'] . '-' . $index);
                }
            }
        }

        static::$generated = true;

        // concat rendered strint to content instead of replacing it
        $content .= $rendered;

        $this->item['content'] = $content;
    }

    protected function getUsedTermsReport()
    {
        $report = array();

        $report[] = 'Glossary terms X-Ref';
        $report[] = '====================';
        $report[] = 'Coverage: ' . $this->options['coverage'];
        $report[] = 'Elements: ' . '"' . join('", "', $this->options['elements']) . '"';
        $report[] = '';

        $report[] = $this->utf8Sprintf('%-30s %-30s %-30s %5s', 'Term', 'Variant', 'Item', 'Count');
        $report[] = $this->utf8Sprintf("%'--30s %'--30s %'--30s %'-5s", '', '', '', '');

        $auxTerm = '';
        $auxVariant = '';
        foreach (static::$xrefs as $term => $variants) {
            $auxTerm = $term;
            foreach ($variants as $variant => $items) {
                $auxVariant = $variant;
                foreach ($items as $item => $count) {
                    $report[] = $this
                            ->utf8Sprintf('%-30s %-30s %-30s %5u', $auxTerm, $auxVariant, $item,
                                    $count);
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

        foreach (static::$definitions as $term => $data) {
            if (!isset(static::$xrefs[$term])) {
                $report[] = $this->utf8Sprintf('%-30s %-30s', $term, $data['source']);
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

