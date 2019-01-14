<?php
declare(strict_types=1);

namespace Trefoil\Helpers;

use EasySlugger\Utf8Slugger;

/**
 * Class IndexLoaderTest
 *
 * @package Trefoil\Helpers
 */
class IndexLoaderTest extends \PHPUnit_Framework_TestCase
{

    public function testLoadFromYamlString(): void
    {
        $yaml = /** @lang yaml */
            <<<YAML
index:
    options: 
        elements: [chapter, edition]
    terms:
        term0:
        term1: "text for term0"
        term2:
            text: "text for term2"
            terms:
                "term2-0": "text for term2-0"
                "term2-1": "text for term2-1"

    manual-terms:
        term3:
        term4: "text for term4"
        term5:
            text: "text for term5"
            terms:
                "term5-0": "text for term5-0"
                "term5-1": "text for term5-1"
                "term5-2": "text for term5-2"
YAML;

        // Utf8Slugger cannot be mocked because it is static
        $slugger = new Utf8Slugger();
        $loader = new IndexLoader($yaml, $slugger);
        $index = $loader->loadFromYamlString();

        static::assertTrue($loader->isLoaded());
        static::assertEquals(['elements' => ['chapter', 'edition']], $loader->getOptions());
        static::assertEquals(9, $index->count(), 'Count total items');

        $manual = 0;
        /** @var IndexItem $indexItem */
        foreach ($index as $indexItem) {
            $manual += $indexItem->isManual() ? 1 : 0;
        }

        static::assertEquals(5, $manual, 'Count manual items');
    }
}

