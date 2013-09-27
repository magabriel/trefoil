<?php
namespace Trefoil\Util;

/**
 * A (very) simple report creator
 */
class SimpleReport
{
    protected $title;
    protected $subtitle;
    protected $headers = array();
    protected $headersWidth = array();
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
    }

    public function setHeadersWidth(array $headersWidth)
    {
        $this->headersWidth = $headersWidth;
    }

    public function addLine(array $fields = array())
    {
        $this->lines[] = $fields;
    }

    public function addSummaryLine($text)
    {
        $this->summary[] = $text;
    }

    public function getText()
    {
        $text = array();

        $text = array_merge($text, $this->formatTitle());
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
            $line.= str_pad($header, $this->headersWidth[$index] ?: 10).' ';
        }
        $text[] = $line;

        $line = '';
        foreach ($this->headersWidth as $headerWidth) {
            $line.= str_repeat('-', $headerWidth ?: 10) .' ';
        }
        $text[] = $line;

        return $text;
    }

    protected function formatLines()
    {
        $text = array();

        foreach ($this->lines as $lineFields) {
            $lineText = '';
            foreach ($lineFields as $index => $field) {
                $lineText.= str_pad($field, $this->headersWidth[$index] ?: 10). ' ';
            }
            $text[] = $lineText;
        }

        return $text;
    }

    protected function formatSummary()
    {
        if (!$this->summary) {
            return array();
        }

        $text = array();
        $text[] = '';
        $text[] = '    '.str_repeat('=', 50);

        foreach ($this->summary as $line) {
            $text[] = '    '. $line;
        }

        $text[] = '    '.str_repeat('=', 50);

        return $text;
    }
}
