<?php

namespace Trefoil\Test\Helpers;

use Trefoil\Helpers\Table;

class TableTest extends \PHPUnit_Framework_TestCase
{

    public function setUp()
    {
    }

    public function tearDown()
    {
    }

    public function testNoTable()
    {
        $input = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.';

        $table = new Table();
        $table->fromHtml($input);
        
        $this->assertTrue($table->isEmpty());
        
        $output = $table->toHtml();
        
        $this->assertEmpty($output);
    }
    
    public function testFromHtmlWithSimpleTable()
    {
        $input = array(
            '<table>',
            '        <tr>',
            '            <th>Header A</th>',
            '            <th>Header B</th>',
            '            <th>Header C</th>', 
            '        </tr>',
            '        <tr>',
            '            <td>Cell 1A</td>',
            '            <td>Cell 1B</td>',
            '            <td>Cell 1C</td>', 
            '        </tr>',
            '        <tr>',
            '            <td>Cell 2A</td>',
            '            <td>Cell 2B</td>',
            '            <td>Cell 2C</td>', 
            '        </tr>',
            '</table>');

        $expected = array(
            '<table>',
            '    <tbody>',
            '        <tr>',
            '            <th>Header A</th>',
            '            <th>Header B</th>',
            '            <th>Header C</th>',
            '        </tr>',
            '        <tr>',
            '            <td>Cell 1A</td>',
            '            <td>Cell 1B</td>',
            '            <td>Cell 1C</td>',
            '        </tr>',
            '        <tr>',
            '            <td>Cell 2A</td>',
            '            <td>Cell 2B</td>',
            '            <td>Cell 2C</td>',
            '        </tr>',
            '    </tbody>',
            '</table>');
            
        $table = new Table();
        $table->fromHtml(implode('', $input));
        $output = $table->toHtml();

        // make them comparable
        $output = tidy_repair_string($output, array('indent' => true), 'utf8');
        $expected = tidy_repair_string(implode("", $expected), array('indent' => true), 'utf8');

        $this->assertEquals($expected, $output);
    }

    public function testFromHtmlWithCompleteTable()
    {
        $input = array(
            '<table>',
            '    <thead>',
            '        <tr>',
            '            <th>Header A</th>',
            '            <th>Header B</th>',
            '            <th>Header C</th>',
            '        </tr>',
            '    </thead>',
            '    <tbody>',
            '        <tr>',
            '            <td>Cell 1A</td>',
            '            <td>Cell 1B</td>',
            '            <td>Cell 1C</td>',
            '        </tr>',
            '        <tr>',
            '            <td>Cell 2A</td>',
            '            <td>Cell 2B</td>',
            '            <td>Cell 2C</td>',
            '        </tr>',
            '    </tbody>',
            '</table>');

        $expected = $input;

        $table = new Table();
        $table->fromHtml(implode('', $input));
        $output = $table->toHtml();

        // make them comparable
        $output = tidy_repair_string($output, array('indent' => true), 'utf8');
        $expected = tidy_repair_string(implode("", $expected), array('indent' => true), 'utf8');

        $this->assertEquals($expected, $output);
    }

    public function testArrayObject()
    {
        $input = array(
            '<table>',
            '    <thead>',
            '        <tr>',
            '            <th>Header A</th>',
            '            <th>Header B</th>',
            '            <th>Header C</th>',
            '        </tr>',
            '    </thead>',
            '    <tbody>',
            '        <tr>',
            '            <td>Cell 1A</td>',
            '            <td>Cell 1B</td>',
            '            <td>Cell 1C</td>',
            '        </tr>',
            '        <tr>',
            '            <td>Cell 2A</td>',
            '            <td>Cell 2B</td>',
            '            <td>Cell 2C</td>',
            '        </tr>',
            '    </tbody>',
            '</table>');

        $expected = array(
            '<table>',
            '    <thead>',
            '        <tr>',
            '            <th>Header A</th>',
            '            <th>Header B</th>',
            '            <th>Header C</th>',
            '        </tr>',
            '    </thead>',
            '    <tbody>',
            '        <tr>',
            '            <td>Cell 1A</td>',
            '            <td>Cell 1B modified</td>',
            '            <td>Cell 1C</td>',
            '        </tr>',
            '        <tr>',
            '            <td colspan="2">Cell 2A</td>',
            '            <td>Cell 2C</td>',
            '        </tr>',
            '    </tbody>',
            '</table>');
        
        $table = new Table();
        $table->fromHtml(implode('', $input));

        // modify a cell
        $table['tbody'][0][1]['contents'] .= ' modified';
        
        // colspan a cell (remove next also)
        $table['tbody'][1][0]['colspan'] = 2;
        unset($table['tbody'][1][1]);

        $output = $table->toHtml();

        // make them comparable
        $output = tidy_repair_string($output, array('indent' => true), 'utf8');
        $expected = tidy_repair_string(implode("", $expected), array('indent' => true), 'utf8');

        $this->assertEquals($expected, $output);
    }
}
