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
namespace Trefoil\Util;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Serveral utility operations for Crawler objects.
 *
 */
class CrawlerTools
{

    /**
     * Return the node name
     *
     * @param Crawler $node
     *
     * @return string
     */
    public static function getNodeName(Crawler $node): string
    {
        return $node->getNode(0)->nodeName;
    }

    /**
     * Return the node text contents (w/o the children).
     *
     * @param Crawler $node
     *
     * @return string
     */
    public static function getNodeText(Crawler $node): string
    {
        foreach ($node->getNode(0)->childNodes as $cNode) {
            if ('#text' === $cNode->nodeName) {
                return $cNode->nodeValue;
            }
        }

        return '';
    }

    /**
     * Return the node HTML contents
     *
     * @param Crawler $node
     *
     * @return string
     */
    public static function getNodeHtml(Crawler $node): string
    {
        $domNode = $node->getNode(0);

        if (null === $domNode) {
            return '';
        }

        $html = $domNode->ownerDocument->saveHTML($domNode);

        // remove line breaks
        $html = str_replace(["\n", "\r"], '', $html);

        // remove surrounding tag
        $regExp = '/';
        $regExp .= '<(?<tag>.*)>';
        $regExp .= '(?<html>.*)$';
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $html = preg_replace_callback(
            $regExp,
            function ($matches) {
                return '<' . $matches['tag'] . '>' . $matches['html'];
            },
            $html
        );

        return $html;
    }
}
