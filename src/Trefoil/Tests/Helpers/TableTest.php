<?php
declare(strict_types=1);

namespace Trefoil\Tests\Helpers;

use Trefoil\Helpers\Table;
use PHPUnit\Framework\TestCase;

/**
 * Class TableTest
 *
 * @package Trefoil\Tests\Helpers
 */
class TableTest extends TestCase
{

    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }

    public function testNoTable(): void
    {
        $input = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.';

        $table = new Table();
        $table->fromHtml($input);
        
        static::assertTrue($table->isEmpty());
        
        $output = $table->toHtml();
        
        static::assertEmpty($output);
    }
    
    public function testFromHtmlWithSimpleTable(): void
    {
        $input = [
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
            '</table>'
        ];

        $expected = [
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
            '</table>'
        ];
            
        $table = new Table();
        $table->fromHtml(implode('', $input));
        $output = $table->toHtml();

        // make them comparable
        $output = tidy_repair_string($output, ['indent' => true], 'utf8');
        $expected = tidy_repair_string(implode('', $expected), ['indent' => true], 'utf8');

        static::assertEquals($expected, $output);
    }

    public function testFromHtmlWithCompleteTable(): void
    {
        $input = [
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
            '</table>'
        ];

        $expected = $input;

        $table = new Table();
        $table->fromHtml(implode('', $input));
        $output = $table->toHtml();

        // make them comparable
        $output = tidy_repair_string($output, ['indent' => true], 'utf8');
        $expected = tidy_repair_string(implode('', $expected), ['indent' => true], 'utf8');

        static::assertEquals($expected, $output);
    }

    public function testArrayObject(): void
    {
        $input = [
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
            '</table>'
        ];

        $expected = [
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
            '</table>'
        ];
        
        $table = new Table();
        $table->fromHtml(implode('', $input));

        // modify a cell
        $table['tbody'][0][1]['contents'] .= ' modified';
        
        // colspan a cell (remove next also)
        $table['tbody'][1][0]['colspan'] = 2;
        unset($table['tbody'][1][1]);

        $output = $table->toHtml();

        // make them comparable
        $output = tidy_repair_string($output, ['indent' => true], 'utf8');
        $expected = tidy_repair_string(implode('', $expected), ['indent' => true], 'utf8');

        static::assertEquals($expected, $output);
    }
}
