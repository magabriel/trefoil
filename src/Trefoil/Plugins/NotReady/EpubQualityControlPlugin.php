<?php
namespace Trefoil\Plugins;
use Symfony\Component\Finder\Finder;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Easybook\Events\EasybookEvents as Events;
use Easybook\Events\BaseEvent;
use Easybook\Events\ParseEvent;

class EpubQualityControlPlugin implements EventSubscriberInterface
{
    protected $app;
    protected $output;
    protected $item;

    protected static $images = array();
    protected static $problems = array();

    public static function getSubscribedEvents()
    {
        return array(
                Events::POST_PARSE => array(
                        'onItemPostParse',
                        -9999 // Latest
                ),
                Events::POST_PUBLISH => 'onPostPublish'
        );
    }

    public function onItemPostParse(BaseEvent $event)
    {
        $this->app = $event->app;
        $this->output = $this->app->get('console.output');
        $edition = $this->app['publishing.edition'];
        $this->item = $event->getItem();

        $this->app['book.logger']->debug('onItemPostParse:begin', get_class(), $this->item['config']['content']);

        $content = $event->getContent();
        $this->checkImages($content);

        $this->app['book.logger']->debug('onItemPostParse:end', get_class(), $this->item['config']['content']);
    }

    public function checkImages($content)
    {
        $images = $this->extractImages($content);

        foreach ($images as $image) {
            // save it for later check
            $this->saveImage($image);

            // start checks
            $extension = pathinfo($image['src'], PATHINFO_EXTENSION);

            // CHECK: No jpg extension allowed (EPUB validation)
            if ('jpg' == $extension) {
                $this->saveProblem($image['src'], 'image', '".jpg" images not allowed. Use ".jpeg" instead.');
            }
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

    protected function saveProblem($object, $type, $message)
    {
        $problem = array();

        $problem['object'] = $object;
        $problem['type'] = $type;
        $problem['message'] = $message;

        $element = $this->item['config']['content'];
        $element = $element ? $element : $this->item['config']['element'];
        if (!isset(static::$problems[$element])) {
            static::$problems[$element] = array();
        }

        static::$problems[$element][] = $problem;
    }

    protected function saveImage($image)
    {
        if (!isset(static::$images[$image['src']])) {
            static::$images[$image['src']] = 0;
        }

        static::$images[$image['src']]++;
    }

    public function onPostPublish(BaseEvent $event)
    {
        $this->app = $event->app;
        $this->output = $event->app->get('console.output');

        $this->app['book.logger']->debug('onPostPublish:begin', get_class());

        // create the report
        $outputDir = $this->app['publishing.dir.output'];
        $reportFile = $outputDir . '/report-quality-control.txt';

        $report = '';
        $report.= $this->getProblemsReport();
        $report.= '';
        $report.= $this->getImagesNotUsedReport();

        file_put_contents($reportFile, $report);

        $this->app['book.logger']->debug('onPostPublish:end', get_class());
    }

    protected function getProblemsReport()
    {
        $report = array();

        $report[] = 'Quality control: problems found';
        $report[] = '===============================';
        $report[] = '';

        $report[] = $this->utf8Sprintf('%-10s %-30s %-100s', 'Type', 'Object', 'Message');
        $report[] = $this->utf8Sprintf("%'--10s %'--30s %'--100s", '', '', '');

        foreach (static::$problems as $element => $problems) {
            $rep = array();
            $rep[] = 'Element: ' . $element;
            $rep[] = '';
            foreach ($problems as $problem) {
                $rep[] = $this->utf8Sprintf('%-10s %-30s %-100s', $problem['type'], $problem['object'], $problem['message']);
            }
            $rep[] = '';

            $report = array_merge($report, $rep);
        }

        if (count(static::$problems) == 0) {
            $report[] = '';
            $report[] = 'No problems found';
            $report[] = '';
        }

        return implode("\n", $report) . "\n";
    }

    protected function getImagesNotUsedReport()
    {
        $report = array();

        $report[] = 'Quality control: Images not used';
        $report[] = '================================';
        $report[] = '';

        $imagesDir =  $this->app['publishing.dir.contents'].'/images';
        $existingFiles= Finder::create()->files()->exclude('theme_tmp')->in($imagesDir)->sortByName();

        $existingImages = array();
        foreach ($existingFiles as $image) {
            $name = str_replace($this->app['publishing.dir.contents'].'/', '', $image->getPathname());
            $existingImages[] = $name;
        }

        $report[] = $this->utf8Sprintf('%-100s', 'Image');
        $report[] = $this->utf8Sprintf("%'--100s", '');

        $count = 0;
        foreach ($existingImages as $image) {
            if (!isset(static::$images[$image])) {
                $report[] = $this->utf8Sprintf('%-100s', $image);
                $count++;
            }
        }

        if ($count == 0) {
            $report[] = '';
            $report[] = 'No unused images';
            $report[] = '';
        }

        return implode("\n", $report) . "\n";
    }
    protected function utf8Sprintf($format)
    {
        $args = func_get_args();

        for ($i = 1; $i < count($args); $i++) {
            $args[$i] = iconv('UTF-8', 'ISO-8859-15', $args[$i]);
        }

        return iconv('ISO-8859-15', 'UTF-8', call_user_func_array('sprintf', $args));
    }
}

