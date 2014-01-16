<?php
namespace Trefoil\Helpers;

/**
 * Preserves certain parts of a text by replacing them with placeholders, so the text
 * can be safely manipulated without inadvertedly replacing one of these parts.
 *
 * Intended use case:
 * 1. Preserve the contents of certain HTML tags.
 * 2. Preserve the values of certain HTML tag attributes.
 * 3. Create individual placeholders for strings.
 * 4. Make the desired changes into the text.
 * 5. Restore the preserved parts.
 *
 */
class TextPreserver
{
    protected $text;

    protected $stringMapper = array();

    public function setText($text)
    {
        $this->text = $text;
    }

    public function getText()
    {
        return $this->text;
    }

    public function preserveHtmlTags($tags = array())
    {
        // replace all the contents of the tags with a placeholder
        $regex = sprintf('/<(?<tag>(%s)[> ].*)>(?<content>.*)</Ums', implode('|', $tags));

        $me = $this;
        $this->text = preg_replace_callback(
            $regex,
            function ($matches) use ($me) {
                $tag = $matches['tag'];
                $content = $matches['content'];

                $placeHolder = $me->createPlacehoder($content, 'tag');

                return sprintf('<%s>%s<', $tag, $placeHolder);

            },
            $this->text
        );

    }

    public function preserveHtmlTagAttributes($attributes = array())
    {
        // replace all the contents of the attribute with a placeholder
        $regex = sprintf('/(?<attr>%s)="(?<value>.*)"/Ums', implode('|', $attributes));

        $me = $this;
        $this->text = preg_replace_callback(
            $regex,
            function ($matches) use ($me) {
                $attr = $matches['attr'];
                $value = $matches['value'];

                $placeHolder = $me->createPlacehoder($value, 'attr');

                return sprintf('%s="%s"', $attr, $placeHolder);
            },
            $this->text
        );
    }

    public function createPlacehoder($string, $prefix = 'str')
    {
        $placeHolder = '@' . $prefix . '-' . md5($string . count($this->stringMapper)) . '@';
        $this->stringMapper[$placeHolder] = $string;

        return $placeHolder;
    }

    public function restore()
    {
        foreach ($this->stringMapper as $key => $value) {
            $key = str_replace('/', '\/', $key);
            $this->text = preg_replace('/' . $key . '/Ums', $value, $this->text);
        }

        return $this->text;
    }
}
