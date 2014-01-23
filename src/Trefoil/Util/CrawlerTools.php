<?php
/*
 * This file is part of the trefoil application.
 *
 * (c) Miguel Angel Gabriel <magabriel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Trefoil\Util;

use DOMNode;
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
    public static function getNodeName(Crawler $node)
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
    public static function getNodeText(Crawler $node)
    {
        foreach ($node->getNode(0)->childNodes as $cNode)
        {
            if ("#text" == $cNode->nodeName) {
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
    public static function getNodeHtml(Crawler $node)
    {
        $domNode = $node->getNode(0);
        
        if (null == $domNode) {
            return '';
        }

        $html = $domNode->ownerDocument->saveHtml($domNode);

        // remove line breaks
        $html = str_replace(array("\n", "\r"), '', $html);

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
