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
namespace Trefoil\Tests\Helpers;

use Trefoil\Helpers\TableExtra;
use PHPUnit\Framework\TestCase;

/**
 * Class TableExtraTest
 *
 * @package Trefoil\Tests\Helpers
 */
class TableExtraTest extends TestCase
{

    public function testConvertWellFormedTable(): void
    {
        $input = [
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
            '</table>'
        ];

        $expected = [
            '<table>',
            '    <thead>',
            '        <tr>',
            '            <th>Header A</th>',
            '            <th colspan="2">Header B</th>',
            '            <th>Header D</th>',
            '            <th>Header E</th>',
            '            <th>Header F</th>',
            '        </tr>',
            '    </thead>',
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
            '</table>'
        ];


        $tableExtra = new TableExtra();
        $output = $tableExtra->processAllTables(implode('', $input));

        $output = tidy_repair_string($output, ['indent' => true], 'utf8');
        $expected = tidy_repair_string(implode('', $expected), ['indent' => true], 'utf8');

        //print_r($output);

        static::assertEquals($expected, $output);
    }

    public function testConvertSimpleTable(): void
    {
        $input = [
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
            '</table>'
        ];

        $expected = [
            '<table>',
            '    <tbody>',
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
            '    <tbody>',
            '</table>'
        ];

        $tableExtra = new TableExtra();
        $output = $tableExtra->processAllTables(implode('', $input));

        $output = tidy_repair_string($output, ['indent' => true], 'utf8');
        $expected = tidy_repair_string(implode('', $expected), ['indent' => true], 'utf8');

        static::assertEquals($expected, $output);
    }

}
