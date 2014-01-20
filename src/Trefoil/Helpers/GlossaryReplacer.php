<?php
/*
 * This file is part of the trefoil application.
 *
 * (c) Miguel Angel Gabriel <magabriel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Trefoil\Helpers;

/**
 * Replaces terms of a glossary into a given text (HTML).
 *
 * 'options' parameter can have the following values:
 *
 *     'coverage': ['all', 'item', 'first'], where:
 *          'all' : replace all ocurrences of term with a link to the definition.
 *          'item': (default) only first ocurrence into current text.
 *          'first': first ocurrence in this or other text pieces (for this to work the Glossary object
 *                  instance received must be the that was passed to each of the GlossaryReplacer objects
 *                  that process all the text pieces).
 *     'elements' ['chapter'] # items where global terms should be replaced.
 *
 * Other options are ignored.
 */
class GlossaryReplacer
{
    /**
     * The Glossary object
     *
     * @var Glossary
     */
    protected $glossary;

    /**
     * The HTML text to replace into
     *
     * @var string
     */
    protected $text;

    /**
     * The options for glossary processing
     *
     * @var array
     */
    protected $glossaryOptions = array();

    /**
     * Identifier for the text to be used in setting cross-references
     *
     * @var string
     */
    protected $textId;

    /**
     * The TextPreserver instance
     *
     * @var TextPreserver
     */
    protected $textPreserver;

    /**
     * @param Glossary      $glossary        The glossary object
     * @param TextPreserver $textPreserver   A TextPreserver instance
     * @param string        $text            The text to replace into
     * @param string        $textId          The id of the text, for cross-reference
     * @param array         $glossaryOptions The options to apply
     */
    public function __construct(Glossary $glossary,
                                TextPreserver $textPreserver,
                                $text,
                                $textId,
                                $glossaryOptions = array())
    {
        $this->glossary = $glossary;
        $this->textPreserver = $textPreserver;
        $this->text = $text;
        $this->textId = $textId;
        $this->glossaryOptions = $glossaryOptions;
    }

    /**
     * Do al the replacements of glossary terms into text, returning the result.
     * Also, the glossary object will be modified for each term with the anchorlinks created into the text
     * and the Xrefs for further reference.
     *
     * @return string
     */
    public function replace()
    {
        if (!$this->glossary->count()) {
            // no glossary terms
            return $this->text;
        }

        // set the TextPeserver instance for this text processing
        $this->textPreserver->setText($this->text);

        // save existing values of tags contents we don't want to get modified into
        $this->textPreserver->preserveHtmlTags(array('a', 'pre'));

        // save existing values of attributes we don't want to get modified into
        $this->textPreserver->preserveHtmlTagAttributes(array('title', 'alt', 'src', 'href'));

        // get the modified text
        $text = $this->textPreserver->getText();

        // process each variant of each term
        foreach ($this->glossary as $glossaryItem/* @var $glossaryItem GlossaryItem */) {

            foreach ($glossaryItem->getVariants() as $variant) {
                $newText = $this->replaceTermVariant($text, $glossaryItem, $variant);

                if ($newText != $text) {
                    // at least a replacement ocurred
                    if ('item' == $this->glossaryOptions['coverage']) {
                        // already replaced once in this item, so ignore subsequent ocurrences and variants
                        $text = $newText;
                        break;
                    }
                }

                $text = $newText;
            }
        }

        // refresh the modified text into the TextPreserver and restore all saved strings
        $this->textPreserver->setText($text);
        $text = $this->textPreserver->restore();

        return $text;
    }

