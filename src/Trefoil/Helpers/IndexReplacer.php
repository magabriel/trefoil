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

use EasySlugger\Slugger;
use EasySlugger\SluggerInterface;

/**
 * Replaces terms of an index into a given text (HTML).
 *
 */
class IndexReplacer
{
    /**
     * The Index object
     *
     * @var Index
     */
    protected $index;

    /**
     * The HTML text to replace into
     *
     * @var string
     */
    protected $text;

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
     * A Twig instance to render the template
     *
     * @var \Twig_Environment
     */
    private $twig;

    /**
     * @param Index             $index         The index object
     * @param TextPreserver     $textPreserver A TextPreserver instance
     * @param string            $text          The text to replace into
     * @param string            $textId        The id of the text, for cross-reference
     * @param \Twig_Environment $twig          Twig loader to load the template from
     * @param SluggerInterface  $slugger       (NOTE: nullable just for testing - it cannot be mocked)
     */
    public function __construct(Index $index,
                                TextPreserver $textPreserver,
                                $text,
                                $textId,
                                \Twig_Environment $twig,
                                SluggerInterface $slugger = null // just for testing, it cannot be mocked
    )
    {
        $this->index = $index;
        $this->textPreserver = $textPreserver;
        $this->text = $text;
        $this->textId = $textId;
        $this->twig = $twig;
        $this->slugger = $slugger;
    }

    /**
     * Do all the replacements of index terms into text, returning the result.
     * Also, the index object will be modified for each term with the anchorlinks created into the text
     * and the Xrefs for further reference.
     *
     * @return string
     */
    public function replace()
    {
        // set the TextPeserver instance for this text processing
        $this->textPreserver->setText($this->text);

        // save existing values of tags contents we don't want to get modified into
        $this->textPreserver->preserveHtmlTags();

        // save existing values of attributes we don't want to get modified into
        $this->textPreserver->preserveHtmlTagAttributes();

        // get the modified text
        $text = $this->textPreserver->getText();

        // replace all index marks in the text
        $text = $this->replaceManualIndexMarks($text);

        // process each variant of each term
        foreach ($this->index as $indexItem/* @var $indexItem IndexItem */) {
            foreach ($indexItem->getVariants() as $variant) {
                if ($indexItem->isManual()) {
//                    $text = $this->replaceManualTermVariant($text, $indexItem, $variant);
                } else {
                    $text = $this->replaceTermVariant($text, $indexItem, $variant);
                }
            }
        }

        // refresh the modified text into the TextPreserver and restore all saved strings
        $this->textPreserver->setText($text);
        $text = $this->textPreserver->restore();

        return $text;
    }

