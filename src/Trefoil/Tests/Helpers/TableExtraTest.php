<?php
namespace Trefoil\Tests\Helpers;

use Trefoil\Helpers\TableExtra;

class TableExtraTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
    }

    public function tearDown()
    {
    }

    public function testConvertWellFormedTable()
    {
        $input = array(
                '<table>',
                '    <thead>',
                '        <tr>',
                '            <th>Header A</th>',
                '            <th>Header B</th>',
                '            <th></th>', // empty cell -> colspan
                '            <th>Header D</th>',
                '            <th>Header E</th>',
                '            <th>Header F</th>',
                '        </tr>',
                '     </thead>',
                '    <tbody>',
                '        <tr>',
                '            <td>Cell 1A</td>',
                '            <td>Cell 1B</td>',
                '            <td></td>', // empty cell -> colspan
                '            <td>Cell 1D</td>',
                '            <td>Cell 1E</td>',
                '            <td>Cell 1F</td>',
                '        </tr>',
                '        <tr>',
                '            <td>Cell 2A</td>',
                '            <td>Cell 2B</td>',
                '            <td></td>', // empty cell -> colspan
                '            <td>Cell 2D</td>',
                '            <td>&#8221;</td>', // cell with '"' -> rowspan
                '            <td>Cell 2F</td>',
                '        </tr>',
                '     </tbody>',
                '</table>');

        $expected = array(
                '<table>',
                '    <thead>',
                '        <tr>',
                '            <th>Header A</th>',
                '            <th colspan="2">Header B</th>',
                '            <th>Header D</th>',
                '            <th>Header E</th>',
                '            <th>Header F</th>',
                '        </tr>',
                '     </thead>',
                '    <tbody>',
                '        <tr>',
                '            <td>Cell 1A</td>',
                '            <td colspan="2">Cell 1B</td>',
                '            <td>Cell 1D</td>',
                '            <td rowspan="2" style="vertical-align: middle;">Cell 1E</td>',
                '            <td>Cell 1F</td>',
                '        </tr>',
                '        <tr>',
                '            <td>Cell 2A</td>',
                '            <td colspan="2">Cell 2B</td>',
                '            <td>Cell 2D</td>',
                '            <td>Cell 2F</td>',
                '        </tr>',
                '     </tbody>',
                '</table>');


        $tableExtra = new TableExtra();
        $output = $tableExtra->processAllTables(implode("", $input));

        $output = tidy_repair_string($output, array('indent' => true));
        $expected = tidy_repair_string(implode("", $expected), array('indent' => true));

        //print_r($output);

        $this->assertEquals($expected, $output);
    }

    public function testConvertSimpleTable()
    {
        $input = array(
                '<table>',
                '        <tr>',
                '            <th>Header A</th>',
                '            <th>Header B</th>',
                '            <th></th>', // empty cell -> colspan
                '            <th>Header D</th>',
                '            <th>Header E</th>',
                '            <th>Header F</th>',
                '        </tr>',
                '        <tr>',
                '            <td>Cell 1A</td>',
                '            <td>Cell 1B</td>',
                '            <td></td>', // empty cell -> colspan
                '            <td>Cell 1D</td>',
                '            <td>Cell 1E</td>',
                '            <td>Cell 1F</td>',
                '        </tr>',
                '        <tr>',
                '            <td>Cell 2A</td>',
                '            <td>Cell 2B</td>',
                '            <td></td>', // empty cell -> colspan
                '            <td>Cell 2D</td>',
                '            <td>&#8221;</td>', // cell with '"' -> rowspan
                '            <td>Cell 2F</td>',
                '        </tr>',
                '</table>');

        $expected = array(
                '<table>',
                '        <tr>',
                '            <th>Header A</th>',
                '            <th colspan="2">Header B</th>',
                '            <th>Header D</th>',
                '            <th>Header E</th>',
                '            <th>Header F</th>',
                '        </tr>',
                '        <tr>',
                '            <td>Cell 1A</td>',
                '            <td colspan="2">Cell 1B</td>',
                '            <td>Cell 1D</td>',
                '            <td rowspan="2" style="vertical-align: middle;">Cell 1E</td>',
                '            <td>Cell 1F</td>',
                '        </tr>',
                '        <tr>',
                '            <td>Cell 2A</td>',
                '            <td colspan="2">Cell 2B</td>',
                '            <td>Cell 2D</td>',
                '            <td>Cell 2F</td>',
                '        </tr>',
                '</table>');


        $tableExtra = new TableExtra();
        $output = $tableExtra->processAllTables(implode("", $input));

        $output = tidy_repair_string($output, array('indent' => true));
        $expected = tidy_repair_string(implode("", $expected), array('indent' => true));

        //print_r($output);

        $this->assertEquals($expected, $output);
    }

}
