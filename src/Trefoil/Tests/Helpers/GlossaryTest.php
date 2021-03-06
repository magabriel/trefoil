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

use Trefoil\Helpers\Glossary;
use Trefoil\Helpers\GlossaryItem;
use PHPUnit\Framework\TestCase;

/**
 * Generated by PHPUnit_SkeletonGenerator 1.2.1 on 2013-12-30 at 21:30:13.
 */
class GlossaryTest extends TestCase
{

    /**
     * @var Glossary
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void
    {
        $this->object = new Glossary;
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown(): void
    {

    }

    public function testAdd(): void
    {
        static::assertEquals(0, $this->object->count());

        $item = $this->createGlossaryItem(1);
        $this->object->add($item);

        static::assertEquals(1, $this->object->count());
    }

    public function testGet(): void
    {
        $item = $this->createGlossaryItem(1);
        $this->object->add($item);

        $actual = $this->object->get('item 1[a|b]');
        static::assertEquals($item, $actual);

        static::assertEquals(
            ['item 1a', 'item 1b'],
            $actual->getVariants()
        );
    }

    public function testMerge(): void
    {
        $itemsA = [
            $this->createGlossaryItem(1),
            $this->createGlossaryItem(2)
        ];

        foreach ($itemsA as $item) {
            $this->object->add($item);
        }

        $itemsB = [
            $this->createGlossaryItem(3),
            $this->createGlossaryItem(4)
        ];

        $glossary2 = new Glossary;
        foreach ($itemsB as $item) {
            $glossary2->add($item);
        }

        $this->object->merge($glossary2);

        static::assertEquals(
             array_merge($itemsA, $itemsB),
             iterator_to_array($this->object->getIterator(), false)
        );
    }

    public function testCount(): void
    {
        static::assertEquals(0, $this->object->count());

        $item = $this->createGlossaryItem(1);
        $this->object->add($item);

        static::assertEquals(1, $this->object->count());
    }

    public function testGetIterator(): void
    {
        $item1 = $this->createGlossaryItem(1);
        $this->object->add($item1);

        $item2 = $this->createGlossaryItem(2);
        $this->object->add($item2);

        static::assertEquals(
            ['item1' => $item1, 'item2' => $item2],
            iterator_to_array($this->object->getIterator())
        );
    }

    /**
     * @param $number
     * @return GlossaryItem
     */
    protected function createGlossaryItem($number): GlossaryItem
    {
        $item = new GlossaryItem;
        $item->setSlug(sprintf('item%s', $number));
        $item->setTerm(sprintf('item %s[a|b]', $number));
        $item->setSource(sprintf('Source for item%s', $number));
        $item->setDescription(sprintf('Source for item%s', $number));

        return $item;
    }

}
