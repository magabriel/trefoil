<?php

namespace Trefoil\Plugins;

use Easybook\Events\BaseEvent;
use Easybook\Events\EasybookEvents;
use Easybook\Events\ParseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Trefoil\Helpers\Activity;
use Trefoil\Helpers\ActivityParser;
use Trefoil\Util\SimpleReport;

class EbookActivitiesPlugin extends BasePlugin implements EventSubscriberInterface
{

    /**
     *
     * @var Array|Activity 
     */
    protected $activities = array();
    
    /**
     * @var array
     */
    protected $xrefs = array();

    /**
     * Whether or not the solutions item has been generated
     * @var bool
     */
    protected $generated = false;

    /*     * *******************************************************************************
     * Event handlers
     * ********************************************************************************
     */

    static public function getSubscribedEvents()
    {
        return array(
            EasybookEvents::PRE_PARSE    => array('onItemPreParse', +100),
            EasybookEvents::POST_PARSE   => array('onItemPostParse', -100),
            EasybookEvents::POST_PUBLISH => 'onPostPublish');
    }

    public function onItemPostParse(ParseEvent $event)
    {
        $this->init($event);

        $this->processItem();

        // reload changed item
        $event->setItem($this->item);
    }

    public function onItemPreParse(ParseEvent $event)
    {
        $this->init($event);

        if ('ebook-activities-solutions' == $this->item['config']['element']) {
            $this->saveSolutions();
        }
    }

    public function onPostPublish(BaseEvent $event)
    {
        $this->init($event);

        // create the processing report
        $this->createReport();
    }
    /*     * *******************************************************************************
     * Implementation
     * ********************************************************************************
     */

    /**
     * For a content item to be processed for activities, replace activities with the
     * interactive version.
     */
    protected function processItem()
    {
        $this->processActivities();

    }

    protected function processActivities()
    {
        // capture activities
        $regExp = '/';
        $regExp .= '(?<div>';
        $regExp .= '<div[^(class|>)]*';
        $regExp .= 'class="activity"';
        $regExp .= '.*';
        $regExp .= '<\/div>)';
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $validYes = $this->getEditionOption('plugins.options.EbookActivities.ynb.yes');
        $validNo = $this->getEditionOption('plugins.options.EbookActivities.ynb.no');
        $validBoth = $this->getEditionOption('plugins.options.EbookActivities.ynb.both');
        
        $me      = $this;
        $content = preg_replace_callback($regExp,
                function ($matches) use ($me, $validYes, $validNo, $validBoth)
                {
                    // PRUEBAS
                    //echo "#### ACTIVIDAD HTML ##########################################\n";
                    //print_r($matches['div']);

                    $parser = new ActivityParser($matches['div']);
                    if ($validYes) {
                        $parser->setResponsesValidAsYes($validYes);
                    }
                    if ($validNo) {
                        $parser->setResponsesValidAsNo($validNo);
                    }
                    if ($validBoth) {
                        $parser->setResponsesValidAsBoth($validBoth);
                    }
                    
                    $activity = $parser->parse();

                    // save it for later
                    $this->activities[] = $activity;

                    // and for reporting
                    //$this->saveXref($activity);

                    // PRUEBAS
                    //echo "#### ACTIVIDAD PARSEADA ########################################\n";
                    //print_r($activity);
                    
                    // convert it
                    $htmlCode = $me->renderActivity($activity);

                    //echo "#### ACTIVIDAD RENDERIZADA #####################################\n";
                    //print_r($htmlCode);
                    
                    return $htmlCode;
                }, $this->item['content']);

        $this->item['content'] = $content;
    }

    /**
     * Render the activity to HTML
     * 
     * @param Activity $activity
     * 
     * @return string HTML representation of activity
     */
    protected function renderActivity(Activity $activity)
    {
        $html = '';
        
        $variables = array('activity' => $activity);

        $html = $this->app['twig']->render('activity.twig', $variables);

        return $html;
    }
    
    /**
     * Save the activities to be generated on item rendering
     */
    protected function saveSolutions()
    {
        $this->app['publishing.activities'] = $this->activities;

        $this->generated = true;
    }

    /**
     * Writes the report with the summary of processing done.
     */
    protected function createReport()
    {
        return;

        $report = '';
        $report .= $this->getUsedTermsReport();
        $report .= "\n\n";
        $report .= $this->getNotUsedTermsReport();

        if (!$this->generated) {
            $this->writeLn(
                "No glossary has been generated, check for missing 'auto-glosssary' contents element.", "error");
        }

        $outputDir  = $this->app['publishing.dir.output'];
        $reportFile = $outputDir . '/report-AutoGlossaryPlugin.txt';

        file_put_contents($reportFile, $report);
    }

    protected function getUsedTermsReport()
    {
        $report = new SimpleReport();
        $report->setTitle('AutoGlossaryPlugin');
        $report->setSubtitle('Used terms');

        $report->addIntroLine('Coverage: ' . $this->glossaryOptions['coverage']);
        $report->addIntroLine('Elements: ' . '"' . join('", "', $this->glossaryOptions['elements']) . '"');

        $report->setHeaders(array('Term', 'Variant', 'Item', 'Count', 'Source'));

        $report->setColumnsWidth(array(30, 30, 30, 5, 30));
        $report->setColumnsAlignment(array('', '', '', 'right', ''));

        $auxTerm    = '';
        $auxVariant = '';
        foreach ($this->processedGlossary as $term => $data) {
            $auxTerm = $term;
            foreach ($data->getXref() as $variant => $items) {
                $auxVariant = $variant;
                foreach ($items as $item => $count) {
                    $report->addline(array($auxTerm, $auxVariant, $item, $count, $data->getSource()));
                    $auxTerm    = '';
                    $auxVariant = '';
                }
            }
        }

        return $report->getText();
    }

    protected function getNotUsedTermsReport()
    {
        $report = new SimpleReport();
        $report->setTitle('AutoGlossaryPlugin');
        $report->setSubtitle('Not used terms');

        $report->setHeaders(array('Term', 'Source'));

        $report->setColumnsWidth(array(30, 30));

        foreach ($this->processedGlossary as $term => $data) {
            if (!count($data->getXref($term))) {
                $report->addLine(array($term, $data->getSource()));
            }
        }

        return $report->getText();
    }
}
