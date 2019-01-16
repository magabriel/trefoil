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

namespace Trefoil\Helpers;

/**
 * Add drop caps (HTML markup) to a given HTML text:
 *     <p class="has-dropcaps"><span class="dropcaps">T</span>his has dropcaps.</p>
 * Features:
 * 1.- Add automatic dropcaps to the first paragraph in the text.
 * 2.- Add automatic dropcaps to the first paragraph after each heading of selected levels.
 * 3.- Process Markdown-like markup for dropcaps:
 *      [[T]]his text has first-letter dropcaps.
 *      [[But]] this text has first-word dropcaps.
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
    public const MODE_LETTER = 'letter';

    /**
     * The first "length" words are transformed into drop caps
     *
     * @var string
     */
    public const MODE_WORD = 'word';

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
    public function __construct($text,
                                $mode = self::MODE_LETTER,
                                $length = 1)
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
    public function getOutput(): string
    {
        return $this->text;
    }

    /**
     * Create drop caps for Markdown-style markup, as in "[[He]]llo"
     */
    public function createForMarkdownStyleMarkup(): void
    {
        $regex = '/';
        $regex .= '\s*<p>\[\[(?<first>.*)\]\](?<rest>.*)<\/p>';
        $regex .= '/Ums'; // Ungreedy, multiline, dotall

        $callback = function ($matches) {
            $ptext = $this->internalRenderDropCaps('', $matches['first'], $matches['rest']);

            return sprintf('<p class="has-dropcaps">%s</p>', $ptext);
        };

        $this->text = preg_replace_callback($regex, $callback, $this->text);
    }

    /**
     * @param $skip
     * @param $dropCaps
     * @param $rest
     * @return string
     */
    protected function internalRenderDropCaps(string $skip,
                                              string $dropCaps,
                                              string $rest): string
    {
        return sprintf('%s<span class="dropcaps">%s</span>%s', $skip, $dropCaps, $rest);
    }

    /**
     * Create drop caps in the paragraph that starts the text (no preceding
     * heading tag)
     */
    public function createForFirstParagraph(): void
    {
        $regex = '/';
        $regex .= '^\s*<p>(?<ptext>.*)<\/p>';
        $regex .= '/Us'; // Ungreedy, dotall

        $callback = function ($matches) {
            $ptext = $this->internalCreateDropCaps($matches['ptext']);

            return sprintf('<p class="has-dropcaps">%s</p>', $ptext);
        };

        $this->text = preg_replace_callback($regex, $callback, $this->text);
    }

    /**
     * Create drop caps markup for a text.
     *
     * @param string $text
     * @return string|null
     */
    protected function internalCreateDropCaps($text): ?string
    {
        // try each one of the possibilities to add drop caps

        $done = $this->internalTryWordModeDropCaps($text);
        if ($done) {
            return $done;
        }

        $done = $this->internalTryLetterModeAbbrHtmlTag($text);
        if ($done) {
            return $done;
        }

        $done = $this->internalTryLetterModeNormalHtmlTag($text);
        if ($done) {
            return $done;
        }

        $done = $this->internalTryLetterModeHtmlEntity($text);
        if ($done) {
            return $done;
        }

        $done = $this->internalTryLetterModeNonWordChar($text);
        if ($done) {
            return $done;
        }

        // default case, just isolate the first "$length" letters

        $enc = mb_internal_encoding();
        mb_internal_encoding('UTF-8');

        $dropCaps = mb_substr($text, 0, $this->length);
        $rest = mb_substr($text, mb_strlen($dropCaps));

        mb_internal_encoding($enc);

        return $this->internalRenderDropCaps('', $dropCaps, $rest);
    }

    /**
     * Word mode drop caps
     *
     * @param $text
     * @return null|string
     */
    protected function internalTryWordModeDropCaps($text): ?string
    {
        if ('word' === $this->mode) {

            // find all words in the text
            preg_match_all('/(\W*\w+\W+)/Us', $text, $matches);

            // isolate the first "$length" words
            $dropCaps = implode('', array_slice($matches[1], 0, $this->length));
            $rest = implode('', array_slice($matches[1], $this->length));

            return $this->internalRenderDropCaps('', $dropCaps, $rest);
        }

        return null;
    }

    /**
     * Look if it starts with an empty abbreviated HTML tag (like '<br />')
     *
     * @param $text
     * @return null|string
     */
    protected function internalTryLetterModeAbbrHtmlTag($text): ?string
    {
        $regex = '/^(?<tag><.*\/>)(?<rest>.*)$/Us';

        if (preg_match($regex, $text, $matches)) {

            // isolate the first "$length" letters but skipping the tag
            $enc = mb_internal_encoding();
            mb_internal_encoding('UTF-8');

            $dropCaps = mb_substr($matches['rest'], 0, $this->length);
            $rest = mb_substr($matches['rest'], mb_strlen($dropCaps));

            mb_internal_encoding($enc);

            // prepend again the tag *before* the markup
            return $this->internalRenderDropCaps($matches['tag'], $dropCaps, $rest);
        }

        return null;
    }

    /**
     * Look if it starts with a normal HTML tag (like '<span>...</span>')
     *
     * @param $text
     * @return null|string
     */
    protected function internalTryLetterModeNormalHtmlTag($text): ?string
    {
        $regex = '/^(?<skip><(?<tag>.*) *(?<attr>.*)>)(?<content>.*)<\/\k<tag>>(?<rest>.*)$/Uus';

        if (preg_match($regex, $text, $matches)) {
            if (strpos($matches['attr'], 'dropcaps') > 0) {
                // already has a explicit dropcaps markup, do nothing
                return $text;
            }

            // isolate the first "$length" letters but skipping the tag
            $enc = mb_internal_encoding();
            mb_internal_encoding('UTF-8');

            $skip = $matches['skip'];
            $dropCapsLength = min($this->length, mb_strlen($matches['content']));

            $dropCaps = mb_substr($matches['content'], 0, $dropCapsLength);
            $contentRest = mb_substr($matches['content'], mb_strlen($dropCaps));
            $rest = $contentRest.sprintf('</%s>', $matches['tag']).$matches['rest'];

            mb_internal_encoding($enc);

            return $this->internalRenderDropCaps($skip, $dropCaps, $rest);
        }

        return null;
    }

    /**
     * Look if it starts with an HTML entity like '&raquo;'
     *
     * @param $text
     * @return null|string
     */
    protected function internalTryLetterModeHtmlEntity($text): ?string
    {
        $regex = '/^(?<entity>&[#[:alnum:]]*;)(?<rest>.*)$/Uus';

        if (preg_match($regex, $text, $matches)) {

            // ignore if it is an space
            if ('&nbsp;' === $matches['entity']) {
                return $text;
            }

            // isolate the first "$length" letters but skipping the entity

            $enc = mb_internal_encoding();
            mb_internal_encoding('UTF-8');

            $dropCaps = mb_substr($matches['rest'], 0, $this->length);
            $rest = mb_substr($matches['rest'], mb_strlen($dropCaps));

            mb_internal_encoding($enc);

            // prepend again the entity
            $dropCaps = $matches['entity'].$dropCaps;

            return $this->internalRenderDropCaps('', $dropCaps, $rest);
        }

        return null;
    }

    /**
     * Look if it starts with a non-word character(s) like opening double quote
     *
     * @param $text
     * @return null|string
     */
    protected function internalTryLetterModeNonWordChar($text): ?string
    {
        $regex = '/^(?<nonword>\W+)(?<rest>.*)$/Uus';

        if (preg_match($regex, $text, $matches)) {

            // isolate the first "$length" letters but skipping the non-word char(s)

            $enc = mb_internal_encoding();
            mb_internal_encoding('UTF-8');

            $dropCaps = mb_substr($matches['rest'], 0, $this->length);
            $rest = mb_substr($matches['rest'], mb_strlen($dropCaps));

            mb_internal_encoding($enc);

            // prepend again the non-word char(s)
            $dropCaps = $matches['nonword'].$dropCaps;

            return $this->internalRenderDropCaps('', $dropCaps, $rest);
        }

        return null;
    }

    /**
     * Create drop caps in each first paragraph under a heading tag
     *
     * @param array $levels [1...6]
     */
    public function createForHeadings($levels = [1, 2]): void
    {
        $regex = '/';
        $regex .= '<h(?<level>[1-6])(?<hrest>.*)>'; // opening heading tag
        $regex .= '(?<hcontent>.*)<\/h\1>'; // closing heading tag (backreference)
        $regex .= '(?<whitespace>\s*)'; // optional whitespace
        $regex .= '<p>(?<ptext>.*)<\/p>'; // 1st paragraph
        $regex .= '/Ums'; // Ungreedy, multiline, dotall

        $callback = function ($matches) use
        (
            $levels
        ) {
            if (!in_array($matches['level'], $levels, false)) {
                return $matches[0];
            }

            $ptext = $this->internalCreateDropCaps($matches['ptext']);

            $html = sprintf('<h%s%s>%s</h%s>%s<p class="has-dropcaps">%s</p>',
                            $matches['level'],
                            $matches['hrest'],
                            $matches['hcontent'],
                            $matches['level'],
                            $matches['whitespace'],
                            $ptext);

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
     */
    public function processManualMarkup(): void
    {
        $regex = '/';
        $regex .= '<p><span.*class="dropcaps">(?<dropcapstext>.*)<\/span>(?<ptext>.*)<\/p>';
        $regex .= '/Ums'; // Ungreedy, multiline, dotall

        $callback = function ($matches) {
            $html = sprintf('<p class="has-dropcaps"><span class="dropcaps">%s</span>%s</p>',
                            $matches['dropcapstext'],
                            $matches['ptext']);

            return $html;
        };

        $text = preg_replace_callback($regex, $callback, $this->text);
        $this->text = $text;
    }

}
