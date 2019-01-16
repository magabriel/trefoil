<?php
declare(strict_types=1);

namespace Trefoil\Helpers;

use PHPUnit\Framework\TestCase;

/**
 * Class IndexTest
 *
 * @package Trefoil\Helpers
 */
class IndexTest extends TestCase
{
    public function testAdd(): void
    {
        $index = new Index();

        static::assertEquals(0, $index->count());

        $item = $this->createItem('term0');
        $index->add($item);

        static::assertEquals(1, $index->count());

        static::assertEquals($item, $index->getIterator()->current());
    }

    /**
     * @param string $term
     * @param string $group
     * @param string $text
     * @return IndexItem
     */
    protected function createItem(string $term,
                                  string $group = '',
                                  string $text = ''): IndexItem
    {
        $item = new IndexItem();
        $item->setTerm($term);
        $item->setSlug($term.'-slug');
        $item->setGroup($group);
        $item->setText($text);
        $item->setSource($term.'-source');

        return $item;
    }

    public function testRemove(): void
    {
        $index = new Index();

        static::assertEquals(0, $index->count());

        $item = $this->createItem('term0');
        $index->add($item);

        static::assertEquals(1, $index->count());

        $index->remove($item);

        static::assertEquals(0, $index->count());
    }

    public function testExplodeVariants(): void
    {
        $index = new Index();

        static::assertEquals(0, $index->count());

        $index->add($this->createItem('term0-[a|b]'));
        $index->add($this->createItem('term1[s]'));

        static::assertEquals(2, $index->count());
        static::assertEquals(['term0-a', 'term0-b'], $index->get('term0-[a|b]')->getVariants());
        static::assertEquals(['term1', 'term1s'], $index->get('term1[s]')->getVariants());
    }

    public function testMerge(): void
    {
        $index = new Index();
        $index->add($this->createItem('term0-[a|b]'));
        $index->add($this->createItem('term1[s]'));

        static::assertEquals(2, $index->count());

        $index2 = new Index();
        $index2->add($this->createItem('term2'));

        $index->merge($index2);

        static::assertEquals(3, $index->count());
    }

    public function testIteratorIsSortedByGroupAndText(): void
    {
        $index = new Index();

        $index->add($this->createItem('term0', 'group1', 'text4'));
        $index->add($this->createItem('term1', 'group1', 'text2'));

        $index->add($this->createItem('term2', 'group0', 'text1'));
        $index->add($this->createItem('term3', 'group0', 'text3'));
        $index->add($this->createItem('term4', 'group0', 'text0'));

        /** @var IndexItem[] $list */
        $list = [];
        foreach ($index as $indexItem) {
            $list[] = $indexItem;
        }

        static::assertEquals('term4', $list[0]->getTerm());
        static::assertEquals('term2', $list[1]->getTerm());
        static::assertEquals('term3', $list[2]->getTerm());
        static::assertEquals('term1', $list[3]->getTerm());
        static::assertEquals('term0', $list[4]->getTerm());
    }
}
