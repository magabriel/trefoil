<?php
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
        /* @var DOMNode $domNode */
        $domNode = null;

        foreach ($node as $n) {
            $domNode = $n;
            break;
        }

        return $domNode->nodeName;
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
        /* @var DOMNode $domNode */
        $domNode = null;

        foreach ($node as $n) {
            $domNode = $n;
            break;
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
