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

use Easybook\Util\Slugger;
use Symfony\Component\Yaml\Yaml;

/**
 * Loader class for Glossary object from definition file.
 * Expected format (yml):
 *     glossary:
 *         options: # optional section
 *             <option..>: <value>
 *         terms:
 *             term1: description of term 1
 *             ...
 *             termn: description of term n
 *
 * @see GlossaryReplacer for allowed options
 * @see Glossary for term syntax
 */
class GlossaryLoader
{

    protected $fileName;
    protected $terms;
    protected $options;
    protected $loaded = false;
    protected $slugger;

    /**
     * @param string  $fileName (full path) of the definition file
     * @param Slugger $slugger  An Slugger instance
     */
    public function __construct($fileName, Slugger $slugger)
    {
        $this->fileName = $fileName;
        $this->slugger = $slugger;
    }

    /**
     * Load the definition file.
     *
     * @param bool|string $hasOptions Whether the file also has an 'options' section
     *
     * @return Glossary
     */
    public function load($hasOptions = false)
    {
        $this->readFromFile($hasOptions);
        $glossary = $this->parse();

        return $glossary;
    }

    /**
     * True if the definition file could not been loaded (i.e. not found)
     *
     * @return boolean
     */
    public function isLoaded()
    {
        return $this->loaded;
    }

    /**
     * Get the option
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Read the options file
     *
     * @param bool $hasOptions Whether the file also has an 'options' section
     */
    protected function readFromFile($hasOptions = false)
    {
        $this->loaded = false;

        if (file_exists($this->fileName)) {
            $glossaryDefinition = Yaml::parse($this->fileName);
            $this->loaded = true;
        } else {
            $glossaryDefinition = array();
        }

        // defaults
        $default = array(
            'glossary' => array(
                'terms' => array()
            )
        );

        if ($hasOptions) {
            $default['glossary']['options'] = array(
                'coverage' => 'item',
                'elements' => array(
                    'chapter'
                )
            );
        }

        $glossaryDefinition = array_replace_recursive($default, $glossaryDefinition);

        $this->terms = $glossaryDefinition['glossary']['terms'];
        if ($hasOptions) {
            $this->options = $glossaryDefinition['glossary']['options'];
        }
    }

    /**
     * Parse the loaded definitions into a Glossary object
     *
     * @return \Trefoil\Helpers\Glossary
     */
    protected function parse()
    {
        $glossary = new Glossary();

        foreach ($this->terms as $term => $description) {

            $gi = new GlossaryItem();
            $gi->setTerm($term);
            $gi->setSlug($this->slugger->slugify($term));
            $gi->setSource(basename($this->fileName));
            $gi->setDescription($description);

            $glossary->add($gi);
        }

        return $glossary;
    }

}
