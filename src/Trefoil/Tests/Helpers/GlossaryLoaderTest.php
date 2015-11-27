<?php
/*
 * This file is part of the trefoil application.
 *
 * (c) Miguel Angel Gabriel <magabriel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Trefoil\Helpers;

use EasySlugger\Utf8Slugger;

/**
 * Generated by PHPUnit_SkeletonGenerator 1.2.1 on 2013-12-31 at 08:46:21.
 */
class GlossaryLoaderTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var GlossaryLoader
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        // Utf8Slugger cannot be mocked because it is static
        $slugger = new Utf8Slugger();

        $this->object = new GlossaryLoader(__DIR__ . '/fixtures/glossary-with-options.yml', $slugger);
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {

    }

    /**
     * @covers Trefoil\Helpers\GlossaryLoader::load
     * @covers Trefoil\Helpers\GlossaryLoader::isLoaded
     * @covers Trefoil\Helpers\GlossaryLoader::getOptions
     */
    public function testLoadWithOptions()
    {
        $this->object->load(true);

        $this->assertTrue($this->object->isLoaded());

        $options = array(
            'coverage' => 'first',
            'elements' => array("chapter", "prologue")
        );

        $this->assertEquals($options, $this->object->getOptions());
    }

    /**
     * @covers Trefoil\Helpers\GlossaryLoader::load
     * @covers Trefoil\Helpers\GlossaryLoader::isLoaded
     * @covers Trefoil\Helpers\GlossaryLoader::getOptions
     */
    public function testLoadWithoutOptions()
    {
        $this->object->load(false);

        $this->assertTrue($this->object->isLoaded());

        $this->assertEquals(null, $this->object->getOptions());
    }


}
