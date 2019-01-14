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
 * Preserves certain parts of a text by replacing them with placeholders, so the text
 * can be safely manipulated without inadvertedly replacing one of these parts.
 * Intended use case:
 * 1. Preserve the contents of certain HTML tags.
 * 2. Preserve the values of certain HTML tag attributes.
 * 3. Create individual placeholders for strings.
 * 4. Make the desired changes into the text.
 * 5. Restore the preserved parts.
 */
class TextPreserver
{
    protected $text;

    protected $stringMapper = [];

    public function getText()
    {
        return $this->text;
    }

    /**
     * @param $text
     */
    public function setText($text): void
    {
        $this->text = $text;
    }

    public function preserveMarkdowmCodeBlocks(): void
    {
        $regExp = '/';
        $regExp .= '(?<codeblock>'; // code block capture group
        $regExp .= '(?<fenced>^~~~.*[^(?~~~)]+^~~~)'; // fenced code block
        $regExp .= '|'; // or
        $regExp .= '(?<inline>`[^`\n]+`)'; // inline code block
        $regExp .= ')'; // code block capture group ends
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $this->text = preg_replace_callback($regExp,
            function ($matches) {
                $content = $matches['codeblock'];

                return $this->internalCreatePlacehoder($content, 'code');
            },
                                            $this->text);
    }

    /**
     * @param        $string
     * @param string $prefix
     * @return string
     */
    public function internalCreatePlacehoder($string,
                                             $prefix = 'str'): string
    {
        $placeHolder = '@'.$prefix.'-'.md5($string.count($this->stringMapper)).'@';
        $this->stringMapper[$placeHolder] = $string;

        return $placeHolder;
    }

    /**
     * @param array $tags
     */
    public function preserveHtmlTags($tags = []): void
    {
        if (count($tags) === 0) {
            $tags = ['a', 'pre', 'code'];
        }

        // replace all the contents of the given tags with a placeholder
        foreach ($tags as $tag) {
            $pattern = '/';
            $pattern .= '<%s(?<attrs>[^>]*)>'; // opening tag with optional attributes
            $pattern .= '(?<content>.*)';
            $pattern .= '<\/%s>'; // closing tag
            $pattern .= '/Ums'; // Ungreedy, multiline, dotall
            /** @noinspection PrintfScanfArgumentsInspection */
            $regex = sprintf($pattern, $tag, $tag);

            $this->text = preg_replace_callback($regex,
                function ($matches) use
                (
                    $tag
                ) {
                    $content = $matches['content'];
                    $attrs = $matches['attrs'];
                    $placeHolder = $this->internalCreatePlacehoder($content, 'tag');

                    return sprintf('<%s%s>%s</%s>', $tag, $attrs, $placeHolder, $tag);
                },
                                                $this->text);
        }
    }

    /**
     * @param array $attributes
     */
    public function preserveHtmlTagAttributes($attributes = []): void
    {
        if (count($attributes) === 0) {
            $attributes = [
                'alt',
                'class',
                'data',
                'id',
                'href',
                'name',
                'rel',
                'src',
                'title',
                'value',
            ];
        }
        // no attributes, so use a default list

        // replace all the contents of the attribute with a placeholder
        $regex = sprintf('/(?<attr>%s)="(?<value>.*)"/Ums', implode('|', $attributes));

        $this->text = preg_replace_callback($regex,
            function ($matches) {
                $attr = $matches['attr'];
                $value = $matches['value'];
                $placeHolder = $this->internalCreatePlacehoder($value, 'attr');

                return sprintf('%s="%s"', $attr, $placeHolder);
            },
                                            $this->text);
    }

    /**
     * @return string|string[]|null
     */
    public function restore()
    {
        foreach ($this->stringMapper as $key => $value) {
            $key = str_replace('/', '\/', $key);
            $this->text = preg_replace('/'.$key.'/Ums', $value, $this->text);
        }

        return $this->text;
    }
}
