<?php
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
     * @return string
     */
    static protected function getNodeName(Crawler $node)
    {
        foreach ($node as $i => $n) {
            $domNode = $n;
            break;
        }

        return $domNode->nodeName;
    }

    /**
     * Return the node HTML contents
     *
     * @param Crawler $node
     * @return string
     */
    static protected function getNodeHtml(Crawler $node)
    {
        $domNode = null;

        foreach ($node as $i => $n) {
            $domNode = $n;
            break;
        }

        $html = $domNode->ownerDocument->saveHtml($domNode);

        // remove surrounding tag
        $regExp = '/';
        $regExp .= '<(?<tag>.*)>';
        $regExp .= '(?<html>.+)$';
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $html = preg_replace_callback($regExp,
                function ($matches)
                {
                    $clean = mb_substr($matches['html'], 0, -mb_strlen('</' . $matches['tag'] . '>', 'utf8'), 'utf8');
                    return $clean;
                }, $html);

        return $html;
    }
}
