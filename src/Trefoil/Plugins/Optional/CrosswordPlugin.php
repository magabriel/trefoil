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
use ReflectionException;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Finder\Finder;
use Trefoil\Exception\PluginException;
use Trefoil\Helpers\Crossword;
use Trefoil\Helpers\CrosswordRenderer;
use Trefoil\Helpers\TrefoilMarkerProcessor;
use Trefoil\Plugins\BasePlugin;
use Trefoil\Util\SimpleReport;

/**
 * Class CrosswordPlugin
 *
 * The provided functions use a "trefoil marker" syntax ('{@..@}' blocks).
 * Expected syntax:
 *      {@ crossword_begin (..arguments..) @}
 *
 *      {@ crossword_end (..arguments..) @}
 *
 * @see     Crossword
 * @package Trefoil\Plugins\Optional
 */
class CrosswordPlugin extends BasePlugin implements EventSubscriberInterface
{

    protected array $problems = [];

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EasybookEvents::PRE_PARSE => ['onItemPreParse', -100], // after TwigExtensionPlugin
            EasybookEvents::POST_PARSE => ['onItemPostParse', -1100], // after ParserPlugin
            EasybookEvents::POST_PUBLISH => 'onPostPublish',
        ];
    }

    /**
     * @param ParseEvent $event
     * @throws PluginException
     * @throws ReflectionException
     */
    public function onItemPreParse(ParseEvent $event)
    {
        $this->init($event);

        $content = $event->getItemProperty('original');

        $content = $this->processTrefoilMarkers($content);

        $this->savePluginOptions();

        $event->setItemProperty('original', $content);
    }

    /**
     * @param $content
     * @return string|null
     */
    protected function processTrefoilMarkers($content): ?string
    {
        $this->crosswordCalls = 0;

        $processor = new TrefoilMarkerProcessor();

        $processor->registerMarker(
            'crossword',
            function (int $id, array $arguments = []) {
                $arguments['id'] = $id;

                $itemsArguments = $this->app['publishing.crossword.arguments'] ?? [];
                $itemsArguments[$id]['puzzle'] = $arguments;
                $this->app['publishing.crossword.arguments'] = $itemsArguments;

                return sprintf(
                    '<div class="crossword crossword-puzzle" data-id="%s" markdown="1"></div>',
                    $id,
                );
            }
        );

        $processor->registerMarker(
            'crossword_clues_across',
            function (int $id, array $arguments = []) {
                $arguments['id'] = $id;

                $itemsArguments = $this->app['publishing.crossword.arguments'] ?? [];
                $itemsArguments[$id]['clues-across'] = $arguments;
                $this->app['publishing.crossword.arguments'] = $itemsArguments;

                return sprintf(
                    '<div class="crossword crossword-clues-across" data-id="%s" markdown="1"></div>',
                    $id,
                );
            }
        );

        $processor->registerMarker(
            'crossword_clues_down',
            function (int $id, array $arguments = []) {
                $arguments['id'] = $id;

                $itemsArguments = $this->app['publishing.crossword.arguments'] ?? [];
                $itemsArguments[$id]['clues-down'] = $arguments;
                $this->app['publishing.crossword.arguments'] = $itemsArguments;

                return sprintf(
                    '<div class="crossword crossword-clues-down" data-id="%s" markdown="1"></div>',
                    $id,
                );
            }
        );

        $processor->registerMarker(
            'crossword_solution',
            function (int $id, array $arguments = []) {
                $arguments['id'] = $id;

                $itemsArguments = $this->app['publishing.crossword.arguments'] ?? [];
                $itemsArguments[$id]['solution'] = $arguments;
                $this->app['publishing.crossword.arguments'] = $itemsArguments;

                return sprintf(
                    '<div class="crossword crossword-solution" data-id="%s" markdown="1"></div>',
                    $id
                );
            }
        );

        $processor->registerMarker(
            'crossword_anagram_word',
            function (int $id, array $arguments = []) {
                $arguments['id'] = $id;

                $itemsArguments = $this->app['publishing.crossword.arguments'] ?? [];
                $itemsArguments[$id]['anagram'] = $arguments;
                $this->app['publishing.crossword.arguments'] = $itemsArguments;

                return sprintf(
                    '<div class="crossword crossword-anagram-word" data-id="%s" markdown="1"></div>',
                    $id
                );
            }
        );

        $processor->registerMarker(
            'crossword_anagram_clue',
            function (int $id, array $arguments = []) {
                $arguments['id'] = $id;

                $itemsArguments = $this->app['publishing.crossword.arguments'] ?? [];
                $itemsArguments[$id]['anagram-clue'] = $arguments;
                $this->app['publishing.crossword.arguments'] = $itemsArguments;

                return sprintf(
                    '<div class="crossword crossword-anagram-clue" data-id="%s" markdown="1"></div>',
                    $id
                );
            }
        );

        return $processor->parse($content);
    }


    /**
     * Save plugin options to be used in the crossword CSS theme file
     */
    protected function savePluginOptions()
    {

        // For theme files
        $gridSize = $this->getEditionOption('plugins.options.Crossword.grid_size');
        if ($gridSize) {
            $this->app['publishing.plugins.options.Crossword.grid_size'] = $gridSize;
        }

        $solutionGridSize = $this->getEditionOption('plugins.options.Crossword.solution_grid_size');
        if ($solutionGridSize) {
            $this->app['publishing.plugins.options.Crossword.solution_grid_size'] = $solutionGridSize;
        }
    }

    /**
     * @param ParseEvent $event
     */
    public function onItemPostParse(ParseEvent $event)
    {
        $this->init($event);

        $this->processCrosswords();

        $event->setItem($this->item);
    }

    protected function processCrosswords()
    {
        $this->processPuzzle();
        $this->processCluesAcross();
        $this->processCluesDown();
        $this->processSolution();
        $this->processAnagramClue();
        $this->processAnagramWord();
    }

    protected function processPuzzle()
    {
        $regExp = '/'
            . '(?<div>'
            . '<div +(?<pre>[^>]*)'
            . 'class="(?<class>crossword crossword-puzzle)"'
            . ' +'
            . 'data-id="(?<id>\d+)"'
            . '(?<post>[^>]*)>'
            . ')' // div group
            . '.*'
            . '<\/div>'
            . '/Ums'; // Ungreedy, multiline, dotall

        $content = preg_replace_callback(
            $regExp,
            function ($matches) {

                $id = $matches['id'];
                $itemsArguments = $this->app['publishing.crossword.arguments'];
                if (!isset($itemsArguments[$id])) {
                    $this->writeLn(sprintf('Puzzle with id "%s" not found.', $id), 'error');
                    $this->saveProblem(sprintf('Puzzle with id "%s" not found.', $id), 'error');

                    return sprintf('ERROR: Puzzle with id "%s" not found.', $id);
                }
                $arguments = $itemsArguments[$id]['puzzle'];
                $title = $arguments['title'] ?? $this->getEditionOption('plugins.options.Crossword.strings.title') ?? '';
                $text = $arguments['text'] ?? $this->getEditionOption('plugins.options.Crossword.strings.text') ?? '';

                if (!isset($arguments['ipuz_file']) || $arguments['ipuz_file'] === '') {
                    $this->writeLn(sprintf('ipuz_file argument is required for Puzzle with id "%s".', $id), 'error');
                    $this->saveProblem(sprintf('ipuz_file argument is required for Puzzle with id "%s".', $id), 'error');

                    return sprintf('ERROR: ipuz_file argument is required for Puzzle with id "%s".', $id);
                }

                $ipuzFolder = $this->getEditionOption('plugins.options.Crossword.ipuz_folder');
                if (!isset($ipuzFolder) || $ipuzFolder === '') {
                    $this->writeLn('ipuz_folder argument is required', 'error');
                    $this->saveProblem('ipuz_folder argument is required', 'error');

                    return sprintf('ERROR: ipuz_folder argument is required');
                }

                // Relative to the Contents folder
                $dir = $this->app['publishing.dir.contents'];
                $file = $dir . '/' . $ipuzFolder . '/' . $arguments['ipuz_file'];

                return $this->renderPuzzle($file, (int) $id, $title, $text);
            },
            $this->item['content']
        );

        $this->item['content'] = $content;
    }

    protected function renderPuzzle(string $ipuzFile, int $id, string $title, string $text): string
    {
        $crosswordRenderer = new CrosswordRenderer();
        $crosswordRenderer->loadIpuz($ipuzFile);
        $success = $crosswordRenderer->render();

        if (!$success) {
            if ($crosswordRenderer->getErrors()) {
                foreach ($crosswordRenderer->getErrors() as $error) {
                    $this->writeLn(sprintf('Puzzle %s: %s', $id, $error), 'error');
                    $this->saveProblem(sprintf('Puzzle %s: %s', $id, $error), 'error');
                }
            }
        }
        if ($crosswordRenderer->getWarnings()) {
            foreach ($crosswordRenderer->getWarnings() as $warning) {
                $this->writeLn(sprintf('Puzzle %s: %s', $id, $warning), 'warning');
                $this->saveProblem(sprintf('Puzzle %s: %s', $id, $warning), 'warning');
            }
        }

        $items = $this->app['publishing.crossword.items'] ?? [];

        $items[$id] = [
            'solution-html' => $success ? $crosswordRenderer->getSolutionHtml() : '',
            'clues-across-html' => $success ? $crosswordRenderer->getCluesAcrossHtml(true) : '',
            'clues-down-html' => $success ? $crosswordRenderer->getCluesDownHtml(true) : '',
            'anagram-word' => $success ? $crosswordRenderer->getAnagramWord() : '',
            'anagram-clue' => $success ? $crosswordRenderer->getAnagramClue() : '',
        ];
        $this->app['publishing.crossword.items'] = $items;

        return sprintf(
            '<div class="crossword crossword-puzzle-container" data-id="%s">' .
            '<div class="crossword-title">%s</div>' .
            '<div class="crossword-text">%s</div>' .
            '<div class="crossword-puzzle">%s</div>' .
            '</div>',
            $id,
            sprintf($title, $id, $id),
            $text,
            $crosswordRenderer->getPuzzleHtml()
        );
    }

    protected function processCluesAcross()
    {
        $regExp = '/'
            . '(?<div>'
            . '<div +(?<pre>[^>]*)'
            . 'class="(?<class>crossword crossword-clues-across)"'
            . ' +'
            . 'data-id="(?<id>\d+)"'
            . '(?<post>[^>]*)>'
            . ')' // div group
            . '.*'
            . '<\/div>'
            . '/Ums'; // Ungreedy, multiline, dotall

        $content = preg_replace_callback(
            $regExp,
            function ($matches) {

                $id = $matches['id'];

                $itemsArguments = $this->app['publishing.crossword.arguments'];
                if (!isset($itemsArguments[$id])) {
                    $this->writeLn(sprintf('Puzzle with id "%s" not found.', $id), 'error');

                    return sprintf('ERROR: Puzzle with id "%s" not found.', $id);
                }
                $arguments = $itemsArguments[$id]['clues-across'];

                $acrossTitle = $arguments['title'] ?? $this->getEditionOption('plugins.options.Crossword.strings.across_title') ?? '';

                if (!isset($this->app['publishing.crossword.items'])) {
                    return '';
                }
                $items = $this->app['publishing.crossword.items'] ?? [];
                if (!isset($items[$id])) {
                    return '';
                }

                return sprintf(
                    '<div class="crossword crossword-clues-across-container" data-id="%s">' .
                    '<div class="crossword crossword-clues-across-title">%s</div>' .
                    '<div class="crossword crossword-clues-across">%s</div>' .
                    '</div>',
                    $id,
                    $acrossTitle,
                    $items[$id]['clues-across-html'] ?? ''
                );
            },
            $this->item['content']
        );

        $this->item['content'] = $content;
    }

    protected function processCluesDown()
    {
        $regExp = '/'
            . '(?<div>'
            . '<div +(?<pre>[^>]*)'
            . 'class="(?<class>crossword crossword-clues-down)"'
            . ' +'
            . 'data-id="(?<id>\d+)"'
            . '(?<post>[^>]*)>'
            . ')' // div group
            . '.*'
            . '<\/div>'
            . '/Ums'; // Ungreedy, multiline, dotall

        $content = preg_replace_callback(
            $regExp,
            function ($matches) {

                $id = $matches['id'];

                $itemsArguments = $this->app['publishing.crossword.arguments'];
                if (!isset($itemsArguments[$id])) {
                    $this->writeLn(sprintf('Puzzle with id "%s" not found.', $id), 'error');

                    return sprintf('ERROR: Puzzle with id "%s" not found.', $id);
                }
                $arguments = $itemsArguments[$id]['clues-down'];

                $downTitle = $arguments['title'] ?? $this->getEditionOption('plugins.options.Crossword.strings.down_title') ?? '';

                if (!isset($this->app['publishing.crossword.items'])) {
                    return '';
                }
                $items = $this->app['publishing.crossword.items'] ?? [];
                if (!isset($items[$id])) {
                    return '';
                }

                return sprintf(
                    '<div class="crossword crossword-clues-down-container" data-id="%s">' .
                    '<div class="crossword crossword-clues-down-title">%s</div>' .
                    '<div class="crossword crossword-clues-down">%s</div>' .
                    '</div>',
                    $id,
                    $downTitle,
                    $items[$id]['clues-down-html'] ?? ''
                );
            },
            $this->item['content']
        );

        $this->item['content'] = $content;
    }

    protected function processAnagramClue()
    {
        $regExp = '/'
            . '(?<div>'
            . '<div +(?<pre>[^>]*)'
            . 'class="(?<class>crossword crossword-anagram-clue)"'
            . ' +'
            . 'data-id="(?<id>\d+)"'
            . '(?<post>[^>]*)>'
            . ')' // div group
            . '.*'
            . '<\/div>'
            . '/Ums'; // Ungreedy, multiline, dotall

        $content = preg_replace_callback(
            $regExp,
            function ($matches) {
                $id = $matches['id'];

                $itemsArguments = $this->app['publishing.crossword.arguments'];
                if (!isset($itemsArguments[$id])) {
                    $this->writeLn(sprintf('Puzzle with id "%s" not found.', $id), 'error');

                    return sprintf('ERROR: Puzzle with id "%s" not found.', $id);
                }
                $arguments = $itemsArguments[$id]['anagram-clue'] ?? [];
                $anagramClueTitle = $arguments['title'] ?? $this->getEditionOption('plugins.options.Crossword.strings.anagram_clue_title') ?? '';

                if (!isset($this->app['publishing.crossword.items'])) {
                    return '';
                }
                $items = $this->app['publishing.crossword.items'] ?? [];
                if (!isset($items[$id])) {
                    return '';
                }

                // Print nothing if there is no clue
                if (empty($items[$id]['anagram-clue'])) {
                    return '';
                }

                return sprintf(
                    '<div class="crossword crossword-anagram-clue-container" data-id="%s">' .
                    '<div class="crossword crossword-anagram-clue-title">%s</div>' .
                    '<div class="crossword crossword-anagram-clue">%s</div>' .
                    '</div>',
                    $id,
                    $anagramClueTitle,
                    $items[$id]['anagram-clue']
                );
            },
            $this->item['content']
        );

        $this->item['content'] = $content;
    }

    protected function processAnagramWord()
    {
        $regExp = '/'
            . '(?<div>'
            . '<div +(?<pre>[^>]*)'
            . 'class="(?<class>crossword crossword-anagram-word)"'
            . ' +'
            . 'data-id="(?<id>\d+)"'
            . '(?<post>[^>]*)>'
            . ')' // div group
            . '.*'
            . '<\/div>'
            . '/Ums'; // Ungreedy, multiline, dotall

        $content = preg_replace_callback(
            $regExp,
            function ($matches) {

                $id = $matches['id'];

                $itemsArguments = $this->app['publishing.crossword.arguments'];
                if (!isset($itemsArguments[$id])) {
                    $this->writeLn(sprintf('Puzzle with id "%s" not found.', $id), 'error');

                    return sprintf('ERROR: Puzzle with id "%s" not found.', $id);
                }
                $arguments = $itemsArguments[$id]['anagram-word'] ?? [];
                $anagramWordTitle = $arguments['title'] ?? $this->getEditionOption('plugins.options.Crossword.strings.anagram_word_title') ?? '';

                if (!isset($this->app['publishing.crossword.items'])) {
                    return '';
                }
                $items = $this->app['publishing.crossword.items'] ?? [];
                if (!isset($items[$id])) {
                    return '';
                }

                // Print nothing if there is no clue
                if (empty($items[$id]['anagram-word'])) {
                    return '';
                }

                return sprintf(
                    '<div class="crossword crossword-anagram-word-container" data-id="%s">' .
                    '<div class="crossword crossword-anagram-word-title">%s</div>' .
                    '<div class="crossword crossword-anagram-word">%s</div>' .
                    '</div>',
                    $id,
                    $anagramWordTitle,
                    $items[$id]['anagram-word']
                );
            },
            $this->item['content']
        );

        $this->item['content'] = $content;
    }

    protected function processSolution()
    {
        $regExp = '/'
            . '(?<div>'
            . '<div +(?<pre>[^>]*)'
            . 'class="(?<class>crossword crossword-solution)"'
            . ' +'
            . 'data-id="(?<id>\d+)"'
            . '(?<post>[^>]*)>'
            . ')' // div group
            . '.*'
            . '<\/div>'
            . '/Ums'; // Ungreedy, multiline, dotall

        $content = preg_replace_callback(
            $regExp,
            function ($matches) {

                $id = $matches['id'];

                $itemsArguments = $this->app['publishing.crossword.arguments'];
                if (!isset($itemsArguments[$id])) {
                    $this->writeLn(sprintf('Puzzle with id "%s" not found.', $id), 'error');

                    return sprintf('ERROR: Puzzle with id "%s" not found.', $id);
                }
                $arguments = $itemsArguments[$id]['solution'];

                $title = $arguments['solution_title'] ?? $this->getEditionOption('plugins.options.Crossword.strings.solution_title') ?? '';

                if (!isset($this->app['publishing.crossword.items'])) {
                    return '';
                }
                $items = $this->app['publishing.crossword.items'] ?? [];
                if (!isset($items[$id])) {
                    return '';
                }

                return sprintf(
                    '<div class="crossword crossword-solution-container" data-id="%s">' .
                    '<div class="crossword-title">%s</div>' .
                    '<div class="crossword-solution">%s</div>' .
                    '</div>',
                    $id,
                    sprintf($title, $id, $id),
                    $items[$id]['solution-html'] ?? ''
                );
            },
            $this->item['content']
        );

        $this->item['content'] = $content;
    }

    public function onPostPublish(BaseEvent $event)
    {
        $this->init($event);

        $this->createReport();
    }

    protected function saveProblem(
        string $message,
        string $severity
    ) {
        $problem = [];

        $problem['message'] = $message;
        $problem['severity'] = $severity;

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
        $reportFile = $outputDir . '/report-CrosswordPlugin.txt';

        $report = new SimpleReport();
        $report->setTitle('CrosswordPlugin');
        $report->setSubtitle('Problems found');

        $report->setHeaders(['Element', 'Severity', 'Message']);
        $report->setColumnsWidth([20, 10, 100]);

        $count = 0;
        foreach ($this->problems as $element => $problems) {
            $report->addLine();
            $report->addLine($element);
            foreach ($problems as $problem) {
                $count++;
                $report->addLine(
                    [
                        '',
                        strtoupper($problem['severity']),
                        $problem['message'],
                    ]
                );
            }
        }

        if ($count === 0) {
            $report->addSummaryLine('No problems found');
        } else {
            $report->addSummaryLine(sprintf('%s problems found', $count));
        }

        file_put_contents($reportFile, $report->getText());
    }
}
