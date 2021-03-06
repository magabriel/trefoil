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

/**
 * An item of the Glossary
 *
 */
class GlossaryItem
{
    protected $term = '';
    protected $description = '';
    protected $slug = '';
    protected $source = '';
    protected $variants = [];
    protected $xref = [];
    protected $anchorLinks = [];

    /**
     * Add a cross-reference to the list
     *
     * @param string $variant The term variant
     * @param string $where   The location where it was found
     */
    public function addXref($variant, $where): void
    {
        if (!isset($this->xref[$variant])) {
            $this->xref[$variant] = [];
        }

        if (!isset($this->xref[$variant][$where])) {
            $this->xref[$variant][$where] = 0;
        }

        $this->xref[$variant][$where]++;
    }

    public function getXref(): array
    {
        return $this->xref;
    }

    public function getTerm(): string
    {
        return $this->term;
    }

    public function setTerm($term): void
    {
        $this->term = $term;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription($description): void
    {
        $this->description = $description;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug($slug): void
    {
        $this->slug = $slug;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource($source): void
    {
        $this->source = $source;
    }

    public function getVariants(): array
    {
        return $this->variants;
    }

    public function setVariants($variants): void
    {
        $this->variants = $variants;
    }

    public function getAnchorLinks(): array
    {
        return $this->anchorLinks;
    }

    /**
     * Add an anchor link to the list
     *
     * @param string $anchorLink
     */
    public function addAnchorLink($anchorLink): void
    {
        $this->anchorLinks[] = $anchorLink;
    }

}
