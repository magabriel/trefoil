<?php
namespace Trefoil\Tests\Plugins;

use Trefoil\Tests\BookPublishingAllTestCase;

class PluginsTest extends BookPublishingAllTestCase
{
    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        // __DIR__ is the directory containing THIS file
        $this->fixturesDir = __DIR__ . '/fixtures/';
    }
}
