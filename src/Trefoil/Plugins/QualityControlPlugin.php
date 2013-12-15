<?php
namespace Trefoil\Plugins;

use Trefoil\Util\SimpleReport;

use Symfony\Component\Finder\Finder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Easybook\Events\EasybookEvents;
use Easybook\Events\BaseEvent;
use Easybook\Events\ParseEvent;

/**
 * This plugin performs several checks on the finished book to help
 * fixing common problems.
 *
 * - Markdown emphasis marks (_ and *) not processed.
 * - Unused images.
 */
class QualityControlPlugin extends BasePlugin implements EventSubscriberInterface
{
    protected $images = array();
    protected $problems = array();

    public static function getSubscribedEvents()
    {
        return array(
                EasybookEvents::POST_PARSE => array('onItemPostParse', -9999), // Latest
                EasybookEvents::POST_PUBLISH => 'onPostPublish'
        );
    }

    /* ********************************************************************************
     * Event handlers
    * ********************************************************************************
    */
    public function onItemPostParse(BaseEvent $event)
    {
        $this->init($event);

        $content = $event->getItemProperty('content');

        $this->checkImages($content);
        $this->checkEmphasis($content);
    }

    public function onPostPublish(BaseEvent $event)
    {
        $this->init($event);

        $this->createReport();
    }

    /* ********************************************************************************
     * Implementation
    * ********************************************************************************
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
    protected function extractImages($string)
    {
        // find all the images
        $regExp = '/<img(.*)\/?>/Us';
        preg_match_all($regExp, $string, $imagesMatch, PREG_SET_ORDER);

        $images = array();
        foreach ($imagesMatch as $imageMatch) {
            // get all attributes
            $regExp2 = '/(?<attr>.*)="(?<value>.*)"/Us';
            preg_match_all($regExp2, $imageMatch[1], $attrMatches, PREG_SET_ORDER);

            $attributes = array();
            foreach ($attrMatches as $attrMatch) {
                $attributes[trim($attrMatch['attr'])] = $attrMatch['value'];
            }

            $images[] = $attributes;
        }

        return $images;
    }

    public function checkEmphasis($content)
    {
        $emphasis = $this->extractEmphasis($content);

        foreach ($emphasis as $emph) {
            $this->saveProblem($emph, 'emphasis', 'Emphasis mark not processed');
        }
    }

    protected function extractEmphasis($string)
    {
        $noBlanks = '[\w\.,\-\(\)\:;"]';

        // find all the Markdown emphasis marks that have made it out to HTML
        $regExp = '/(?<em>'; // start capturing group
        $regExp.= '\s[_\*]+(?:[^\s_\*]+?)'; // underscore or asterisk after a space
        $regExp.= '|';
        $regExp.= '(?:[^\s_\*]+)[_\*]+\s'; // underscore or asterisk before a space
        $regExp.= '|';
        $regExp.= '(?:'.$noBlanks.'+\*+'.$noBlanks.'+)'; // asterisk between no spaces
        $regExp.= ')/Ums';
        preg_match_all($regExp, $string, $matches, PREG_SET_ORDER);

        $emphasis = array();
        foreach ($matches as $match) {
            $emphasis[] = $match['em'];
        }

        return $emphasis;
    }

    protected function saveProblem($object, $type, $message)
    {
        $problem = array();

        $problem['object'] = $object;
        $problem['type'] = $type;
        $problem['message'] = $message;

        $element = $this->item['config']['content'];
        $element = $element ? $element : $this->item['config']['element'];
        if (!isset($this->problems[$element])) {
            $this->problems[$element] = array();
        }

        $this->problems[$element][] = $problem;
    }

    protected function saveImage($image)
    {
        if (!isset($this->images[$image['src']])) {
            $this->images[$image['src']] = 0;
        }

        $this->images[$image['src']]++;
    }

    protected function createReport()
    {
        // create the report
        $outputDir = $this->app['publishing.dir.output'];
        $reportFile = $outputDir . '/report-QualityControlPlugin.txt';

        $report = '';
        $report.= $this->getProblemsReport();
        $report.= '';
        $report.= $this->getImagesNotUsedReport();

        file_put_contents($reportFile, $report);
    }

    protected function getProblemsReport()
    {
        $report = new SimpleReport();
        $report->setTitle('QualityControlPlugin');
        $report->setSubtitle('Problems found');

        $report->setHeaders(array('Element', 'Type', 'Object', 'Message'));
        $report->setColumnsWidth(array(10, 10, 30, 100));

        $count = 0;
        foreach ($this->problems as $element => $problems) {
            $report->addLine();
            $report->addLine($element);
            $report->addLine();
            foreach ($problems as $problem) {
                $count++;
                $report->addLine(array('', $problem['type'], $problem['object'], $problem['message']));
            }
        }

        if ($count == 0) {
            $report->addSummaryLine('No problems found');
        } else {
            $report->addSummaryLine(sprintf('%s problems found', $count));
        }

        return $report->getText();
    }

    protected function getImagesNotUsedReport()
    {
        $report = new SimpleReport();
        $report->setTitle('QualityControlPlugin');
        $report->setSubtitle('Images not used');

        $report->setHeaders(array('Image'));
        $report->setColumnsWidth(array(100));

        // check existing images
        $imagesDir =  $this->app['publishing.dir.contents'].'/images';

        $existingImages = array();

        if (file_exists($imagesDir)) {
            $existingFiles= Finder::create()->files()->in($imagesDir)->sortByName();

            foreach ($existingFiles as $image) {
                $name = str_replace($this->app['publishing.dir.contents'].'/', '', $image->getPathname());
                $existingImages[] = $name;
            }
        }

        $count = 0;
        foreach ($existingImages as $image) {
            if (!isset($this->images[$image])) {
                $count++;
                $report->addLine(array($image));
            }
        }

        if ($count == 0) {
            $report->addSummaryLine('No unused images');
        } else {
            $report->addSummaryLine(sprintf('%s unused images found', $count));
        }

        return $report->getText();
    }
}

