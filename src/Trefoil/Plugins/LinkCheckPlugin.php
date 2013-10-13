<?php
namespace Trefoil\Plugins;

use Trefoil\Util\Toolkit;
use Trefoil\Util\SimpleReport;
use Trefoil\Helpers\LinkChecker;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Easybook\Events\EasybookEvents;
use Easybook\Events\BaseEvent;
use Easybook\Events\ParseEvent;

/**
 * Plugin to check internal and external links in book.
 *
 * Internal links are checked by looking for a valid link target, i.e. an html element
 * whith id="the-link-target" in the whole book.
 *
 * External links are (optionally) checked for existence by performing a network lookup.
 * This behaviour is off by default because could be very time consuming if the book
 * has a large number of external links. To turn it on, set the following option in
 * the book's config.yml:
 *
 *     editions:
 *         <edition-name>
 *             check_external_links: true
 *
 * The plugin will generate a report in the output directory for all the external links
 * in the book with its status from the check (OK or error).
 *
 */
class LinkCheckPlugin extends BasePlugin implements EventSubscriberInterface
{
    protected $links = array(
            'internal' => array(),
            'external' => array()
            );

    protected $linkTargets = array();

    public static function getSubscribedEvents()
    {
        return array(
                EasybookEvents::POST_PARSE => array('onItemPostParse', -1100), // the latest possible
                EasybookEvents::POST_PUBLISH => array('onPostPublish', -1100)  // the latest possible
        );
    }

    public function onItemPostParse(BaseEvent $event)
    {
        $this->init($event);

        // retrieve all the links for this item
        $links = $this->findLinks($event->getContent(),  $event->getItem()['config']['content']);
        $this->links = array_merge_recursive($this->links, $links);

        // retrieve all the internal link targets for this item
        $linkTargets = $this->findLinkTargets($event->getContent());
        $this->linkTargets = array_merge_recursive($this->linkTargets, $linkTargets);
    }

    public function onPostPublish(BaseEvent $event)
    {
        $this->init($event);

        $this->checkInternalLinks();
        $this->checkExternalLinks();

        $this->createReport();
    }

    /**
     * Extract all links in content
     *
     * @param string $content
     * @return array
     */
    protected function findLinks($content, $xref)
    {
        $links = array(
                'internal' => array(
                        $xref => array()
                        ),
                'external' => array(
                        $xref => array()
                        )
        );

        preg_match_all('/<a .*href="(?<uri>.*)".*>(?<text>.*)<\/a>/Ums', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $link = array(
                    'text' => $match['text'],
                    'uri' => $match['uri']
                    );

            if ('#' == substr($match['uri'], 0, 1)) {
                $links['internal'][$xref][] = $link;
            } else {
                $links['external'][$xref][] = $link;
            }
        }

        return $links;
    }

    /**
     * Extract all possible link targets in content
     *
     * @param string $content
     * @return array
     */
    protected function findLinkTargets($content)
    {
        $linkTargets = array();

        preg_match_all('/<.*id="(?<id>.*)".*>/Ums', $content, $matches);

        foreach ($matches['id'] as $match) {
            $linkTargets[] = $match;
        }

        return $linkTargets;
    }

    /**
     * Check all the internal links for existence
     */
    protected function checkInternalLinks()
    {
        foreach ($this->links['internal'] as $xref => $links) {
            foreach ($links as $index => $link) {
                if (!in_array(substr($link['uri'], 1), $this->linkTargets)) {
                    $this->writeLn(
                            sprintf(
                                    '<error>"%s": Internal link target "%s" => "%s" not found.</error>',
                                    $xref,
                                    $link['text'],
                                    $link['uri']));
                    $this->links['internal'][$xref][$index]['status'] = 'Not found';
                } else {
                    $this->links['internal'][$xref][$index]['status'] = 'OK';
                }
            }
        }
    }

    /**
     * Check external links for existence
     */
    protected function checkExternalLinks()
    {
        if (!isset($this->app->book('editions')[$this->edition]['check_external_links'])
               || !$this->app->book('editions')[$this->edition]['check_external_links']) {
            // nothing to do
            return;
        }

        $checker = new LinkChecker();

        foreach ($this->links['external'] as $xref => $links) {
            foreach ($links as $index => $link) {
                try {
                    $this->writeLn(sprintf(' > Checking link "%s" => ', $link['text']));
                    $this->write(sprintf('     %s ...', $link['uri']));

                    $checker->check($link['uri']);

                    $this->writeLn('<info>OK</info>', false);

                    $this->links['external'][$xref][$index]['status'] = 'OK';

                } catch (\Exception $e) {
                    $this->writeLn('', false);
                    $this->writeLn(
                            sprintf(
                                    '<error>"%s": External link target "%s" => "%s" error "%s".</error>',
                                    $xref,
                                    $link['text'],
                                    $link['uri'],
                                    $e->getMessage()));

                    $this->links['external'][$xref][$index]['status'] = $e->getMessage();
                }
            }
        }
    }

    protected function createReport()
    {
        $report = new SimpleReport();
        $report->setTitle('LinkCheckPlugin');

        $checkExternalLinks = isset($this->app->book('editions')[$this->edition]['check_external_links']) &&
                              $this->app->book('editions')[$this->edition]['check_external_links'];
        $report->setSubtitle(sprintf('check_external_links: %s', $checkExternalLinks?'true':'false'));

        $report->setHeaders(array(
                'Type',
                'Item',
                'Status',
                'Link'));
        $report->setColumnsWidth(array(
                8,
                4,
                20,
                100
                ));

        $countOk = 0;
        $countError = 0;
        $countNotChecked = 0;

        foreach ($this->links as $type => $linksByType) {

            $report->addLine(array(ucfirst($type)));
            $report->addLine();

            foreach ($linksByType as $xref => $links) {
                $report->addLine(array('', $xref));
                $report->addLine();
                foreach ($links as $index => $link) {

                    $status = isset($link['status']) ? $link['status'] : 'Not checked';

                    if ('OK' == $status) {
                        $countOk++;
                    } elseif ('Not checked' == $status) {
                        $countNotChecked++;
                    } else {
                        $countError++;
                    }

                    $report->addLine(array('','',$status,$link['text']));
                    if ($link['text'] != $link['uri']) {
                        $report->addLine(array('','','','<'.$link['uri'].'>'));
                    }
                }
                if (!$links) {
                    $report->addLine(array('','','== No links =='));
                    $report->addLine();
                }
            }
        }

        $report->addSummaryLine(sprintf('Total OK.........: %s', $countOk));
        $report->addSummaryLine(sprintf('Total Error......: %s', $countError));
        $report->addSummaryLine(sprintf('Total Not checked: %s', $countNotChecked));

        $text = $report->getText();

        // write report
        $outputDir = $this->app['publishing.dir.output'];
        $reportFile = $outputDir . '/report-LinkCheckPlugin.txt';

        file_put_contents($reportFile, $text);
    }
}

