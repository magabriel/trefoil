<?php
namespace Trefoil\Helpers;

/**
 * Add drop caps (HTML markup) to a give text.
 *
 */
class DropCaps
{
    const MODE_LETTER = 'letter';
    const MODE_WORD = 'word';

    protected $text;
    protected $mode = self::MODE_LETTER;
    protected $length = 1;

    public function __construct($text, $mode = self::MODE_LETTER, $length = 1)
    {
        $this->text = $text;
        $this->mode = $mode;
        $this->length = $length;
    }

    public function getOutput()
    {
        return $this->text;
    }

    /**
     * Create drop caps in the paragraph that starts the text (no preceding
     * heading tag)
     */
    public function createForFirstParagraph()
    {
        $regex = '/';
        $regex.= '^\s*<p>(?<ptext>.*)<\/p>';
        $regex.= '/Us'; // Ungreedy, dotall

        $text = preg_replace_callback($regex,
                function ($matches)
                {
                    $ptext = $this->createDropCaps($matches['ptext']);

                    $html = sprintf('<p class="has-dropcaps">%s</p>',
                            $ptext
                    );
                    return $html;
                },
                $this->text);

        $this->text = $text;
    }

    /**
     * Create drop caps in each first paragraph under a heading tag
     * @param array $levels [1...6]
     */
    public function createForHeadings($levels = array(1, 2))
    {
        $regex = '/';
        $regex.= '<h(?<level>[1-6])(?<hrest>.*)>'; // opening heading tag
        $regex.= '(?<hcontent>.*)<\/h\1>'; // closing heading tag (backreference)
        $regex.= '(?<whitespace>\s*)'; // optional whitespace
        $regex.= '<p>(?<ptext>.*)<\/p>'; // 1st paragraph
        $regex.= '/Ums'; // Ungreedy, multiline, dotall

        $text = preg_replace_callback($regex,
                function ($matches) use ($levels)
                {
                    if (!in_array($matches['level'], $levels)) {
                        return $matches[0];
                    }

                    $ptext = $this->createDropCaps($matches['ptext']);

                    $html = sprintf('<h%s%s>%s</h%s>%s<p class="has-dropcaps">%s</p>',
                            $matches['level'],
                            $matches['hrest'],
                            $matches['hcontent'],
                            $matches['level'],
                            $matches['whitespace'],
                            $ptext
                    );
                    return $html;
                },
                $this->text);

        $this->text = $text;
    }

    /**
     * Create drop caps markup for a text.
     *
     * @param string $text
     * @return string
     */
    protected function createDropCaps($text)
    {
        $skip = '';
        $dropCaps = '';
        $rest = '';

        if ('word' == $this->mode) {

            // find all words in the text
            preg_match_all('/(\W*\w+\W+)/Us', $text, $matches);

            // isolate the first "$length" words
            $dropCaps = implode('', array_slice($matches[1], 0, $this->length));
            $rest = implode('', array_slice($matches[1], $this->length));

            return $this->renderDropCaps($skip, $dropCaps, $rest);
        }

        // 'letter'mode

        // look if it starts with an HTML entity
        if (preg_match('/^(&[#[:alnum:]]*;)/U', $text, $matches)) {

            // ignore if it is an space
            if ('&nbsp;' == $matches[1]) {
                return $text;
            }
            // isolate the first "$length" letters but skipping the entity
            $dropCaps = $matches[1] . substr($text, strlen($matches[1]), $this->length);
            $rest = substr($text, strlen($matches[1])+$this->length);

            return $this->renderDropCaps($skip, $dropCaps, $rest);
        }

        // look if it starts with an HTML tag
        if (preg_match('/^<(?<tag>.*)(?<attr>.*)>(?<content>.*)<\/\1>/', $text, $matches)) {

            // an HTML tag
            if (strpos($matches['attr'], 'dropcaps') > 0) {
                // already has a explicit dropcaps markup, do nothing
                return $text;
            }

            // isolate the first "$length" letters but skipping the tag
            $skip = '<'.$matches['tag'].$matches['attr'].'>';
            $dropCapsLength = min($this->length, strlen($matches['content']));

            $dropCaps = substr($text, strlen($skip), $dropCapsLength);
            $rest = substr($text, strlen($skip) + $dropCapsLength);

            return $this->renderDropCaps($skip, $dropCaps, $rest);
        }

        // normal case, isolate the first "$length" letters
        $dropCaps = substr($text, 0, $this->length);
        $rest = substr($text, $this->length);

        return $this->renderDropCaps($skip, $dropCaps, $rest);
    }

    protected function renderDropCaps($skip, $dropCaps, $rest)
    {
        $html = sprintf('%s<span class="dropcaps">%s</span>%s',
                $skip,
                $dropCaps,
                $rest);

        return $html;
    }
}
