<?php

namespace Trefoil\Plugins;

use Easybook\Events\BaseEvent;
use Easybook\Events\EasybookEvents;
use Easybook\Events\ParseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Trefoil\Helpers\QuizActivity;
use Trefoil\Helpers\QuizActivityParser;
use Trefoil\Helpers\QuizItem;
use Trefoil\Helpers\QuizQuestionnaireParser;
use Trefoil\Util\SimpleReport;

class EbookQuizPlugin extends BasePlugin implements EventSubscriberInterface
{
    /**
     * The name of the book element that will receive the rendered solutions
     */
    const QUIZ_SOLUTIONS_ELEMENT = 'ebook-quiz-solutions';

    /**
     *
     * @var Array|QuizItem
     */
    protected $quizElements = array();

    /**
     * @var array
     */
    protected $xrefs = array();

    /**
     * Whether or not the solutions item has been generated
     *
     * @var bool
     */
    protected $generated = false;

    static public function getSubscribedEvents()
    {
        return array(
            EasybookEvents::PRE_PARSE => array('onItemPreParse', +100),
            EasybookEvents::POST_PARSE   => array('onItemPostParse', -100),
            EasybookEvents::POST_PUBLISH => 'onPostPublish');
    }

    public function onItemPreParse(ParseEvent $event)
    {
        $this->init($event);

        if (self::QUIZ_SOLUTIONS_ELEMENT == $this->item['config']['element']) {
            // prepare to render the solutions into this element
            $this->prepareSolutions();
        }
    }
    
    public function onItemPostParse(ParseEvent $event)
    {
        $this->init($event);

        if (self::QUIZ_SOLUTIONS_ELEMENT != $this->item['config']['element']) {
            // a normal item that can contain quiz elements
            $this->processItem();
        }

        // reload changed item
        $event->setItem($this->item);
    }

    public function onPostPublish(BaseEvent $event)
    {
        $this->init($event);

        // create the processing report
        $this->createReport();
    }

    /**
     * Parse the quiz elements in the current item and render them.
     *
     * The quiz elements are stored for later rendering of solutions.
     */
    protected function processItem()
    {
        // capture quiz elements
        $regExp = '/';
        $regExp .= '(?<div>';
        $regExp .= '<div[^(class|>)]*';
        $regExp .= 'class="(?<type>activity|questions)"';
        $regExp .= '.*';
        $regExp .= '<\/div>)';
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $me = $this;
        $content = preg_replace_callback(
            $regExp,
            function ($matches) use ($me) {
                // PRUEBAS
                //echo "#### QUIZ ELEMENT SOURCE HTML ##########################################\n";
                //print_r($matches['div']);

                $html = '';
                switch ($matches['type']) {
                    case 'activity':
                        $html = $me->processActivityType($matches['div']);
                        break;
                    case 'questions':
                        $html = $me->processQuestionnaireType($matches['div']);
                        break;
                    default:
                        // unrecognized type
                        // TODO: create error report
                        return $matches[0];
                }


                //echo "#### QUIZ ELEMENT RENDERIZADO #####################################\n";
                //print_r($html);

                return $html;
            },
            $this->item['content']
        );

        $this->item['content'] = $content;
    }

    /**
     * Parse the quiz element of type QuizActivity and return a rendered version.
     *
     * @param $sourceHtml
     *
     * @return string
     */
    protected function processActivityType($sourceHtml)
    {
        $validYes = $this->getEditionOption('plugins.options.EbookQuiz.ynb.yes');
        $validNo = $this->getEditionOption('plugins.options.EbookQuiz.ynb.no');
        $validBoth = $this->getEditionOption('plugins.options.EbookQuiz.ynb.both');

        $parser = new QuizActivityParser($sourceHtml);
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

        // save it for rendering the solutions later
        $this->quizElements[] = $activity;

        // and for reporting
        //$this->saveXref($activity);

        // PRUEBAS
        //echo "#### ACTIVIDAD PARSEADA ########################################\n";
        //print_r($activity);

        // render it
        $variables = array('activity' => $activity);
        $html = $this->app['twig']->render('ebook-quiz-activity.twig', $variables);

        return $html;
    }

    /**
     * Parse the quiz element of type QuizQuestionnaire and return a rendered version.
     *
     * @param $sourceHtml
     *
     * @return string
     */
    protected function processQuestionnaireType($sourceHtml)
    {

        $parser = new QuizQuestionnaireParser($sourceHtml);

        $questionnaire = $parser->parse();

        // save it for rendering the solutions later
        $this->quizElements[] = $questionnaire;

        // and for reporting
        //$this->saveXref($questionnaire);

        // PRUEBAS
        //echo "#### QUESTIONNAIRE PARSEADO ########################################\n";
        //print_r($questionnaire);

        // render it
        $variables = array('questionnaire' => $questionnaire);
        $html = $this->app['twig']->render('ebook-quiz-questionnaire.twig', $variables);

        return $html;
    }

    /**
     * Save the information for the "solutions" book element to be rendered.
     * 
     * The rendering will be done by the publisher at decoration time (or when
     * the book is assembled, depending on the implementation).
     */
    protected function prepareSolutions()
    {
        $this->app['publishing.quiz.items'] = $this->quizElements;
       
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
                 "No glossary has been generated, check for missing 'auto-glosssary' contents element.",
                 "error"
            );
        }

        $outputDir = $this->app['publishing.dir.output'];
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

        $auxTerm = '';
        $auxVariant = '';
        foreach ($this->processedGlossary as $term => $data) {
            $auxTerm = $term;
            foreach ($data->getXref() as $variant => $items) {
                $auxVariant = $variant;
                foreach ($items as $item => $count) {
                    $report->addline(array($auxTerm, $auxVariant, $item, $count, $data->getSource()));
                    $auxTerm = '';
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
