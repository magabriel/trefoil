<?php
declare(strict_types=1);

namespace Trefoil\Helpers;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Class TabularList
 *
 * This class manages a TabularList object, which represents an HTML list that can be alternatively shown as an HTML
 * table without loosing information (and vice versa).
 *
 * Its intended use is providing an adequate representation of tables in ebooks (where wide tables are not appropriate)
 * while maintaining the table as-is for wider formats, like PDF.
 *
 * Example source list:
 * <ul>
 *  <li><b>Category 1:</b> Value 1 for category 1
 *      <ul>
 *          <li><b>Attribute 1:</b> Value for attribute 1.1</li>
 *          <li><b>Attribute 2:</b> Value for attribute 1.2</li>
 *      </ul>
 *  </li>
 *  <li><b>Category 1:</b> Value 2 for category 1
 *      <ul>
 *          <li><b>Attribute 1:</b> Value for attribute 2.1</li>
 *          <li><b>Attribute 2:</b> Value for attribute 2.2</li>
 *      </ul>
 *  </li>
 * </ul>
 *
 * First level <li> are categories while other levels' <li>s are considered attributes. The amount of categories
 * is controlled by an argument at table rendering time.
 *
 * Example rendered table:
 * <table>
 *  <thead><tr><th>Category 1</th><th>Attribute 1</th><th>Attribute 2</th></tr></thead>
 *  <tbody>
 *      <tr><td>Value 1 for category 1</td><td>Value for attribute 1.1</td><td>Value for attribute 1.2</td></tr>
 *      <tr><td>Value 2 for category 1</td><td>Value for attribute 2.1</td><td>Value for attribute 2.2</td></tr>
 *  </tbody>
 *
 * @package Trefoil\Helpers
 */
class TabularList
{
    /**
     * @var Table
     */
    protected $table;

    /**
     * Number of list levels to be treated as categories.
     *
     * @var int|null
     */
    protected $numCategories;

    /**
     * Deep of the list (max number of levels).
     *
     * @var int
     */
    protected $deep = 0;

    /**
     * If a zero categories table needs headings.
     *
     * @var bool
     */
    protected $needsHeadingsForZeroCategories = false;

    public function __construct()
    {
        $this->table = new Table();
    }

    /**
     * @param string   $htmlList      string with HTML <ul> tag
     * @param int|null $numCategories number of list levels to be represented as categories (columns)
     *                                in the table (null=default)
     */
    public function fromHtml($htmlList, $numCategories = null): void
    {
        $this->numCategories = $numCategories;

        $this->parseHtmlList($htmlList);
    }

    /**
     * @return string List rendered to HTML table
     */
    public function toHtmlTable(): string
    {
        return $this->table->toHtml();
    }

    /**
     * @param      string $htmlList list as html (<ul>...</ul>)
     */
    protected function parseHtmlList($htmlList): void
    {
        $htmlList = $this->prepareHtmlList($htmlList);

        $crawler = new Crawler();
        $crawler->addHtmlContent($htmlList);
        $crawler = $crawler->filter('ul');

        // not an <ul> list in the input 
        if ($crawler->count() === 0) {
            return;
        }

        $listAsArray = $this->parseUl($crawler);

        // extract the numcategories from the <ul> class, if present
        if ($this->numCategories === null) {
            $this->extractNumCategories($crawler);
        }

        // find max deep of tree and set default numcategories
        $this->findDeep($listAsArray);
        if ($this->numCategories === null) {
            $this->numCategories = $this->deep + 1;
        }

        if ($this->numCategories === 1) {
            $this->numCategories = 0;
            $this->needsHeadingsForZeroCategories = true;
        }

        $this->prepareTableDefinition($listAsArray);
    }

    /**
     * Prepare the HTML source list, ensuring every <li>..</li> has a <p>..</p> surrounding the contents.
     *
     * @param $htmlList
     *
     * @return string
     */
    protected function prepareHtmlList($htmlList): string
    {
        $htmlList = preg_replace('/<li>(?!<p>)/U', '<li><p>', $htmlList);
        $htmlList = preg_replace('/(?<!<\/p>)<\/li>/U', '</p></li>', $htmlList);

        $htmlList = preg_replace('/<b>/U', '<strong>', $htmlList);
        $htmlList = preg_replace('/<\/b>/U', '</strong>', $htmlList);

        return $htmlList;
    }

    /**
     * @param Crawler $ulNode
     */
    protected function extractNumCategories(Crawler $ulNode): void
    {
        $ulClasses = $ulNode->attr('class') ?: '';
        $matches = [];

        foreach (explode(' ', $ulClasses) as $class) {
            if (preg_match('/tabularlist-(\d*)$/', $class, $matches)) {
                $this->numCategories = (int)$matches[1];
            }
        }
    }

    /**
     * Parses a recursive <ul> list to an array.
     *
     * @param Crawler $ulNode
     *
     * @return array parsed <ul> list
     */
    protected function parseUl(Crawler $ulNode): array
    {
        $output = [];

        $ulNode->children()->each(
            function (Crawler $liNode) use (&$output) {

                $cell = [];
                $cellText = '';

                $liNode->children()->each(
                    function (Crawler $liChildrenNode) use (&$cell, &$cellText) {

                        switch ($liChildrenNode->nodeName()) {
                            case 'p':
                                // append paragraphs to form the cell text
                                $cellText .= '<p>' . $liChildrenNode->html() . '</p>';
                                break;

                            case 'ol':
                                // ordered lists are treated as raw HTML, appending it to the cell text
                                $cellText .= '<ol>' . $liChildrenNode->html() . '</ol>';
                                break;

                            case 'ul' :
                                // get the collected text into the cell text
                                $cell['text'] = $cellText;
                                $cellText = '';

                                // an unordered list is a new list level
                                $cell['list'] = $this->parseUl($liChildrenNode);
                                break;

                            default:
                                // other tags are ignored
                                break;
                        }
                    }
                );

                // uncollected text
                if ($cellText) {
                    $cell['text'] = $cellText;
                }

                if (!empty($cell)) {
                    $output[] = $cell;
                }
            }
        );

        return $output;
    }

