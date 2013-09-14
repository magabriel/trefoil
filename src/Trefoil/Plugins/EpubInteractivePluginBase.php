<?php
namespace Trefoil\Plugins;

use Easybook\Events\ParseEvent;
use Symfony\Component\DomCrawler\Crawler;

/**
 * This is the base for interactive epub plugins.
 *
 */
class EpubInteractivePluginBase
{
    protected $app;
    protected $item;
    protected $links = array();

    protected function init(ParseEvent $event)
    {
        $this->app = $event->app;
        $this->item = $event->getItem();
    }

    protected function wrapUp($idsToRemoveFromToc = array())
    {
        // save new links
        $oldLinks = $this->app->get('publishing.links');
        $newLinks = array_merge($oldLinks, $this->links);

        $this->app->set('publishing.links', $newLinks);

        // remove activities from TOC
        $this->cleanToc($idsToRemoveFromToc);
    }

    /**
     * Remove the activities from TOC because they don't belong there
     */
    protected function cleanToc($idsToRemoveFromToc = array())
    {
        // Remove activities from TOC
        $newToc = array();
        $toc = $this->app->get('publishing.active_item.toc');
        foreach ($toc as $tocitem)
        {
            // Only add it if it is not an activity
            if (!in_array($tocitem['slug'], $idsToRemoveFromToc)) {
                $newToc[] = $tocitem;
            }
        }

        // Set the new TOC both as itself and as inside item
        $this->app->set('publishing.active_item.toc', $newToc);

        $item = $this->app->get('publishing.active_item');
        $item['toc'] = $newToc;
        $this->app->set('publishing.active_item', $item);
    }

    protected function registerAnchorLink($id)
    {
        $item = $this->item;

        $itemSlug = $item['config']['element'];
        if (array_key_exists('number', $item['config']) && $item['config']['number'] !== '') {
            $itemSlug = $item['config']['element'] . '-' . $item['config']['number'];
        }

        $relativeUrl = '#' . $id;
        $absoluteUrl = $itemSlug . '.html' . $relativeUrl;

        $this->links[$relativeUrl] = $absoluteUrl;
    }

    /**
     * Return the node name
     *
     * @param Crawler $node
     * @return Ambigous <NULL>
     */
    protected function getNodeName(Crawler $node)
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
     * @return string|mixed
     */
    protected function getNodeHtml(Crawler $node)
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