    /**
     * Replace a term variant into the content of certain tags
     *
     * @param string       $text
     * @param GlossaryItem $glossaryItem
     * @param string       $variant      The variant to replace
     *
     * @return string
     */
    protected function replaceTermVariant($text, GlossaryItem $glossaryItem, $variant)
    {
        // construct regexp to replace only into certain tags
        $tags = array('p', 'li', 'dd');

        $patterns = array();
        foreach ($tags as $tag) {
            $patterns[] = sprintf('/<(?<tag>%s)>(?<content>.*)<\/%s>/Ums', $tag, $tag);
        }

        // replace all occurrences of $variant into text $item with a glossary link
        $me = $this;
        $text = preg_replace_callback(
            $patterns,
            function ($matches) use ($me, $glossaryItem, $variant) {
                // extract what to replace
                $tag = $matches['tag'];
                $tagContent = $matches['content'];

                // do the replacement
                $newContent = $me->replaceTermVariantIntoString($tagContent, $glossaryItem, $variant);

                // reconstruct the original tag with the modified text
                return sprintf('<%s>%s</%s>', $tag, $newContent, $tag);
            },
            $text
        );

        return $text;
    }

    /**
     * Replace a term variant into a given string
     *
     * @param string       $text
     * @param GlossaryItem $glossaryItem
     * @param string       $variant      The variant to replace
     *
     * @return string
     */
    protected function replaceTermVariantIntoString($text, GlossaryItem $glossaryItem, $variant)
    {
        // construct the regexp to replace inside the tag content
        $regExp = '/';
        $regExp .= '(^|\W)'; // $1 = previous delimiter or start of string
        $regExp .= '(' . $variant . ')'; // $2 = the term to replace
        $regExp .= '(\W|$)'; // $3 = following delimiter or end of string
        $regExp .= '/ui'; // unicode, case-insensitive

        // replace all ocurrences of $variant into $tagContent with a glossary link
        $me = $this;
        $text = preg_replace_callback(
            $regExp,
            function ($matches) use ($me, $glossaryItem, $variant) {
                // look if already replaced once in this item, so just leave it unchanged
                if ('item' == $me->glossaryOptions['coverage']) {
                    foreach ($glossaryItem->getXref() as $variant => $xRefs) {
                        if (isset($xRefs[$me->textId])) {
                            return $matches[0];
                        }
                    }
                }

                /* if coverage type is "first" and term is already defined,
                 * don't replace the term again
                */
                if ('first' == $me->glossaryOptions['coverage'] && $glossaryItem->getXref()) {
                    // already replaced elsewhere, just leave it unchanged
                    return $matches[0];
                }

                /* create the anchor link from the slug
                 * and get the number given to the anchor link just created
                 */
                list($anchorLink, $num) = $me->saveProcessedDefinition(
                                             $glossaryItem,
                                             sprintf('auto-glossary-term-%s', $glossaryItem->getSlug())
                );

                // save the placeholder for this slug to be replaced later
                $placeHolder = $me->textPreserver->createPlacehoder($glossaryItem->getSlug() . '-' . $num);

                // save the placeholder for this term (to avoid further unwanted matches into)
                $placeHolder2 = $me->textPreserver->createPlacehoder($matches[2]);

                // create replacement
                $repl = sprintf(
                    '<span class="auto-glossary-term">'
                    . '<a href="#auto-glossary-%s" id="auto-glossary-term-%s">%s</a>'
                    . '</span>',
                    $placeHolder,
                    $placeHolder,
                    $placeHolder2
                );

                // save xref
                $glossaryItem->addXref($variant, $me->textId);

                // return reconstructed match
                return $matches[1] . $repl . $matches[3];
            },
            $text
        );

        return $text;
    }

    /**
     * Save an anchor link to be registered later
     *
     * @param GlossaryItem $glossaryItem
     * @param string       $anchorLink
     *
     * @return string The text of the anchor link saved
     */
    protected function saveProcessedDefinition(GlossaryItem $glossaryItem, $anchorLink)
    {
        $count = count($glossaryItem->getAnchorLinks());

        $newAnchorLink = $anchorLink . '-' . $count;
        $glossaryItem->addAnchorLink($newAnchorLink);

        return array($newAnchorLink, $count);
    }
}
