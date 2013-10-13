<?php
namespace Trefoil\Tests\Plugins;

use Trefoil\Tests\BookPublishingTestCase;

class PluginsTest extends BookPublishingTestCase
{
    public function __construct()
    {
        parent::__construct();

        // __DIR__ is the directory containing THIS file
        $this->fixturesDir = __DIR__.'/fixtures/';
    }
}
