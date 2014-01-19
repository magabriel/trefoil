<?php
namespace Trefoil\Tests\Functional;

use Trefoil\Tests\BookPublishingAllTestCase;

class FunctionalTest extends BookPublishingAllTestCase
{
    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        // __DIR__ is the directory containing THIS file
        $this->fixturesDir = __DIR__ . '/fixtures/';
    }
}
