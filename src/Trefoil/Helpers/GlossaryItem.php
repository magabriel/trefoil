<?php
namespace Trefoil\Helpers;

/**
 * An item of the Glossary
 *
 */
class GlossaryItem
{
    protected $term;
    protected $description;
    protected $slug;
    protected $source;
    protected $variants = array();
    protected $xref = array();
    protected $anchorLinks = array();

    /**
     * Add a cross-reference to the list
     *
     * @param string $variant The term variant
     * @param string $where   The location where it was found
     */
    public function addXref($variant, $where)
    {
        if (!isset($this->xref[$variant])) {
            $this->xref[$variant] = array();
        }

        if (!isset($this->xref[$variant][$where])) {
            $this->xref[$variant][$where] = 0;
        }

        $this->xref[$variant][$where]++;
    }

    public function getXref()
    {
        return $this->xref;
    }

    public function getTerm()
    {
        return $this->term;
    }

    public function setTerm($term)
    {
        $this->term = $term;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($description)
    {
        $this->description = $description;
    }

    public function getSlug()
    {
        return $this->slug;
    }

    public function setSlug($slug)
    {
        $this->slug = $slug;
    }

    public function getSource()
    {
        return $this->source;
    }

    public function setSource($source)
    {
        $this->source = $source;
    }

    public function getVariants()
    {
        return $this->variants;
    }

    public function setVariants($variants)
    {
        $this->variants = $variants;
    }

    public function getAnchorLinks()
    {
        return $this->anchorLinks;
    }

    /**
     * Add an anchor link to the list
     *
     * @param string $anchorLink
     */
    public function addAnchorLink($anchorLink)
    {
        $this->anchorLinks[] = $anchorLink;
    }

}
