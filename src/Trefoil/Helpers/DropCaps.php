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
 * Add drop caps (HTML markup) to a given HTML text:
 *
 *     <p class="has-dropcaps"><span class="dropcaps">T</span>his has dropcaps.</p>
 *
 * Features:
 *
 * 1.- Add automatic dropcaps to the first paragraph in the text.
 *
 * 2.- Add automatic dropcaps to the first paragraph after each heading of selected levels.
 *
 * 3.- Process Markdown-like markup for dropcaps:
 *
 *      [[T]]his text has first-letter dropcaps.
 *
 *      [[But]] this text has first-word dropcaps.
 *
 * 4.- Process manually-added dropcaps markup (the <span>) adding the "has-dropcaps" class
 *     to the surrounding paragraph.
 */
class DropCaps
{

    /**
     * The first "length" letters are transformed into drop caps
     *
     * @var string
     */
    const MODE_LETTER = 'letter';

    /**
     * The first "length" words are transformed into drop caps
     *
     * @var string
     */
    const MODE_WORD = 'word';

    /**  @var string */
    protected $text;

    /**  @var string */
    protected $mode = self::MODE_LETTER;

    /**  @var int */
    protected $length = 1;

    /**
     * @param string $text   The input text
     * @param string $mode   The working mode (default = MODE_LETTER)
     * @param int    $length The length (default = 1)
     */
    public function __construct($text, $mode = self::MODE_LETTER, $length = 1)
    {
        $this->text = $text;
        $this->mode = $mode;
        $this->length = $length;
    }

    /**
     * Get the processed text
     *
     * @return string
     */
    public function getOutput()
    {
        return $this->text;
    }

    /**
     * Create drop caps for Markdown-style markup, as in "[[He]]llo"
     */
    public function createForMarkdownStyleMarkup()
    {
        $regex = '/';
        $regex .= '\s*<p>\[\[(?<first>.*)\]\](?<rest>.*)<\/p>';
        $regex .= '/Ums'; // Ungreedy, multiline, dotall

        // PHP 5.3 compat
        $me = $this;

        $callback = function ($matches) use ($me) {
            $ptext = $me->internalRenderDropCaps('', $matches['first'], $matches['rest']);
            $html = sprintf('<p class="has-dropcaps">%s</p>', $ptext);

            return $html;
        };

        $this->text = preg_replace_callback($regex, $callback, $this->text);
    }

    /**
     * Create drop caps in the paragraph that starts the text (no preceding
     * heading tag)
     */
    public function createForFirstParagraph()
    {
        $regex = '/';
        $regex .= '^\s*<p>(?<ptext>.*)<\/p>';
        $regex .= '/Us'; // Ungreedy, dotall

        // PHP 5.3 compat
        $me = $this;

        $callback = function ($matches) use ($me) {
            $ptext = $me->internalCreateDropCaps($matches['ptext']);
            $html = sprintf('<p class="has-dropcaps">%s</p>', $ptext);

            return $html;
        };

        $this->text = preg_replace_callback($regex, $callback, $this->text);
    }

    /**
     * Create drop caps in each first paragraph under a heading tag
     *
     * @param array $levels [1...6]
     */
    public function createForHeadings($levels = array(1, 2))
    {
        $regex = '/';
        $regex .= '<h(?<level>[1-6])(?<hrest>.*)>'; // opening heading tag
        $regex .= '(?<hcontent>.*)<\/h\1>'; // closing heading tag (backreference)
        $regex .= '(?<whitespace>\s*)'; // optional whitespace
        $regex .= '<p>(?<ptext>.*)<\/p>'; // 1st paragraph
        $regex .= '/Ums'; // Ungreedy, multiline, dotall

        // PHP 5.3 compat
        $me = $this;

        $callback = function ($matches) use ($me, $levels) {
            if (!in_array($matches['level'], $levels)) {
                return $matches[0];
            }

            $ptext = $me->internalCreateDropCaps($matches['ptext']);

            $html = sprintf(
                '<h%s%s>%s</h%s>%s<p class="has-dropcaps">%s</p>',
                $matches['level'],
                $matches['hrest'],
                $matches['hcontent'],
                $matches['level'],
                $matches['whitespace'],
                $ptext
            );

            return $html;
        };

        $text = preg_replace_callback($regex, $callback, $this->text);

        // only set if no errors (i.e. no <p> tag after a heading)
        if (null !== $text) {
            $this->text = $text;
        }
    }

