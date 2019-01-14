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
namespace Trefoil\Tests\Plugins;

use Trefoil\Tests\BookPublishingAllTestCase;

class PluginsTest extends BookPublishingAllTestCase
{
    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        $this->fixturesDir = __DIR__ . '/fixtures/';
    }
}
