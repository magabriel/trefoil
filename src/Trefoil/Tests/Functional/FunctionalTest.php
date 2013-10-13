<?php
namespace Trefoil\Tests\Functional;

use Trefoil\Tests\BookPublishingTestCase;

class FunctionalTest extends BookPublishingTestCase
{
    public function __construct()
    {
        parent::__construct();

        // __DIR__ is the directory containing THIS file
        $this->fixturesDir = __DIR__.'/fixtures/';
    }
}