    /**
     * Replace all manual index marks with the corresponding anchor link target.
     *
     * Cases:
     *
     *   in text           | indexed term        | Notes
     *   ----------------- | ------------------- | -------------------
     *   |a word|          | a word              |
     *   a word|@|         | word                |
     *   "a word"|@|       | a word              | Also for single and typographical quotes
     *   <b>a word<b>|@|   | a word              | Also for <strong> and <em> tags
     *
     * @param $text
     * @return string
     */
    private function replaceManualIndexMarks($text)
    {
        $indexMark = "\|@\|";
        $word = "[\w-]+";

        $pattern = '/';
        $pattern .= '(?:'; // start non-capturing term and mark
        $pattern .= '(?:'; // start non-capturing group for term and delimiters
        $pattern .= '(?<pre>\W)(?<term>' . $word . ')'; // a word string
        $pattern .= '|';
        $pattern .= '(?<pre>")(?<term>.+)(?<post>")'; // a double quoted string
        $pattern .= '|';
        $pattern .= '(?<pre>&#8220;)(?<term>.+)(?<post>&#8221;)'; // a double quoted string (typographical)
        $pattern .= '|';
        $pattern .= '(?<pre>\')(?<term>.+)(?<post>\')'; // a single quoted string
        $pattern .= '|';
        $pattern .= '(?<pre>\&#8216;)(?<term>.+)(?<post>\&#8217;)'; // a single quoted string (typographical)
        $pattern .= '|';
        $pattern .= '(?<pre>\&#171;)(?<term>.+)(?<post>\&#187;)'; // an angle quoted string (typographical)
        $pattern .= '|';
        $pattern .= '(?<pre><em>)(?<term>.+)(?<post><\/em>)'; // an emphasized (underscore) string
        $pattern .= '|';
        $pattern .= '(?<pre><strong>)(?<term>.+)(?<post><\/strong>)'; // an emphasized (bold) string
        $pattern .= '|';
        $pattern .= '(?<pre><b>)(?<term>.+)(?<post><\/b>)'; // an emphasized (bold) string
        $pattern .= ')'; // end non-capturing group term and delimiters
        $pattern .= $indexMark; // followed by and index mark
        $pattern .= ')'; // end non-capturing group term and mark
        $pattern .= '|';
        $pattern .= '(?:\|(?<term>[^@\|]+)\|)'; // a term delimited by "|"

        $pattern .= '/UmuJ'; // Ungreedy, multiline, unicode, allow duplicate subpattern names

        // replace all occurrences with a index link
        $text = preg_replace_callback(
            $pattern,
            function ($matches) use ($indexMark) {
                // extract what to replace
                $pre = isset($matches['pre']) ? $matches['pre'] : "";
                $term = $matches['term'];
                $post = isset($matches['post']) ? $matches['post'] : "";

                // look if the term is configured
                $indexItem = $this->index->getItemWithVariant($term);

                if ($indexItem == null) {
                    $indexItem = new IndexItem();
                    $indexItem->setTerm($term);
                    $indexItem->setText($term);
                    $indexItem->setGroup($term);
                    $indexItem->setSource('manual');
                    if ($this->slugger !== null) {
                        $slug = crc32($term) . '-' . $this->slugger->slugify($term);
                    } else {
                        // do our best (just for testing - slugger cannot be mocked)
                        $slug = str_replace(' ', '-', $term);
                    }
                    $indexItem->setSlug($slug);
                }

                // convert this item to manual even if it was previously automatic
                // to avoid unwanted further processing
                $indexItem->setManual(true);

                // do the replacement
                $newContent = $this->replaceManualTermIntoString($indexItem, $term);

                $this->index->add($indexItem);

                // reconstruct the original tag with the modified text
                return $pre . $newContent . $post;
            },
            $text
        );

        return $text;
    }

    /**
     * Replace a single manual index term with the rendered template.
     *
     * The rendering expects a Twig template called "auto-index-term.twig" to be loadable.
     * Sample template (note that {{ term }} is not used but it is mantained for compatibilty
     * with the automatic index terms case):
     *
     *      {% spaceless %}
     *          {{ term }}<a class="auto-index-term" id="auto-index-term-{{ reference }}"/>
     *      {% endspaceless %}
     *
     * @param IndexItem $indexItem
     * @param  string   $term
     * @return string
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    protected function replaceManualTermIntoString(IndexItem $indexItem, $term)
    {
        /* create the anchor link from the slug
         * and get the number given to the anchor link just created
         */
        $anchorLink = $this->saveAnchorLink($indexItem);

        // save the placeholder for this slug to be replaced later
        $placeHolder = $this->textPreserver->internalCreatePlacehoder($anchorLink);

        // create replacement for link
        $replacement = $this->renderIndexTerm($placeHolder, $term, $indexItem);

        // save xref
        $indexItem->addXref($term, $this->getTextId());

