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
use Trefoil\Helpers\QuizActivity;
use Trefoil\Helpers\QuizActivityParser;
use Trefoil\Helpers\QuizItem;
use Trefoil\Helpers\QuizQuestionnaire;
use Trefoil\Helpers\QuizQuestionnaireParser;
use Trefoil\Plugins\BasePlugin;
use Trefoil\Util\SimpleReport;

/**
 * Class EbookQuizPlugin
 *
 * @package Trefoil\Plugins\Optional
 */
class EbookQuizPlugin extends BasePlugin implements EventSubscriberInterface
{
    /**
     * The name of the book element that will receive the rendered solutions
     */
    public const QUIZ_SOLUTIONS_ELEMENT = 'ebook-quiz-solutions';

    /**
     * @var QuizItem[][]
     */
    protected $quizItems = [];

    /**
     * Whether or not the solutions item has been generated
     *
     * @var bool
     */
    protected $generated = false;

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EasybookEvents::PRE_PARSE    => ['onItemPreParse', +100],
            EasybookEvents::POST_PARSE   => ['onItemPostParse', -1100], // after ParserPlugin
            EasybookEvents::POST_PUBLISH => 'onPostPublish',
        ];
    }

    /**
     * @param ParseEvent $event
     */
    public function onItemPreParse(ParseEvent $event)
    {
        $this->init($event);

        if (self::QUIZ_SOLUTIONS_ELEMENT === $this->item['config']['element']) {
            // prepare to render the solutions into this element
            $this->prepareSolutions();
        }
    }

    /**
     * Save the information for the "solutions" book element to be rendered.
     * The rendering will be done by the publisher at decoration time (or when
     * the book is assembled, depending on the implementation).
     */
    protected function prepareSolutions()
    {
        $this->app['publishing.quiz.items'] = $this->quizItems;

        $this->generated = true;
    }

    /**
     * @param ParseEvent $event
     */
    public function onItemPostParse(ParseEvent $event)
    {
        $this->init($event);

        if (self::QUIZ_SOLUTIONS_ELEMENT !== $this->item['config']['element']) {
            // a normal item that can contain quiz elements
            $this->processItem();
        }

        // reload changed item
        $event->setItem($this->item);
    }

    /**
     * Parse the quiz elements in the current item and render them.
     * The quiz elements are stored for later rendering of solutions.
     */
    protected function processItem()
    {
        $quizElements = [
            'activity', // backwards compatibility
            'questions', // backwards compatibility
            'quiz-activity',
            'quiz-questionnaire',
        ];

        // capture quiz elements
        $regExp = '/';
        $regExp .= '(?<div>';
        $regExp .= '<div[^(class|>)]*';
        $regExp .= sprintf('class="(?<type>%s)"', implode('|', $quizElements));

        // can have other divs embedded (i.e. figures or tables already decorated)
        $regExp .= '(';
        $regExp .= '(<div.*>.*<\/div>)|'; // a single div
        $regExp .= '.'; // anything
        $regExp .= ')*';

        $regExp .= '<\/div>)';
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $content = preg_replace_callback(
            $regExp,
            function ($matches) {
                $html = '';
                switch ($matches['type']) {
                    case 'activity':
                    case 'quiz-activity':
                        $html = $this->internalProcessActivityType($matches['div']);
                        break;
                    case 'questions':
                    case 'quiz-questionnaire':
                        $html = $this->internalProcessQuestionnaireType($matches['div']);
                        break;
                }

                return $html;
            },
            $this->item['content']);

        $this->item['content'] = $content;
    }

    /**
     * Parse the quiz element of type QuizActivity and return a rendered version.
     *
     * @param $sourceHtml
     * @return string
     * @throws \Exception
     */
    protected function internalProcessActivityType($sourceHtml): string
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
        $this->saveQuizItem($activity);

        // render it
        $variables = ['activity' => $activity];

        return $this->app['twig']->render('ebook-quiz-activity.twig', $variables);
    }

    /**
     * Save a quiz item xref for reporting.
     *
     * @param QuizItem $quizItem
     */
    protected function saveQuizItem(QuizItem $quizItem)
    {
        // save the xref to this item
        $quizItem->setXref($this->item['config']['content']);

        // assign a name for grouping items
        $name = $this->item['title'];
        if ($this->item['label']) {
            $name = $this->item['label'].' - '.$name;
        }

        if (!isset($this->quizItems[$name])) {
            $this->quizItems[$name] = [];
        }

        $this->quizItems[$name][] = $quizItem;
    }

    /**
     * Parse the quiz element of type QuizQuestionnaire and return a rendered version.
     *
     * @param $sourceHtml
     * @return string
     * @throws \Exception
     */
    protected function internalProcessQuestionnaireType($sourceHtml): string
    {
        $parser = new QuizQuestionnaireParser($sourceHtml);

        $questionnaire = $parser->parse();

        // save it for rendering the solutions later
        $this->saveQuizItem($questionnaire);

        // render it
        $variables = ['questionnaire' => $questionnaire];

        return $this->app['twig']->render('ebook-quiz-questionnaire.twig', $variables);
    }

    /**
     * @param BaseEvent $event
     */
    public function onPostPublish(BaseEvent $event)
    {
        $this->init($event);

        $this->checkCompatibility();

        // create the processing report
        $this->createReport();
    }

    protected function checkCompatibility()
    {
        $plugins = $this->getEditionOption('plugins.enabled');

        if (in_array('KindleTweaks', $plugins, true)) {
            $this->writeLn('"KindleTweaks" plugin is enabled. Please disable it to avoid incompatibilities.', 'error');
        }
    }

    /**
     * Writes the report with the summary of processing done.
     */
    protected function createReport()
    {
        if (!$this->generated) {
            $this->writeLn(
                sprintf(
                    "No Quiz has been generated, check for missing '%s' contents element.",
                    self::QUIZ_SOLUTIONS_ELEMENT),
                'error');
        }

        $report = new SimpleReport();
        $report->setTitle('EbookQuizPlugin');
        $report->setSubtitle('Quiz Items');

        $report->setHeaders(['Item title', 'X-Ref', 'Id', 'Type', 'Heading', 'Questions']);

        $report->setColumnsWidth([40, 20, 15, 15, 40, 9]);
        $report->setColumnsAlignment(['', '', '', '', '', 'right']);

        foreach ($this->quizItems as $item => $quizItems) {
            /** @var QuizItem[] $quizItems */
            $auxItem = $item;
            $auxXref = isset($quizItems[0]) ? $quizItems[0]->getXref() : '';

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

                $report->addLine(
                    [
                        substr($auxItem, 0, 40),
                        $auxXref,
                        $quizItem->getId(),
                        $quizItem->getType(),
                        substr($quizItem->getHeading(), 0, 40),
                        $count,
                    ]);
                $auxItem = '';
                $auxXref = '';
            }
        }

        $outputDir = $this->app['publishing.dir.output'];
        $reportFile = $outputDir.'/report-EbookQuizPlugin.txt';

        file_put_contents($reportFile, $report->getText());

    }

}
