<?php
/**
 * Created by PhpStorm.
 * User: miguelangel
 * Date: 4/12/18
 * Time: 21:57
 */

namespace Trefoil\Helpers;


use EasySlugger\Slugger;
use EasySlugger\Utf8Slugger;

class IndexReplacerTest extends \PHPUnit_Framework_TestCase
{

    public function testReplace()
    {
        $replacer = $this->createReplacer();
        $replaced = $replacer->replace();

        $this->assertEquals($this->getExpectedText(), $replaced);
    }

    protected function createReplacer(): IndexReplacer
    {
        $replacer = new IndexReplacer(
            $this->createIndex(),
            new TextPreserver(),
            $this->getText(),
            "text-id",
            $this->createTwig()
        );

        return $replacer;
    }

    protected function createTwig(): \Twig_Environment
    {
        $loader = new \Twig_Loader_Array([]);
        $loader->setTemplate('auto-index-term.twig',
            '{{ term }}<a class="auto-index-term" id="term-{{ reference }}"/>'
        );
        $twig = new \Twig_Environment($loader, array(
            'debug' => true,
            'cache' => false,
            'strict_variables' => true,
            'autoescape' => false,
        ));

        return $twig;
    }

    protected function createIndex(): Index
    {
        $index = new Index();

        $index->add($this->createItem("term0"));
        $index->add($this->createItem("term1"));

        $index->add($this->createItem("term2", "", "", true));
        $index->add($this->createItem("term3", "", "", true));
        $index->add($this->createItem("term4", "", "", true));

        return $index;
    }

    protected function createItem(string $term,
                                  string $group = "",
                                  string $text = "",
                                  bool $isManual = false): IndexItem
    {
        $item = new IndexItem();
        $item->setTerm($term);
        $item->setSlug($term . "-slug");
        $item->setGroup($group);
        $item->setText($text);
        $item->setSource($term . "-source");
        $item->setManual($isManual);
        return $item;
    }

    protected function getText(): string
    {
        return /** @lang HTML */
            <<<TAG
<h2>The title</h2>
<h3>Automatic terms</h3>
<p class="para">This is a <strong>paragraph (term0)</strong> with some text for testing.</p>
<p>And this is another one with <a href="http://google.com">more text (term1)</a></p>
<h3>Manually marked terms</h3>
<p>This is a manual term: term20|@| which should work.</p>
<p>This is another one with emphasis: <strong>term21</strong>|@| which should also work.</p>
<p>And underlined: <em>term22</em>|@|.</p>
<p>This is a term marked with delimiters only and composed by two words: |term23 test|.</p>
TAG;
    }

    protected function getExpectedText(): string
    {
        return /** @lang HTML */
            <<<TAG
<h2>The title</h2>
<h3>Automatic terms</h3>
<p class="para">This is a <strong>paragraph (term0<a class="auto-index-term" id="term-term0-slug-0"/>)</strong> with some text for testing.</p>
<p>And this is another one with <a href="http://google.com">more text (term1)</a></p>
<h3>Manually marked terms</h3>
<p>This is a manual term: term20<a class="auto-index-term" id="term-term20-0"/> which should work.</p>
<p>This is another one with emphasis: <strong>term21<a class="auto-index-term" id="term-term21-0"/></strong> which should also work.</p>
<p>And underlined: <em>term22<a class="auto-index-term" id="term-term22-0"/></em>.</p>
<p>This is a term marked with delimiters only and composed by two words: term23 test<a class="auto-index-term" id="term-term23-test-0"/>.</p>
TAG;
    }
}