        return $replacement;
    }

    /**
     * Save an anchor link to be registered later
     *
     * @param IndexItem $indexItem
     *
     * @return int The anchor link saved
     */
    protected function saveAnchorLink(IndexItem $indexItem)
    {
        $count = count($indexItem->getAnchorLinks());

        $savedAnchorLink = $indexItem->getSlug() . '-' . $count;
        $indexItem->addAnchorLink($savedAnchorLink);

        return $savedAnchorLink;
    }

    /**
     * Render an index term with the corresponding template.
     *
     * It expects a Twig template called "auto-index-term.twig" to be loadable.
     *
     * Sample template:
     *
     *      {% spaceless %}
     *          {{ term }}<a class="auto-index-term" id="auto-index-term-{{ reference }}"/>
     *      {% endspaceless %}
     *
     * @param  string   $reference
     * @param string    $term
     * @param IndexItem $item
     * @return string
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    protected function renderIndexTerm($reference, $term, IndexItem $indexItem)
    {
        return $this->twig->render('auto-index-term.twig',
            array(
                'reference' => $reference,
                'term' => $term,
                'item' => $indexItem
            ));
    }

    /**
     * @return string
     */
    protected function getTextId()
    {
        return $this->textId;
    }

    /**
     * Replace a term variant into the content of certain tags
     *
     * @param string    $text
     * @param IndexItem $indexItem
     * @param string    $variant The variant to replace
     *
     * @return string
     */
    protected function replaceTermVariant($text, IndexItem $indexItem, $variant)
    {
        // construct regexp to replace only into certain tags
        $tags = array('p', 'li', 'dd');

        $patterns = array();
        foreach ($tags as $tag) {
            $pattern = '/';
            $pattern .= '<(?<tag>%s)(?<attrs>[^>]*)>'; // opening tag with optional attributes
            $pattern .= '(?<content>.*)'; // content
            $pattern .= '<\/%s>'; // closing tag
            $pattern .= '/Ums'; // Ungreedy, multiline, dotall
            $patterns[] = sprintf($pattern, $tag, $tag);
        }

        // replace all occurrences of $variant into text $item with a index link
        // PHP 5.3 compat
        $me = $this;

        $text = preg_replace_callback(
            $patterns,
            function ($matches) use ($me, $indexItem, $variant) {
                // extract what to replace
                $tag = $matches['tag'];
                $tagContent = $matches['content'];
                $attrs = $matches['attrs'];

                // do the replacement
                $newContent = $me->replaceTermVariantIntoString($tagContent, $indexItem, $variant);

                // reconstruct the original tag with the modified text
                return sprintf('<%s%s>%s</%s>', $tag, $attrs, $newContent, $tag);
            },
            $text
        );

        return $text;
    }

    /**
     * Replace a term variant into a given string
     *
     * The rendering expects a Twig template called "auto-index-term.twig" to be loadable.
     * Sample template:
     *
     *      {% spaceless %}
     *          {{ term }}<a class="auto-index-term" id="auto-index-term-{{ reference }}"/>
     *      {% endspaceless %}
     *
     * @param string    $text
     * @param IndexItem $indexItem
     * @param string    $variant The variant to replace
     *
     * @return string
     */
    protected function replaceTermVariantIntoString($text, IndexItem $indexItem, $variant)
    {
        // construct the regexp to replace inside the tag content
        $regExp = '/';
        $regExp .= '(?<prev>^|\W)'; // previous delimiter or start of string
        $regExp .= '(?<term>' . $variant . ')'; // the term to replace
        $regExp .= '(?<after>\W|$)'; // following delimiter or end of string
        $regExp .= '/ui'; // unicode, case-insensitive

        // replace all ocurrences of $variant into $tagContent with a index link

        $textPreserver = $this->textPreserver;
        $twig = $this->twig;

        $text = preg_replace_callback(
            $regExp,
            function ($matches) use ($indexItem, $variant, $textPreserver, $twig) {
                $previous = $matches['prev'];
                $term = $matches['term'];
                $after = $matches['after'];

                /* create the anchor link from the slug
                 * and get the number given to the anchor link just created
                 */
                $anchorLink = $this->saveAnchorLink($indexItem);

                // save the placeholder for this slug to be replaced later
                $placeHolder = $textPreserver->internalCreatePlacehoder($anchorLink);

                // save the placeholder for this term (to avoid further unwanted matches into)
                $placeHolder2 = $textPreserver->internalCreatePlacehoder($term);

                // create replacement for link
                $replacement = $this->renderIndexTerm($placeHolder, $placeHolder2, $indexItem);

                // save xref
                $indexItem->addXref($variant, $this->getTextId());

                // return reconstructed match
                return $previous . $replacement . $after;
            },
            $text
        );

        return $text;
    }
}
