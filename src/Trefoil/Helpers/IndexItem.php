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

/**
 * An item of the Index
 *
 */
class IndexItem
{
    protected $term;
    protected $text;
    protected $group;
    protected $slug;
    protected $source;
    protected $variants = array();
    protected $xref = array();
    protected $anchorLinks = array();
    protected $manual = false;

    /**
     * Add a cross-reference to the list
     *
     * @param string $variant The term variant
     * @param string $where The location where it was found
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

    /**
     * @return mixed
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * @param mixed $text
     */
    public function setText($text)
    {
        $this->text = $text;
    }

    /**
     * @return mixed
     */
    public function getGroup()
    {
        return $this->group;
    }

    /**
     * @param mixed $group
     */
    public function setGroup($group)
    {
        $this->group = $group;
    }

    /**
     * @return bool
     */
    public function isManual(): bool
    {
        return $this->manual;
    }

    /**
     * @param bool $manual
     */
    public function setManual(bool $manual): void
    {
        $this->manual = $manual;
    }

}
