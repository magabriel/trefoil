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

namespace Trefoil\Util;

/**
 * A (very) simple report creator
 */
class SimpleReport
{
    protected $title;
    protected $subtitle;
    protected $intro = [];
    protected $headers = [];
    protected $columnsWidth = [];
    protected $columnsAlignment = [];
    /**
     * @var string[][]
     */
    protected $lines = [];
    protected $summary = [];

    /**
     * @param $title
     */
    public function setTitle($title): void
    {
        $this->title = $title;
    }

    /**
     * @param $subtitle
     */
    public function setSubtitle($subtitle): void
    {
        $this->subtitle = $subtitle;
    }

    /**
     * @param array $headers
     */
    public function setHeaders(array $headers): void
    {
        $this->headers = $headers;

        foreach ($headers as $index => $header) {
            $this->columnsWidth[$index] = 10;
            $this->columnsAlignment[$index] = '';
        }
    }

    /**
     * @param array $columnsWidth
     */
    public function setColumnsWidth(array $columnsWidth): void
    {
        $this->columnsWidth = $columnsWidth;
    }

    /**
     * @param array $columnsAlignment
     */
    public function setColumnsAlignment(array $columnsAlignment): void
    {
        $this->columnsAlignment = $columnsAlignment;
    }

    /**
     * @param array $fields
     */
    public function addLine($fields = []): void
    {
        if (!is_array($fields)) {
            $fields = [$fields];
        }
        $this->lines[] = $fields;
    }

    /**
     * @param $text
     */
    public function addIntroLine($text): void
    {
        $this->intro[] = $text;
    }

    /**
     * @param $text
     */
    public function addSummaryLine($text): void
    {
        $this->summary[] = $text;
    }

    /**
     * @return string
     */
    public function getText(): string
    {
        $text = [];

        $text = array_merge($text, $this->formatTitle());
        $text = array_merge($text, $this->formatIntro());
        $text = array_merge($text, $this->formatHeaders());
        $text = array_merge($text, $this->formatLines());
        $text = array_merge($text, $this->formatSummary());
        $text[] = '';

        return implode("\n", $text);
    }

    /**
     * @return array
     */
    protected function formatTitle(): array
    {
        $text = [];

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

    /**
     * @return array
     */
    protected function formatIntro(): array
    {
        if (!$this->intro) {
            return [];
        }

        $text = [];

        foreach ($this->intro as $line) {
            $text[] = ' '.$line;
        }

        $text[] = '';

        return $text;
    }

    /**
     * @return array
     */
    protected function formatHeaders(): array
    {
        $text = [];

        $line = '';
        foreach ($this->headers as $index => $header) {
            $line .= $this->pad($header, $this->columnsWidth[$index], $this->columnsAlignment[$index]).' ';
        }
        $text[] = $line;

        $line = '';
        foreach ($this->columnsWidth as $headerWidth) {
            $line .= str_repeat('-', $headerWidth ?: 10).' ';
        }
        $text[] = $line;

        return $text;
    }

    /**
     * @param        $str
     * @param int    $width
     * @param string $alignment
     * @return string
     */
    protected function pad(string $str,
                           int $width = 10,
                           string $alignment = 'left'): string
    {
        $padType = STR_PAD_RIGHT;

        if ('right' === $alignment) {
            $padType = STR_PAD_LEFT;
        } elseif ('center' === $alignment) {
            $padType = STR_PAD_BOTH;
        }

        return $this->mbStrPad($str, $width, $padType, ' ');
    }

    /**
     * @see http://www.php.net/manual/en/ref.mbstring.php#90611
     * @param        $input
     * @param        $pad_length
     * @param        $pad_style
     * @param string $pad_string
     * @param string $encoding
     * @return string
     */
    protected function mbStrPad(string $input,
                                int $pad_length,
                                int $pad_style,
                                string $pad_string = '',
                                string $encoding = 'UTF-8'): string
    {
        return str_pad(
            $input,
            strlen($input) - mb_strlen($input, $encoding) + $pad_length,
            $pad_string,
            $pad_style);
    }

    /**
     * @return array
     */
    protected function formatLines(): array
    {
        $text = [];

        foreach ($this->lines as $lineFields) {
            $lineText = '';
            foreach ($lineFields as $index => $field) {
                $lineText .= $this->pad((string) $field, $this->columnsWidth[$index], $this->columnsAlignment[$index]).' ';
            }
            $text[] = $lineText;
        }

        return $text;
    }

    /**
     * @return array
     */
    protected function formatSummary(): array
    {
        if (!$this->summary) {
            return [];
        }

        $text = [];
        $text[] = '';
        $text[] = '    '.str_repeat('=', 50);

        foreach ($this->summary as $line) {
            $text[] = '    '.$line;
        }

        $text[] = '    '.str_repeat('=', 50);

        return $text;
    }
}