    /**
     * @param array $listAsArray the list definition as an array
     */
    protected function prepareTableDefinition(array $listAsArray): void
    {
        // make the table body from the list
        $this->processListToTable($listAsArray);

        // ensure we have headings
        /** @var array[] $row */
        foreach ($this->table['tbody'] as $row) {
            $colIndex = 0;
            /** @var array $row */
            foreach ($row as $cell) {
                // detected heading for cell is in the extra
                $heading = $cell['extra'] ?? '';
                $existingHeading = $this->table->getHeadingCell($colIndex);
                if ($heading && !$existingHeading) {
                    $this->table->addHeadingCell($heading, $colIndex);
                }

                $colIndex++;
            }
        }
    }

    protected function processListToTable($list, $level = 0): void
    {
        /** @var array $list */
        foreach ($list as $listNodeIndex => $listNode) {

            // if this is a 0-level node, add a new row
            $lastRow = $this->table->getBodyRowsCount() - 1;
            if ($level === 0 && $this->table->getBodyCellsCount($lastRow)) {
                $this->table->addBodyRow();
            }

            // extract heading from cell
            $node = $this->extractNodeText($listNode['text']);

            // add new row if needed to mantain the right table flow
            $this->createNewRowIfNeeded($level, $listNodeIndex);

            // start processing the node text, which each node will have
            $cellContents = $node['text'];

            // create new cell from node
            $where = $this->createCell($cellContents, $node);

            // process the node sublist, if present 
            if (isset($listNode['list'])) {
                if ($level + 1 < $this->numCategories) {
                    $this->processListToTable($listNode['list'], $level + 1);
                } else {
                    $cellContents .= $this->listToText($listNode['list']);
                    $this->table->addBodyCell($cellContents, (int)$where['row'], (int)$where['column']);
                }
            }
        }
    }

    /**
     * Extract node text and heading from the original node text.
     *
     * @param $text
     *
     * @return array of 'text' and 'heading' components
     */
    protected function extractNodeText($text): array
    {
        $node = [
            'text' => $text,
            'heading' => '',
        ];

        // if we don't use categories then we don't need headings
        if ($this->numCategories === 0 && !$this->needsHeadingsForZeroCategories) {
            return $node;
        }

        // extract heading
        $matches = [];

        if (preg_match(
            '/^(?<all><p><strong>(?<heading>.*)(?:(?::<\/strong>)|(?:<\/strong>:)))/U',
            $node['text'],
            $matches
        )) {
            $node['heading'] = $matches['heading'];
            $node['text'] = trim(substr($node['text'], strlen($matches['all'])));
            // remove final paragraph closing tag
            $node['text'] = str_replace('</p>', '', $node['text']);
        }

        return $node;
    }

    /**
     * Recursively collect all text in the list and its descendant nodes.
     *
     * @param $list
     *
     * @return string
     */
    protected function listToText($list): string
    {
        $text = '';

        /** @var array $list */
        foreach ($list as $listNode) {

            if (isset($listNode['text'])) {
                $text .= '<li>' . $listNode['text'] . '</li>';
            }

            if (isset($listNode['list'])) {
                $text .= '<li style="list-style: none; display: inline">' . $this->listToText($listNode['list']) . '</li>';
            }
        }

        $text = '<ul>' . $text . '</ul>';

        return $text;
    }

    protected function findDeep(array $list, $countLevels = 1): void
    {
        $this->deep = max($this->deep, $countLevels);

        foreach ($list as $listNode) {
            if (isset($listNode['list'])) {
                $this->findDeep($listNode['list'], $countLevels + 1);
            }
        }
    }

    /**
     * Create a new row if the table needs it (depending on level, deep).
     *
     * @param $level
     * @param $listNodeIndex
     */
    protected function createNewRowIfNeeded($level, $listNodeIndex): void
    {
        $needsRowspan = ($level > 0 && $listNodeIndex > 0);
        $needsNewRow = $needsRowspan || ($level === 0 && $listNodeIndex === 0);

        // if we need more categories than the list's deep it means that
        // the last level is a list and we are going to represent it as additional categories.
        if ($this->numCategories > $this->deep) {
            // if we are at the latest level (the deepest list), we don't want rowspan or a new row
            if ($level === $this->deep - 1) {
                $needsRowspan = false;
                $needsNewRow = false;
            }
        }

        if ($needsNewRow) {
            $this->table->addBodyRow();
        }

        // add empty cells as rowspanned (=> with quote only)
        if ($needsRowspan) {
            for ($i = 0; $i < $level; $i++) {
                $this->table->addBodyCell("'");
            }
        }
    }

    /**
     * @param $cellContents
     * @param $node
     *
     * @return array
     */
    protected function createCell($cellContents, $node): array
    {
        $where = $this->table->addBodyCell($cellContents);

        // save candidate heading in extra
        if (!$this->table->getBodyCellExtra($where['row'], $where['column'])) {
            $this->table->setBodyCellExtra($node['heading'], (int)$where['row'], (int)$where['column']);

            return $where;
        }

        return $where;
    }

}