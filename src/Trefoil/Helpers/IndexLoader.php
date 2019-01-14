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

namespace Trefoil\Helpers;

use EasySlugger\SluggerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Loader class for Index object from definition file.
 * Expected format (yml):
 *     index:
 *         options: # optional section
 *              # items where the auto index terms should be applied
 *              elements: [chapter] # default
 *          # definitions of automatically-added terms
 *          terms:
 *              # A simple term definition:
 *              term: text (optional)
 *              #A complex term definition (group):
 *              term:
 *                  text: "Term text" (optional)
 *                  terms:
 *                      "subterm 1": "subterm 1 text" (optional)
 *                      ...
 *                      "subterm n": "subterm n text" (optional)
 *          # definitions of manually-added terms
 *          manual-terms:
 *              # A simple term definition:
 *              term-key-1: text (optional)
 *              #A complex term definition (group):
 *              group-name:
 *                  text: "Term text for group" (optional)
 *                  terms:
 *                      subterm-key-1: "subterm 1 text" (optional)
 *                      ...
 *                      subterm-key-n: "subterm n text" (optional)
 *
 * @see IndexReplacer for allowed options
 * @see Index for term syntax
 */
class IndexLoader
{
    protected $fileNameOrYamlString;
    protected $terms = [];
    protected $manualTerms = [];
    protected $options;
    protected $loaded = false;
    protected $slugger;

    /**
     * @param string           $fileNameOrYamlString (full path) of the definition file or a YAML string to parse
     * @param SluggerInterface $slugger              An Slugger instance
     */
    public function __construct(string $fileNameOrYamlString,
                                SluggerInterface $slugger)
    {
        $this->fileNameOrYamlString = $fileNameOrYamlString;
        $this->slugger = $slugger;
    }

    /**
     * Load the definition file.
     *
     * @return Index
     */
    public function load(): Index
    {
        // definitely a filename
        $this->readFromFile($this->fileNameOrYamlString);

        return $this->parse($this->fileNameOrYamlString);
    }

    /**
     * Read the options file
     *
     * @param $fileName
     */
    protected function readFromFile(string $fileName): void
    {
        $this->loaded = false;

        if (file_exists($fileName)) {
            $indexDefinition = Yaml::parse($fileName) ?: [];
            $this->loaded = true;
        } else {
            $indexDefinition = [];
        }

        $this->applyDefaultsToIndexDefinition($indexDefinition);

    }

    /**
     * @param array $indexDefinition
     */
    protected function applyDefaultsToIndexDefinition(array $indexDefinition): void
    {
        // defaults
        $default = [
            'index' => [
                'terms'        => [],
                'manual-terms' => [],
            ],
        ];

        $default['index']['options'] = [
            'elements' => [
                'chapter',
            ],
        ];

        $indexDefinition = array_replace_recursive($default, $indexDefinition);

        $this->terms = $indexDefinition['index']['terms'];
        $this->manualTerms = $indexDefinition['index']['manual-terms'];
        $this->options = $indexDefinition['index']['options'];
    }

    /**
     * Parse the loaded definitions into a Index object.
     *
     * @param $fileName
     * @return \Trefoil\Helpers\Index
     */
    protected function parse(string $fileName): Index
    {
        $index = new Index();

        // add the automatic terms to the index
        foreach ($this->terms as $term => $definition) {
            $this->parseTermToIndex($term, $definition, $fileName, $index);
        }

        // add the manual terms to the index
        foreach ($this->manualTerms as $term => $definition) {
            $this->parseTermToIndex($term, $definition, $fileName, $index, true);
        }

        return $index;
    }

    /**
     * @param string            $term
     * @param null|string|array $definition
     * @param string            $fileName
     * @param Index             $index
     * @param bool              $isManual
     */
    protected function parseTermToIndex(string $term,
                                        $definition,
                                        string $fileName,
                                        Index $index,
                                        bool $isManual = false): void
    {
        /*
         * A simple term definition:
         *     term: text (optional)
         * Create a single item for it.
         */
        if (is_string($definition) || $definition === null) {
            $item = $this->createItem($term, $fileName);
            $text = $definition ?: $term;
            $group = $text;
            $item->setText($text);
            $item->setGroup($group);
            $item->setManual($isManual);
            $index->add($item);

            return;
        }

        /*
         * A complex term definition (group):
         *      term:
         *          text: "Term text" (optional)
         *          terms:
         *              "subterm 1": "subterm 1 text" (optional)
         *              ...
         *              "subterm n": "subterm n text" (optional)
         * Create an item for each one.
         */
        $text = $definition['text'] ?? $term;
        $group = $text;
        /** @var array[][] $definition */
        foreach ($definition['terms'] as $term2 => $definition2) {
            $item = $this->createItem($term2, $fileName);
            $text = is_string($definition2) ? $definition2 : $term2;
            $item->setText($text);
            $item->setGroup($group);
            $item->setManual($isManual);
            $index->add($item);
        }
    }

    /**
     * @param $term
     * @param $fileName
     * @return IndexItem
     */
    protected function createItem(string $term,
                                  string $fileName): IndexItem
    {
        $item = new IndexItem();
        $item->setTerm($term);
        $item->setSource(basename($fileName));
        // ensure uniqueness of slug to avoid collisions
        $prefix = crc32($term).'-';
        $item->setSlug($prefix.$this->slugger::slugify($term));

        return $item;
    }

    /**
     * @return Index
     */
    public function loadFromYamlString(): Index
    {
        // definitely a Yaml string
        $this->readFromYamlString($this->fileNameOrYamlString);

        return $this->parse('placeholder-filename');
    }

    /**
     * @param $yamlString
     */
    protected function readFromYamlString(string $yamlString): void
    {
        $indexDefinition = Yaml::parse($yamlString);
        $this->loaded = true;

        $this->applyDefaultsToIndexDefinition($indexDefinition);
    }

    /**
     * True if the definition file could not been loaded (i.e. not found)
     *
     * @return boolean
     */
    public function isLoaded(): bool
    {
        return $this->loaded;
    }

    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

}
