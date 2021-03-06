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
use Easybook\Events\ParseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Finder\Finder;
use Trefoil\Plugins\BasePlugin;
use Trefoil\Util\SimpleReport;

/**
 * This plugin performs several checks on the finished book to help
 * fixing common problems.
 * - Markdown emphasis marks (_ and *) not processed.
 * - Unused images.
 */
class QualityControlPlugin extends BasePlugin implements EventSubscriberInterface
{
    protected $images = [];
    /**
     * @var string[][][]
     */
    protected $problems = [];
    protected $problemsFound = false;

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            //EasybookEvents::POST_PARSE   => array('onItemPostParse', -9999), // Latest
            EasybookEvents::POST_DECORATE => ['onItemPostDecorate', -9999], // Latest
            EasybookEvents::POST_PUBLISH  => 'onPostPublish',
        ];
    }

    /* ********************************************************************************
     * Event handlers
    * ********************************************************************************
    */
    /**
     * @param ParseEvent $event
     */
    public function onItemPostParse(ParseEvent $event)
    {
        $this->init($event);

        $content = $event->getItemProperty('content');

        $this->checkImages($content);
        $this->checkEmphasis($content);
    }

    /**
     * @param BaseEvent $event
     */
    public function onItemPostDecorate(BaseEvent $event)
    {
        $this->init($event);

        $content = $this->item['content'];

        $this->checkImages($content);
        $this->checkEmphasis($content);
    }

    /**
     * @param BaseEvent $event
     */
    public function onPostPublish(BaseEvent $event)
    {
        $this->init($event);

        $this->createReport();

        $this->writeResultsMessage();
    }

    /* ********************************************************************************
     * Implementation
    * ********************************************************************************
    */
    /**
     * @param $content
     */
    public function checkImages($content)
    {
        $images = $this->extractImages($content);

        foreach ($images as $image) {
            // save it for later check
            $this->saveImage($image);
        }
    }

    /**
     * Extracts all images in the string
     *
     * @param string $string
     * @return array of images
     */
    protected function extractImages($string): array
    {
        // find all the images
        $regExp = '/<img(.*)\/?>/Us';
        /** @var string[][] $imagesMatch */
        preg_match_all($regExp, $string, $imagesMatch, PREG_SET_ORDER);

        $images = [];

        if ($imagesMatch) {
            foreach ($imagesMatch as $imageMatch) {
                // get all attributes
                $regExp2 = '/(?<attr>.*)="(?<value>.*)"/Us';
                /** @var string[][] $attrMatches */
                preg_match_all($regExp2, $imageMatch[1], $attrMatches, PREG_SET_ORDER);

                $attributes = [];

                if ($attrMatches) {
                    foreach ($attrMatches as $attrMatch) {
                        $attributes[trim($attrMatch['attr'])] = $attrMatch['value'];
                    }
                }

                $images[] = $attributes;
            }
        }

        return $images;
    }

    /**
     * @param $content
     */
    public function checkEmphasis($content)
    {

        // process all the paragraphs
        $regExp = '/';
        $regExp .= '<p>(?<par>.*)<\/p>';
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall
        /** @var string[][] $matches */
        preg_match_all($regExp, $content, $matches, PREG_SET_ORDER);

        if ($matches) {
            foreach ($matches as $match) {
                $emphasis = $this->extractEmphasis($match['par']);
                foreach ($emphasis as $emph) {
                    if (in_array($emph, ['(*)', '"*"'])) {
                        // common strings
                        continue;
                    }
                    if (false !== strpos($emph, '__')) {
                        // a line draw with underscores
                        continue;
                    }

                    $this->saveProblem($emph, 'emphasis', 'Emphasis mark not processed');
                }
            }
        }
    }

    /**
     * @param $string
     * @return array
     */
    protected function extractEmphasis($string): array
    {
        $noBlanks = '[\w\.,\-\(\)\:;"]';

        // find all the Markdown emphasis marks that have made it out to HTML
        $regExp = '/(?<em>'; // start capturing group
        $regExp .= '\s[_\*]+(?:[^\s_\*]+?)'; // underscore or asterisk after a space
        $regExp .= '|';
        $regExp .= '(?:[^\s_\*]+)[_\*]+\s'; // underscore or asterisk before a space
        $regExp .= '|';
        $regExp .= '(?:\s(?:_|\*{1,2})\s)'; // underscore or asterisk or double asterisk surrounded by spaces
        $regExp .= '|';
        $regExp .= '^(?:[_\*]+\s.*$)'; // underscore or asterisk before a space at start of line
        $regExp .= '|';
        $regExp .= '(?:'.$noBlanks.'+\*+'.$noBlanks.'+)'; // asterisk between no spaces
        $regExp .= ')/Ums'; // Ungreedy, multiline, dotall
        /** @var string[][] $matches */
        preg_match_all($regExp, $string, $matches, PREG_SET_ORDER);

        $emphasis = [];

        if ($matches) {
            foreach ($matches as $match) {
                $emphasis[] = $match['em'];
            }
        }

        return $emphasis;
    }

    /**
     * @param $object
     * @param $type
     * @param $message
     */
    protected function saveProblem($object,
                                   $type,
                                   $message)
    {
        $problem = [];

        $problem['object'] = $object;
        $problem['type'] = $type;
        $problem['message'] = $message;

        $element = $this->item['config']['content'];
        $element = $element ?: $this->item['config']['element'];
        if (!isset($this->problems[$element])) {
            $this->problems[$element] = [];
        }

        $this->problems[$element][] = $problem;
    }

    protected function createReport()
    {
        // create the report
        $outputDir = $this->app['publishing.dir.output'];
        $reportFile = $outputDir.'/report-QualityControlPlugin.txt';

        $report = '';
        $report .= $this->getProblemsReport();
        $report .= '';
        $report .= $this->getImagesNotUsedReport();

        file_put_contents($reportFile, $report);
    }

    /**
     * @param $image
     */
    protected function saveImage($image)
    {
        if (!isset($this->images[$image['src']])) {
            $this->images[$image['src']] = 0;
        }

        $this->images[$image['src']]++;
    }

    /**
     * @return string
     */
    protected function getProblemsReport(): string
    {
        $report = new SimpleReport();
        $report->setTitle('QualityControlPlugin');
        $report->setSubtitle('Problems found');

        $report->setHeaders(['Element', 'Type', 'Object', 'Message']);
        $report->setColumnsWidth([10, 10, 30, 100]);

        $count = 0;
        foreach ($this->problems as $element => $problems) {
            $report->addLine();
            $report->addLine($element);
            $report->addLine();
            foreach ($problems as $problem) {
                $count++;
                $report->addLine(
                    [
                        '',
                        $problem['type'],
                        substr($problem['object'], 0, 30),
                        $problem['message'],
                    ]);
            }
        }

        if ($count === 0) {
            $report->addSummaryLine('No problems found');
        } else {
            $report->addSummaryLine(sprintf('%s problems found', $count));
            $this->problemsFound = true;
        }

        return $report->getText();
    }

    /**
     * @return string
     */
    protected function getImagesNotUsedReport(): string
    {
        $report = new SimpleReport();
        $report->setTitle('QualityControlPlugin');
        $report->setSubtitle('Images not used');

        $report->setHeaders(['Image']);
        $report->setColumnsWidth([100]);

        // check existing images
        $imagesDir = $this->app['publishing.dir.contents'].'/images';

        $existingImages = [];

        if (file_exists($imagesDir)) {
            $existingFiles = Finder::create()->files()->in($imagesDir)->sortByName();

            foreach ($existingFiles as $image) {
                $name = str_replace($this->app['publishing.dir.contents'].'/', '', $image->getPathname());
                $existingImages[] = $name;
            }
        }

        $count = 0;
        foreach ($existingImages as $image) {
            if (!isset($this->images[$image])) {
                $count++;
                $report->addLine([$image]);
            }
        }

        if ($count === 0) {
            $report->addSummaryLine('No unused images');
        } else {
            $report->addSummaryLine(sprintf('%s unused images found', $count));
            $this->problemsFound = true;
        }

        return $report->getText();
    }

    /**
     * Write a message to the user about problems found.
     */
    protected function writeResultsMessage()
    {
        if (!$this->problemsFound) {
            $this->writeLn('No quality problems');
        } else {
            $this->writeLn('Some quality problems detected', 'warning');
        }
    }
}
