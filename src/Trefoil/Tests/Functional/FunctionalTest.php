<?php
/*
 * This file is part of the trefoil application.
 *
 * (c) Miguel Angel Gabriel <magabriel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Trefoil\Tests\Functional;

use Trefoil\Tests\BookPublishingAllTestCase;

class FunctionalTest extends BookPublishingAllTestCase
{
    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        $this->fixturesDir = __DIR__ . '/fixtures/';
    }
}
