<?php
/*
 * This file is part of the trefoil application.
 *
 * (c) Miguel Angel Gabriel <magabriel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Trefoil\Plugins;

use Easybook\Events\BaseEvent;
use Easybook\Events\EasybookEvents;
use Easybook\Events\ParseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Trefoil\Helpers\QuizActivity;
use Trefoil\Helpers\QuizActivityParser;
use Trefoil\Helpers\QuizItem;
use Trefoil\Helpers\QuizQuestionnaire;
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

    public static function getSubscribedEvents()
    {
        return array(
            EasybookEvents::PRE_PARSE    => array('onItemPreParse', +100),
            EasybookEvents::POST_PARSE   => array('onItemPostParse', -1100), // after ParserPlugin
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
        $quizElements = array(
            'activity', // backwards compatibility
            'questions', // backwards compatibility
            'quiz-activity',
            'quiz-questionnaire'
        );

        // capture quiz elements
        $regExp = '/';
        $regExp .= '(?<div>';
        $regExp .= '<div[^(class|>)]*';
        $regExp .= sprintf('class="(?<type>%s)"', implode('|', $quizElements));
        $regExp .= '.*';
        $regExp .= '<\/div>)';
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $me = $this;
        $content = preg_replace_callback(
            $regExp,
            function ($matches) use ($me) {
                $html = '';
                switch ($matches['type']) {
                    case 'activity':
                    case 'quiz-activity':
                        $html = $me->processActivityType($matches['div']);
                        break;
                    case 'questions':
                    case 'quiz-questionnaire':
                        $html = $me->processQuestionnaireType($matches['div']);
                        break;
                }

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

        // save xref for reporting
        $this->saveXref($activity);

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

        // save xref for reporting
        $this->saveXref($questionnaire);

        // render it
        $variables = array('questionnaire' => $questionnaire);
        $html = $this->app['twig']->render('ebook-quiz-questionnaire.twig', $variables);

        return $html;
    }

    /**
     * Save a quiz item xref for reporting.
     *
     * @param QuizItem $quizItem
     */
    protected function saveXref(QuizItem $quizItem)
    {
        $name = $this->item['config']['content'];

        if (!isset($this->xrefs[$name])) {
            $this->xrefs[$name] = array();
        }

        $this->xrefs[$name][] = $quizItem;
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
        if (!$this->generated) {
            $this->writeLn(
                 "No glossary has been generated, check for missing 'auto-glosssary' contents element.",
                 "error"
            );
        }

        $report = new SimpleReport();
        $report->setTitle('EbookQuizPlugin');
        $report->setSubtitle('Quiz Items');

        $report->setHeaders(array('Item', 'Id', 'Type', 'Heading', 'Questions'));

        $report->setColumnsWidth(array(30, 15, 15, 40, 9));
        $report->setColumnsAlignment(array('', '', '', '', 'right'));

        foreach ($this->xrefs as $item => $quizItems) {
            $auxItem = $item;

            /** @var QuizItem $quizItem */
            foreach ($quizItems as $quizItem) {

                switch ($quizItem->getType()) {
                    case QuizActivity::QUIZ_ACTIVITY_TYPE_ABC:
                    case QuizActivity::QUIZ_ACTIVITY_TYPE_YNB:
                        /** @var QuizActivity $activity */
                        $activity = $quizItem;
                        $count = count($activity->getQuestions());
                        break;
                    case QuizQuestionnaire::QUIZ_QUESTIONNAIRE:
                        /** @var QuizQuestionnaire $questionnaire */
                        $questionnaire = $quizItem;
                        $count = count($questionnaire->getQuestions());
                        break;
                    default:
                        $count = null;
                }

                $report->addline(
                       array($auxItem,
                             $quizItem->getId(),
                             $quizItem->getType(),
                             substr($quizItem->getHeading(), 0, 40),
                             $count)
                );
                $auxItem = '';
            }
        }

        $outputDir = $this->app['publishing.dir.output'];
        $reportFile = $outputDir . '/report-EbookQuizPlugin.txt';

        file_put_contents($reportFile, $report->getText());

    }

}