    /**
     * Process manually-added dropcaps markup, adding additional markup to the containing paragraph.
     *
     * @return string
     */
    public function processManualMarkup()
    {
        $regex = '/';
        $regex .= '<p><span.*class="dropcaps">(?<dropcapstext>.*)<\/span>(?<ptext>.*)<\/p>';
        $regex .= '/Ums'; // Ungreedy, multiline, dotall

        $callback = function ($matches) {
            $html = sprintf(
                '<p class="has-dropcaps"><span class="dropcaps">%s</span>%s</p>',
                $matches['dropcapstext'],
                $matches['ptext']
            );

            return $html;
        };

        $text = preg_replace_callback($regex, $callback, $this->text);

        $this->text = $text;
    }

    /**
     * Create drop caps markup for a text.
     *
     * @param string $text
     *
     * @return string
     *
     * @internal Should be protected but made public for PHP 5.3 compat
     */
    public function internalCreateDropCaps($text)
    {
        if ('word' == $this->mode) {

            // find all words in the text
            preg_match_all('/(\W*\w+\W+)/Us', $text, $matches);

            // isolate the first "$length" words
            $dropCaps = implode('', array_slice($matches[1], 0, $this->length));
            $rest = implode('', array_slice($matches[1], $this->length));

            return $this->internalRenderDropCaps('', $dropCaps, $rest);
        }

        // 'letter' mode
        
        // look if it starts with an empty abbreviated HTML tag (like '<br />')
        if (preg_match('/^(?<tag><.*\/>)(?<rest>.*)$/Uus', $text, $matches)) {

            // isolate the first "$length" letters but skipping the tag
            $dropCaps = mb_substr($matches['rest'], 0, $this->length, 'utf-8');
            $rest = mb_substr($matches['rest'], mb_strlen($dropCaps, 'utf-8'), null, 'utf-8');

            // prepend again the tag *before* the markup
            return $this->internalRenderDropCaps($matches['tag'], $dropCaps, $rest);
        }

        // look if it starts with a normal HTML tag (like '<span>...</span>')
        if (preg_match('/^(?<skip><(?<tag>.*) *(?<attr>.*)>)(?<content>.*)<\/\k<tag>>(?<rest>.*)$/Uus', $text, $matches)) {

            if (strpos($matches['attr'], 'dropcaps') > 0) {
                // already has a explicit dropcaps markup, do nothing
                return $text;
            }

            // isolate the first "$length" letters but skipping the tag
            $skip = $matches['skip'];
            $dropCapsLength = min($this->length, mb_strlen($matches['content'], 'utf-8'));

            $dropCaps = mb_substr($text, mb_strlen($skip, 'utf-8'), $dropCapsLength, 'utf-8');
            $contentRest = mb_substr($matches['content'], mb_strlen(($dropCaps)), null, 'utf-8');
            $rest = $contentRest . sprintf('</%s>', $matches['tag']) . $matches['rest'];
            return $this->internalRenderDropCaps($skip, $dropCaps, $rest);
        }
        
        // look if it starts with an HTML entity
        if (preg_match('/^(?<entity>&[#[:alnum:]]*;)(?<rest>.*)$/Uus', $text, $matches)) {

            // ignore if it is an space
            if ('&nbsp;' == $matches['entity']) {
                return $text;
            }
            // isolate the first "$length" letters but skipping the entity
            $dropCaps = mb_substr($matches['rest'], 0, $this->length, 'utf-8');
            $rest = mb_substr($matches['rest'], strlen($dropCaps), null, 'utf-8');

            // prepend again the entity
            $dropCaps = $matches['entity'] . $dropCaps;
            
            return $this->internalRenderDropCaps('', $dropCaps, $rest);
        }

        // look if it starts with a non-word character(s)
        if (preg_match('/^(?<nonword>\W+)(?<rest>.*)$/Uus', $text, $matches)) {

            // isolate the first "$length" letters but skipping the non-word char(s)
            $dropCaps = mb_substr($matches['rest'], 0, $this->length, 'utf-8');
            $rest = mb_substr($matches['rest'], mb_strlen($dropCaps), null, 'utf-8');

            // prepend again the non-word char(s)
            $dropCaps = $matches['nonword'] . $dropCaps;
            
            return $this->internalRenderDropCaps('', $dropCaps, $rest);
        }

        // normal case, isolate the first "$length" letters
        $dropCaps = mb_substr($text, 0, $this->length, 'utf-8');
        $rest = mb_substr($text, $this->length, null, 'utf-8');

        return $this->internalRenderDropCaps('', $dropCaps, $rest);
    }
    
    /**
     *
     * @param $skip
     * @param $dropCaps
     * @param $rest
     *
     * @return string
     *
     * @internal Should be protected but made public for PHP 5.3 compat
     */
    public function internalRenderDropCaps($skip, $dropCaps, $rest)
    {
        $html = sprintf('%s<span class="dropcaps">%s</span>%s', $skip, $dropCaps, $rest);

        return $html;
    }

}
