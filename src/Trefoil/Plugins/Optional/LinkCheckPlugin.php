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

namespace Trefoil\Plugins\Optional;

use Easybook\Events\BaseEvent;
use Easybook\Events\EasybookEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Trefoil\Helpers\LinkChecker;
use Trefoil\Plugins\BasePlugin;
use Trefoil\Util\SimpleReport;

/**
 * Plugin to check internal and external links in book.
 * Internal links are checked by looking for a valid link target, i.e. an html element
 * whith id="the-link-target" in the whole book.
 * External links are (optionally) checked for existence by performing a network lookup.
 * This behaviour is off by default because could be very time consuming if the book
 * has a large number of external links. To turn it on, set the following option in
 * the book's config.yml:
 *     editions:
 *         <edition-name>
 *             plugins:
 *                 ...
 *                 options:
 *                     LinkCheck:
 *                         check_external_links: true
 * The plugin will generate a report in the output directory for all the links
 * in the book with its status from the check (OK or error).
 */
class LinkCheckPlugin extends BasePlugin implements EventSubscriberInterface
{
    /**
     * @var string[][][][] $links
     */
    protected $links = [
        'internal' => [],
        'external' => [],
    ];

    protected $linkTargets = [];

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EasybookEvents::POST_DECORATE => ['onItemPostDecorate', -1100], // the latest possible
            EasybookEvents::POST_PUBLISH  => ['onPostPublish', -1100] // the latest possible
        ];
    }

    /**
     * @param BaseEvent $event
     */
    public function onItemPostDecorate(BaseEvent $event)
    {
        $this->init($event);

        // retrieve all the links for this item
        $links = $this->findLinks($this->item['content'], $this->item['config']['content']);
        $this->links = array_merge_recursive($this->links, $links);

        // retrieve all the internal link targets for this item
        $linkTargets = $this->findLinkTargets($this->item['content']);
        $this->linkTargets = array_merge_recursive($this->linkTargets, $linkTargets);
    }

    /**
     * @param BaseEvent $event
     */
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
     * @param        $xref
     * @return array
     */
    protected function findLinks($content,
                                 $xref): array
    {
        $links = [
            'internal' => [
                $xref => [],
            ],
            'external' => [
                $xref => [],
            ],
        ];

        preg_match_all('/<a .*href="(?<uri>.*)".*>(?<text>.*)<\/a>/Ums', $content, $matches, PREG_SET_ORDER);

        /** @var string[] $matches */
        if ($matches) {
            /** @var string[][] $match */
            foreach ($matches as $match) {
                $link = [
                    'text' => $match['text'],
                    'uri'  => $match['uri'],
                ];

                if ('#' === substr($match['uri'], 0, 1)) {
                    $links['internal'][$xref][] = $link;
                } else {
                    $links['external'][$xref][] = $link;
                }
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
    protected function findLinkTargets($content): array
    {
        $linkTargets = [];

        /** @var string[][] $matches */
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
        $errors = false;

        foreach ($this->links['internal'] as $xref => $links) {
            foreach ($links as $index => $link) {
                if (!in_array(substr($link['uri'], 1), $this->linkTargets, true)) {
                    $this->links['internal'][$xref][$index]['status'] = 'Not found';
                    $errors = true;
                } else {
                    $this->links['internal'][$xref][$index]['status'] = 'OK';
                }
            }
        }

        if ($errors) {
            $this->writeLn('Some internal links are not correct.', 'error');
        }
    }

    /**
     * Check external links for existence
     */
    protected function checkExternalLinks()
    {
        if (!$this->getEditionOption('plugins.options.LinkCheck.check_external_links')) {
            return;
        }

        $checker = new LinkChecker();

        $this->writeLn('Checking external links....');

        $errors = false;

        $numLinks = 0;
        foreach ($this->links['external'] as $links) {
            $numLinks += count($links);
        }

        $this->progressStart($numLinks);
        foreach ($this->links['external'] as $xref => $links) {
            foreach ($links as $index => $link) {
                try {
                    $checker->check($link['uri']);
                    $this->links['external'][$xref][$index]['status'] = 'OK';
                } catch (\Exception $e) {
                    $this->links['external'][$xref][$index]['status'] = $e->getMessage();
                    $errors = true;
                }
                $this->progressAdvance();
            }
        }
        $this->progressFinish();

        if ($errors) {
            $this->writeLn('Some external links are not correct.', 'error');
        } else {
            $this->writeLn('All external links are correct.');
        }
    }

    protected function createReport()
    {
        $report = new SimpleReport();
        $report->setTitle('LinkCheckPlugin');

        $checkExternalLinks = $this->getEditionOption('plugins.options.LinkCheck.check_external_links');
        $report->setSubtitle(sprintf('check_external_links: %s', $checkExternalLinks ? 'true' : 'false'));

        $report->setHeaders(
            [
                'Type',
                'Item',
                'Status',
                'Link',
            ]);
        $report->setColumnsWidth(
            [
                8,
                4,
                20,
                100,
            ]);

        $countOk = 0;
        $countError = 0;
        $countNotChecked = 0;

        foreach ($this->links as $type => $linksByType) {

            $report->addLine([ucfirst($type)]);
            $report->addLine();

            foreach ($linksByType as $xref => $links) {
                $report->addLine(['', $xref]);
                $report->addLine();
                foreach ($links as $link) {

                    $status = $link['status'] ?? 'Not checked';

                    if ('OK' === $status) {
                        $countOk++;
                    } elseif ('Not checked' === $status) {
                        $countNotChecked++;
                    } else {
                        $countError++;
                    }

                    $report->addLine(['', '', $status, trim($link['text'])]);
                    if ($link['text'] !== $link['uri']) {
                        $report->addLine(['', '', '', '<'.$link['uri'].'>']);
                    }
                }
                if (!$links) {
                    $report->addLine(['', '', '== No links ==']);
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
        $reportFile = $outputDir.'/report-LinkCheckPlugin.txt';

        file_put_contents($reportFile, $text);
    }
}
