<?php
/*
 * This file is part of the trefoil application.
 *
 * (c) Miguel Angel Gabriel <magabriel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Trefoil\Util;

/**
 * A (very) simple report creator
 */
class SimpleReport
{
    protected $title;
    protected $subtitle;
    protected $intro = array();
    protected $headers = array();
    protected $columnsWidth = array();
    protected $columnsAlignment = array();
    protected $lines = array();
    protected $summary = array();

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function setSubtitle($subtitle)
    {
        $this->subtitle = $subtitle;
    }

    public function setHeaders(array $headers)
    {
        $this->headers = $headers;

        foreach ($headers as $index => $header) {
            $this->columnsWidth[$index] = 10;
            $this->columnsAlignment[$index] = '';
        }
    }

    public function setColumnsWidth(array $columnsWidth)
    {
        $this->columnsWidth = $columnsWidth;
    }

    public function setColumnsAlignment(array $columnsAlignment)
    {
        $this->columnsAlignment = $columnsAlignment;
    }

    public function addLine($fields = array())
    {
        if (!is_array($fields)) {
            $fields = array($fields);
        }
        $this->lines[] = $fields;
    }

    public function addIntroLine($text)
    {
        $this->intro[] = $text;
    }

    public function addSummaryLine($text)
    {
        $this->summary[] = $text;
    }

    public function getText()
    {
        $text = array();

        $text = array_merge($text, $this->formatTitle());
        $text = array_merge($text, $this->formatIntro());
        $text = array_merge($text, $this->formatHeaders());
        $text = array_merge($text, $this->formatLines());
        $text = array_merge($text, $this->formatSummary());
        $text[] = '';

        return join("\n", $text);
    }

    protected function formatTitle()
    {
        $text = array();

        $text[] = str_repeat('=', 80);

        $text[] = $this->title;
        if ($this->subtitle) {
            $text[] = $this->subtitle;
            str_repeat('-', 80);
        }

        $text[] = str_repeat('=', 80);

        $text[] = '';

        return $text;
    }

    protected function formatHeaders()
    {
        $text = array();

        $line = '';
        foreach ($this->headers as $index => $header) {
            $line .= $this->pad($header, $this->columnsWidth[$index], $this->columnsAlignment[$index]) . ' ';
        }
        $text[] = $line;

        $line = '';
        foreach ($this->columnsWidth as $headerWidth) {
            $line .= str_repeat('-', $headerWidth ? : 10) . ' ';
        }
        $text[] = $line;

        return $text;
    }

    protected function pad($str, $width = 10, $alignment = 'left')
    {
        $padType = STR_PAD_RIGHT;

        if ('right' == $alignment) {
            $padType = STR_PAD_LEFT;
        } elseif ('center' == $alignment) {
            $padType = STR_PAD_BOTH;
        }

        return $this->mb_str_pad($str, $width, ' ', $padType);
    }

    /**
     * @see http://www.php.net/manual/en/ref.mbstring.php#90611
     */
    protected function mb_str_pad($input, $pad_length, $pad_string = '', $pad_style, $encoding = "UTF-8")
    {
        return str_pad(
            $input,
            strlen($input) - mb_strlen($input, $encoding) + $pad_length,
            $pad_string,
            $pad_style
        );
    }

    protected function formatLines()
    {
        $text = array();

        foreach ($this->lines as $lineFields) {
            $lineText = '';
            foreach ($lineFields as $index => $field) {
                $lineText .= $this->pad($field, $this->columnsWidth[$index], $this->columnsAlignment[$index]) . ' ';
            }
            $text[] = $lineText;
        }

        return $text;
    }

    protected function formatIntro()
    {
        if (!$this->intro) {
            return array();
        }

        $text = array();

        foreach ($this->intro as $line) {
            $text[] = ' ' . $line;
        }

        $text[] = '';

        return $text;
    }

    protected function formatSummary()
    {
        if (!$this->summary) {
            return array();
        }

        $text = array();
        $text[] = '';
        $text[] = '    ' . str_repeat('=', 50);

        foreach ($this->summary as $line) {
            $text[] = '    ' . $line;
        }

        $text[] = '    ' . str_repeat('=', 50);

        return $text;
    }
}
