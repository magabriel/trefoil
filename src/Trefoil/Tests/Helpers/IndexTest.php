<?php

namespace Trefoil\Helpers;

class IndexTest extends \PHPUnit_Framework_TestCase
{
    public function testAdd()
    {
        $index = new Index();

        $this->assertEquals(0, $index->count());

        $item = $this->createItem("term0");
        $index->add($item);

        $this->assertEquals(1, $index->count());

        $this->assertEquals($item, $index->getIterator()->current());
    }

    public function testRemove()
    {
        $index = new Index();

        $this->assertEquals(0, $index->count());

        $item = $this->createItem("term0");
        $index->add($item);

        $this->assertEquals(1, $index->count());

        $index->remove($item);

        $this->assertEquals(0, $index->count());
    }

    public function testExplodeVariants()
    {
        $index = new Index();

        $this->assertEquals(0, $index->count());

        $index->add($this->createItem("term0-[a|b]"));
        $index->add($this->createItem("term1[s]"));

        $this->assertEquals(2, $index->count());
        $this->assertEquals(["term0-a", "term0-b"], $index->get("term0-[a|b]")->getVariants());
        $this->assertEquals(["term1", "term1s"], $index->get("term1[s]")->getVariants());
    }

    public function testMerge()
    {
        $index = new Index();
        $index->add($this->createItem("term0-[a|b]"));
        $index->add($this->createItem("term1[s]"));

        $this->assertEquals(2, $index->count());

        $index2 = new Index();
        $index2->add($this->createItem("term2"));

        $index->merge($index2);

        $this->assertEquals(3, $index->count());
    }

    public function testIteratorIsSortedByGroupAndText()
    {
        $index = new Index();

        $index->add($this->createItem("term0", "group1", "text4"));
        $index->add($this->createItem("term1", "group1", "text2"));

        $index->add($this->createItem("term2", "group0", "text1"));
        $index->add($this->createItem("term3", "group0", "text3"));
        $index->add($this->createItem("term4", "group0", "text0"));

        /** @var IndexItem[] $list */
        $list = [];
        foreach ($index as $indexItem) {
            $list[] = $indexItem;
        }

        $this->assertEquals("term4", $list[0]->getTerm());
        $this->assertEquals("term2", $list[1]->getTerm());
        $this->assertEquals("term3", $list[2]->getTerm());
        $this->assertEquals("term1", $list[3]->getTerm());
        $this->assertEquals("term0", $list[4]->getTerm());
    }

    /**
     * @param string $term
     * @param string $group
     * @param string $text
     * @return IndexItem
     */
    protected function createItem(string $term,
                                  string $group = "",
                                  string $text = ""): IndexItem
    {
        $item = new IndexItem();
        $item->setTerm($term);
        $item->setSlug($term . "-slug");
        $item->setGroup($group);
        $item->setText($text);
        $item->setSource($term . "-source");
        return $item;
    }
}
