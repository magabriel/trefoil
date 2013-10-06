<?php
namespace Trefoil\Helpers;

class GlossaryReplacer
{
    /**
     * @var Glossary
     */
    protected $glossary;

    /**
     * The HTML text to replace into
     * @var string
     */
    protected $text;

    /**
     * The options for glossary processing
     * @var array
     */
    protected $glossaryOptions = array();

    public function __construct(Glossary $glossary, $text, $glossaryOptions = array())
    {
        $this->glossary = $glossary;
        $this->text = $text;
        $this->glossaryOptions = $glossaryOptions;
    }

    public function replace()
    {
        $newText = '';

        return $newText;
    }
}
