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
 * An Index object.
 *
 * An index item (a term and its description) can be a literal or a variant definition:
 *
 *     term
 *     or
 *     term => variant
 *
 * Variants can be specified of the form 'root[suffix]', where suffix can be '[one]' or '[one|two|..]'
 * Examples:
 *   'cat[s]' => 'cat' and 'cats'
 *   'star [wars|trek]' => 'star wars' and 'star trek'
 *
 */
class Index implements \IteratorAggregate
{

    /**
     * @var IndexItem[]
     */
    protected $items = [];

    /**
     * @param IndexItem $item
     */
    public function add(IndexItem $item): void
    {
        $this->explodeVariants($item);
        $this->items[$item->getSlug()] = $item;
    }

    /**
     * @param IndexItem $item
     */
    public function remove(IndexItem $item): void
    {
        foreach ($this->items as $key => $existingItem) {
            if ($existingItem->getTerm() === $item->getTerm()) {
                unset($this->items[$key]);
                break;
            }
        }
    }


    /**
     * @param $term
     * @return IndexItem|null
     */
    public function get($term): ?IndexItem
    {
        foreach ($this->items as $item) {
            if ($item->getTerm() === $term) {
                return $item;
            }
        }

        return null;
    }


    /**
     * @param $variant
     * @return IndexItem|null
     */
    public function getItemWithVariant($variant): ?IndexItem
    {
        foreach ($this->items as $item) {
            foreach ($item->getVariants() as $itemVariant) {
                if ($itemVariant === $variant) {
                    return $item;
                }
            }
        }

        return null;
    }

    /**
     * Merge another index into this one
     *
     * @param Index $index
     */
    public function merge(Index $index): void
    {
        foreach ($index as $item) {
            $this->add($item);
        }
    }

    /**
     * Number of items in index
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
        // Sort the items by group and text
        uasort($this->items, function (IndexItem $a, IndexItem $b) {
            return (
                strtolower(str_pad($a->getGroup(), 20) . $a->getText())
                <=>
                strtolower(str_pad($b->getGroup(), 20) . $b->getText())
            );
        });

        return new \ArrayIterator($this->items);
    }

    /**
     * Explode all term variants so each one could be processed separately.
     * Variants can be specified of the form 'root[suffix]', where suffix can be '[one]' or '[one|two|..]'
     * Examples:
     *   'cat[s]' => 'cat' and 'cats'
     *   'star [wars|trek]' => 'star wars' and 'star trek'
     *
     * @param IndexItem $item
     */
    protected function explodeVariants(IndexItem $item): void
    {
        $regExp = '/';
        $regExp .= '(?<root>[\w\s-]*)'; // root of the term (can contain in-between spaces or dashes)
        $regExp .= '(\['; // opening square bracket
        $regExp .= '(?<suffixes>.+)'; // suffixes
        $regExp .= '\])?'; // closing square bracket
        $regExp .= '/u'; // unicode

        $variants = [];

        if (preg_match($regExp, $item->getTerm(), $parts)) {
            if ($parts && array_key_exists('suffixes', $parts)) {
                $suffixes = explode('|', $parts['suffixes']);
                if (count($suffixes) === 1) {
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
