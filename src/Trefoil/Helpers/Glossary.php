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
 * A Glossary object.
 *
 * A glossary item (a term and its description) can be a literal or a variant definition:
 *
 *     term => definition
 *     or
 *     term => variant
 *
 * Variants can be specified of the form 'root[suffix]', where suffix can be '[one]' or '[one|two|..]'
 * Examples:
 *   'cat[s]' => 'cat' and 'cats'
 *   'star [wars|trek]' => 'star wars' and 'star trek'
 *
 */
class Glossary implements \IteratorAggregate
{

    /**
     * @var GlossaryItem[]
     */
    protected $items = array();

    /**
     * @param GlossaryItem $item
     */
    public function add(GlossaryItem $item)
    {
        $this->explodeVariants($item);
        $this->items[$item->getSlug()] = $item;
    }

    /**
     * @param GlossaryItem $item
     */
    public function remove(GlossaryItem $item)
    {
        foreach ($this->items as $key => $existingItem) {
            if ($existingItem->getTerm() == $item->getTerm()) {
                unset($this->items[$key]);
                break;
            }
        }
    }

    /**
     * @param string $term
     *
     * @return GlossaryItem
     */
    public function get($term)
    {
        foreach ($this->items as $item) {
            if ($item->getTerm() == $term) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Merge another glossary into this one
     *
     * @param Glossary $glossary
     */
    public function merge(Glossary $glossary)
    {
        foreach ($glossary as $item) {
            $this->add($item);
        }
    }

    /**
     * Number of items in glossary
     *
     * @return number
     */
    public function count()
    {
        return count($this->items);
    }

    /**
     * @see IteratorAggregate::getIterator()
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->items);
    }

    /**
     * Explode all term variants so each one could be processed separately.
     * Variants can be specified of the form 'root[suffix]', where suffix can be '[one]' or '[one|two|..]'
     * Examples:
     *   'cat[s]' => 'cat' and 'cats'
     *   'star [wars|trek]' => 'star wars' and 'star trek'
     *
     * @param GlossaryItem $item
     */
    protected function explodeVariants(GlossaryItem $item)
    {
        $regExp = '/';
        $regExp .= '(?<root>[\w\s-]*)'; // root of the term (can contain in-between spaces or dashes)
        $regExp .= '(\['; // opening square bracket
        $regExp .= '(?<suffixes>.+)'; // suffixes
        $regExp .= '\])?'; // closing square bracket
        $regExp .= '/u'; // unicode

        $variants = array();

        if (preg_match($regExp, $item->getTerm(), $parts)) {
            if (array_key_exists('suffixes', $parts)) {
                $suffixes = explode('|', $parts['suffixes']);
                if (1 == count($suffixes)) {
                    // exactly one suffix means root without and with suffix (i.e. 'word[s]')
                    $variants[] = $parts['root'];
                    $variants[] = $parts['root'] . $suffixes[0];
                } else {
                    // more than one suffix means all the variations (i.e. 'entit[y|ies]')
                    foreach ($suffixes as $suffix) {
                        $variants[] = $parts['root'] . $suffix;
                    }
                }
            } else {
                // no suffixes, just the root definition
                $variants[] = $parts['root'];
            }
        }

        $item->setVariants($variants);
    }

}
