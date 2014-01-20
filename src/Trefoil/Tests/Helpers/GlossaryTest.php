<?php
/*
 * This file is part of the trefoil application.
 *
 * (c) Miguel Angel Gabriel <magabriel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Trefoil\Tests\Helpers;

use Trefoil\Helpers\Glossary;
use Trefoil\Helpers\GlossaryItem;

/**
 * Generated by PHPUnit_SkeletonGenerator 1.2.1 on 2013-12-30 at 21:30:13.
 */
class GlossaryTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var Glossary
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->object = new Glossary;
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {

    }

    /**
     * @covers Trefoil\Helpers\Glossary::add
     */
    public function testAdd()
    {
        $this->assertEquals(0, $this->object->count());

        $item = $this->createGlossaryItem(1);
        $this->object->add($item);

        $this->assertEquals(1, $this->object->count());
    }

    /**
     * @covers Trefoil\Helpers\Glossary::get
     */
    public function testGet()
    {
        $item = $this->createGlossaryItem(1);
        $this->object->add($item);

        $actual = $this->object->get('item 1[a|b]');
        $this->assertEquals($item, $actual);

        $this->assertEquals(
             array('item 1a', 'item 1b'),
             $actual->getVariants()
        );
    }

    /**
     * @covers Trefoil\Helpers\Glossary::merge
     */
    public function testMerge()
    {
        $itemsA = array(
            $this->createGlossaryItem(1),
            $this->createGlossaryItem(2)
        );

        foreach ($itemsA as $item) {
            $this->object->add($item);
        }

        $itemsB = array(
            $this->createGlossaryItem(3),
            $this->createGlossaryItem(4)
        );

        $glossary2 = new Glossary;
        foreach ($itemsB as $item) {
            $glossary2->add($item);
        }

        $this->object->merge($glossary2);

        $this->assertEquals(
             array_merge($itemsA, $itemsB),
             iterator_to_array($this->object->getIterator(), false)
        );
    }

    /**
     * @covers Trefoil\Helpers\Glossary::count
     */
    public function testCount()
    {
        $this->assertEquals(0, $this->object->count());

        $item = $this->createGlossaryItem(1);
        $this->object->add($item);

        $this->assertEquals(1, $this->object->count());
    }

    /**
     * @covers Trefoil\Helpers\Glossary::getIterator
     */
    public function testGetIterator()
    {
        $item1 = $this->createGlossaryItem(1);
        $this->object->add($item1);

        $item2 = $this->createGlossaryItem(2);
        $this->object->add($item2);

        $this->assertEquals(
             array('item 1[a|b]' => $item1, 'item 2[a|b]' => $item2),
             iterator_to_array($this->object->getIterator())
        );
    }

    protected function createGlossaryItem($number)
    {
        $item = new GlossaryItem;
        $item->setSlug(sprintf('item%s', $number));
        $item->setTerm(sprintf('item %s[a|b]', $number));
        $item->setSource(sprintf('Source for item%s', $number));
        $item->setDescription(sprintf('Source for item%s', $number));

        return $item;
    }

}
