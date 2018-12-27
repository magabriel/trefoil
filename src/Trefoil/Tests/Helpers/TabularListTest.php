<?php

namespace Trefoil\Test\Helpers;

use Trefoil\Helpers\TabularList;

class TabularListTest extends \PHPUnit_Framework_TestCase
{

    public function setUp()
    {
    }

    public function tearDown()
    {
    }

    public function testNoList()
    {
        $list = new TabularList();
        $list->fromHtml('');
        $output = $list->toHtmlTable();

        $this->assertEmpty($output, "Empty list");
    }

    public function testSimpleListDefault()
    {
        $input = __DIR__ . '/fixtures/tabularlist-test-simple-list.html';
        $expected = __DIR__ . '/fixtures/tabularlist-test-simple-list-expected.html';

        $this->executeTestSimpleList($input, $expected, null, "Simple list default");
    }

    public function testSimpleList0Categories()
    {
        $input = __DIR__ . '/fixtures/tabularlist-test-simple-list.html';
        $expected = __DIR__ . '/fixtures/tabularlist-test-simple-list-expected-0-categories.html';

        $this->executeTestSimpleList($input, $expected, 0, "Simple list 0 categories");
    }

    public function testSimpleList2Categories()
    {
        $input = __DIR__ . '/fixtures/tabularlist-test-simple-list.html';
        $expected = __DIR__ . '/fixtures/tabularlist-test-simple-list-expected-2-categories.html';

        $this->executeTestSimpleList($input, $expected, 2, "Simple list 2 categories");
    }

    public function testSimpleListMultiattribute()
    {
        $input = __DIR__ . '/fixtures/tabularlist-test-simple-list-multiattribute.html';
        $expected = __DIR__ . '/fixtures/tabularlist-test-simple-list-multiattribute-expected.html';

        $this->executeTestSimpleList($input, $expected, 5, "Simple list multiattribute");
    }

    public function testRowspanListDefault()
    {
        $input = __DIR__ . '/fixtures/tabularlist-test-rowspan-list.html';
        $expected = __DIR__ . '/fixtures/tabularlist-test-rowspan-list-expected.html';

        $this->executeTestSimpleList($input, $expected, null, "Rowspan list default");
    }

    public function testRowspanList0Categories()
    {
        $input = __DIR__ . '/fixtures/tabularlist-test-rowspan-list.html';
        $expected = __DIR__ . '/fixtures/tabularlist-test-rowspan-list-expected-0-categories.html';

        $this->executeTestSimpleList($input, $expected, 0,"Rowspan list 0 categories");
    }

    public function testRowspanList1Category()
    {
        $input = __DIR__ . '/fixtures/tabularlist-test-rowspan-list.html';
        $expected = __DIR__ . '/fixtures/tabularlist-test-rowspan-list-expected-1-categories.html';

        $this->executeTestSimpleList($input, $expected, 1, "Rowspan list 1 category");
    }

    public function testRowspanList2Category()
    {
        $input = __DIR__ . '/fixtures/tabularlist-test-rowspan-list.html';
        $expected = __DIR__ . '/fixtures/tabularlist-test-rowspan-list-expected-2-categories.html';

        $this->executeTestSimpleList($input, $expected, 2, "Rowspan list 2 categories");
    }

    public function testRowspanListMultiattribute()
    {
        $input = __DIR__ . '/fixtures/tabularlist-test-rowspan-list-multiattribute.html';
        $expected = __DIR__ . '/fixtures/tabularlist-test-rowspan-list-multiattribute-expected.html';

        $this->executeTestSimpleList($input, $expected, 5, "Rowspan list multiattribute");
    }

    public function testRowspanListMultiattributeWithCategoriesGreaterThanDeep()
    {
        $input = __DIR__ . '/fixtures/tabularlist-test-rowspan-list-multiattribute.html';
        $expected = __DIR__ . '/fixtures/tabularlist-test-rowspan-list-multiattribute-expected.html';

        $this->executeTestSimpleList($input, $expected, 4, "Rowspan list multiattribute greater than deep");
    }

    protected function executeTestSimpleList($inputFile, $expectedFile, $numCategories = null, $message = "")
    {
        $input = file_get_contents($inputFile);
        $expected = file_get_contents($expectedFile);

        $list = new TabularList();
        $list->fromHtml($input, $numCategories);
        $output = $list->toHtmlTable();

        // add style to make it easy to visualization
        $output2 = $input . $output;
        $output2 .= "<style>";
        $output2 .= "td { border: 1px solid black; }";
        $output2 .= "th { background: #bbb; border: 1px solid blue;}";
        $output2 .= "</style>";
        $output2 = tidy_repair_string($output2, array('indent' => true), 'utf8');
        // put expected in a temp file to make it easy to examine
        file_put_contents('/tmp/' . basename($expectedFile) . '-output.html', $output2);

        // make them comparable
        $output = tidy_repair_string($output, array('indent' => true), 'utf8');
        $expected = tidy_repair_string($expected, array('indent' => true), 'utf8');
        $this->assertEquals($expected, $output, $message);
    }
}
