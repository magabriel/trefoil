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
use Trefoil\Exception\PluginException;
use Trefoil\Helpers\Crossword;
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

    protected static array $wordFiles = [];
    protected int $crosswordCalls = 0;
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

        $this->checkBalancedCalls();
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
            'crossword_begin',
            function (int $id, array $arguments = []) {
                $arguments['id'] = $id;

                $this->crosswordCalls++;

                $itemsArguments = $this->app['publishing.crossword.arguments'] ?? [];
                $itemsArguments[$id]['puzzle'] = $arguments;
                $this->app['publishing.crossword.arguments'] = $itemsArguments;

                return sprintf(
                    '<div class="crossword crossword-puzzle" data-id="%s" markdown="1">',
                    $id
                );
            }
        );

        $processor->registerMarker(
            'crossword_end',
            function () {
                $this->crosswordCalls--;

                return '</div>';
            }
        );

        $processor->registerMarker(
            'crossword_definitions',
            function (int $id, array $arguments = []) {
                $arguments['id'] = $id;

                $itemsArguments = $this->app['publishing.crossword.arguments'] ?? [];
                $itemsArguments[$id]['definitions'] = $arguments;
                $this->app['publishing.crossword.arguments'] = $itemsArguments;

                return sprintf(
                    '<div class="crossword crossword-definitions" data-id="%s" markdown="1"></div>',
                    $id
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

        return $processor->parse($content);
    }

    protected function savePluginOptions()
    {
        $gridSize = $this->getEditionOption('plugins.options.Crossword.grid_size');
        if ($gridSize) {
            $this->app['publishing.plugins.options.Crossword.grid_size'] = $gridSize;
        }

        $solutionGridSize = $this->getEditionOption('plugins.options.Crossword.solution_grid_size');
        if ($solutionGridSize) {
            $this->app['publishing.plugins.options.Crossword.solution_grid_size'] = $solutionGridSize;
        }

        $highlightType = $this->getEditionOption('plugins.options.Crossword.word_files');
        if ($highlightType) {
            $this->app['publishing.plugins.options.Crossword.word_files'] = $highlightType;
        }

        $strings = $this->getEditionOption('plugins.options.Crossword.strings');
        if ($strings) {
            $this->app['publishing.plugins.options.Crossword.strings'] = $strings;
        }
    }

    protected function checkBalancedCalls()
    {
        if ($this->crosswordCalls > 0) {
            $this->writeLn('crossword_begin() call without ending previous.', 'error');
        }

        if ($this->crosswordCalls < 0) {
            $this->writeLn('crossword_end() call without crossword_begin().', 'error');
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
        $this->processDefinitions();
        $this->processSolution();
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
            . '(?<content>.*)'
            . '<\/div>'
            . '/Ums'; // Ungreedy, multiline, dotall

        $content = preg_replace_callback(
            $regExp,
            function ($matches) {
                print_r($matches);

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
                $board = $this->parseBoardLayout($matches['content']);
                if (!$board) {
                    $this->writeLn(sprintf('No board layout found for puzzle id "%s".', $id), 'error');
                    $this->saveProblem(sprintf('No board layout found for puzzle id "%s".', $id), 'error');
                }
                $seed = $arguments['seed'] ?? intval(1000 + $id);

                $timeStart = microtime(true);
                $this->write(sprintf('Generating puzzle id "%s".', $id), 'info');

                $crossword = new Crossword();
                $crossword->setRandomSeed($seed);
                $wordsWithDefinitions = [];
                $wordFile = $arguments['word_file'] ?? null;
                if ($wordFile) {
                    $wordsWithDefinitions = $this->readWordsWithDefinitionsFromFile($wordFile);
                } else {
                    $this->writeLn(sprintf('No word_file defined for puzzle id "%s".', $id), 'error');
                    $this->saveProblem(sprintf('No word_file defined for puzzle id "%s".', $id), 'error');
                }
                $crossword->debug = false;
                $crossword->useDebugFile = false;
                $crossword->debugStatus = true;
                $success = $crossword->generate($wordsWithDefinitions,  $board);

                $timeEnd = microtime(true);
                $this->writeLn(sprintf(
                    ' %.2f sec.',
                    $timeEnd - $timeStart
                ), 'plain');

                if (!$success) {
                    if ($crossword->getErrors()) {
                        foreach ($crossword->getErrors() as $error) {
                            $this->writeLn(sprintf('Puzzle %s: %s', $id, $error), 'error');
                            $this->saveProblem(sprintf('Puzzle %s: %s', $id, $error), 'error');
                        }
                    }
                }
                if ($crossword->getWarnings()) {
                    foreach ($crossword->getWarnings() as $warning) {
                        $this->writeLn(sprintf('Puzzle %s: %s', $id, $warning), 'warning');
                        $this->saveProblem(sprintf('Puzzle %s: %s', $id, $warning), 'warning');
                    }
                }

                $items = $this->app['publishing.crossword.items'] ?? [];

                $items[$id] = [
                    'solution' => $crossword->solutionAsHtml(),
                    'definitions' => $crossword->definitionListAsHtml(
                        $arguments['strings']['across'] ?? 'Across',
                        $arguments['strings']['down'] ?? 'Down'
                    ),
                ];
                $this->app['publishing.crossword.items'] = $items;

                return sprintf(
                    '<div class="crossword crossword-puzzle-container" data-id="%s">' .
                        '<div class="crossword-title">%s</div>' .
                        '<div class="crossword-text">%s</div>' .
                        '<div class="crossword-puzzle">%s</div>' .
                        '</div>',
                    $arguments['id'],
                    sprintf($title, $id, $id),
                    $text,
                    $crossword->puzzleAsHtml()
                );
            },
            $this->item['content']
        );

        $this->item['content'] = $content;
    }

    protected function processDefinitions()
    {
        $regExp = '/'
            . '(?<div>'
            . '<div +(?<pre>[^>]*)'
            . 'class="(?<class>crossword crossword-definitions)"'
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
                $arguments = $itemsArguments[$id]['definitions'];

                if (!isset($this->app['publishing.crossword.items'])) {
                    return '';
                }
                $items = $this->app['publishing.crossword.items'] ?? [];
                if (!isset($items[$id])) {
                    return '';
                }

                $definitions = $items[$id]['definitions'];

                return sprintf(
                    '<div class="crossword crossword-definitions" data-id="%s">%s</div>',
                    $arguments['id'],
                    $definitions
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

                $title = $arguments['title'] ?? $this->getEditionOption('plugins.options.Crossword.strings.solution_title') ?? '';

                $text = $arguments['text'] ?? '';

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
                        '<div class="crossword-text">%s</div>' .
                        '<div class="crossword-solution">%s</div>' .
                        '</div>',
                    $arguments['id'],
                    sprintf($title, $id, $id),
                    $text,
                    $items[$id]['solution']
                );
            },
            $this->item['content']
        );

        $this->item['content'] = $content;
    }

    /**
     * Loads the word list from the defined word files.
     * @param mixed $wordFile
     * @return array
     */
    protected function readWordsWithDefinitionsFromFile($wordFile): array
    {
        $files = $this->getEditionOption('plugins.options.Crossword.word_files', []);
        $contentsDir = realpath($this->app['publishing.dir.book'] . '/Contents');

        foreach ($files as $file) {
            if (!isset($file['label'])) {
                $this->writeLn(sprintf('Word file without a label.', 'error'));

                return [];
            }
            if ($file['label'] === $wordFile) {

                if (isset(static::$wordFiles[$wordFile])) {
                    return static::$wordFiles[$wordFile];
                }

                // Name can contain a path or just a file name (from the Contents directory)
                $dirs = [$contentsDir];
                $fileName = pathinfo($file['name'], PATHINFO_BASENAME);

                $separatorPos = strpos($file['name'], DIRECTORY_SEPARATOR);
                if ($separatorPos !== false) {
                    if ($separatorPos === 0) {
                        // Absolute path
                        $dirs[] = realpath(pathinfo($file['name'], PATHINFO_DIRNAME));
                    } else {
                        // relative path
                        $dirs[] = realpath(
                            pathinfo(
                                $contentsDir . DIRECTORY_SEPARATOR . $file['name'],
                                PATHINFO_DIRNAME
                            )
                        );
                    }
                }

                $filePath = $this->app->getFirstExistingFile($fileName, $dirs);
                if (!$filePath) {
                    $this->writeLn(
                        sprintf('Word file with name "%s" not found in "%s".', $file['name'], $contentsDir),
                        'error'
                    );

                    return [];
                }

                $fileLines = file($filePath, FILE_IGNORE_NEW_LINES + FILE_SKIP_EMPTY_LINES);
                if (!$fileLines) {
                    $this->writeLn(sprintf('Word file with label "%s" is empty.', $file['name']), 'error');
                } else {
                    $this->writeLn(
                        sprintf('Word file with label "%s" loaded with %d entries.', $file['name'], count($fileLines)),
                        'info'
                    );
                }

                $wordsWithDefinitions = [];
                foreach ($fileLines as $line) {
                    $parts = explode(' ', $line, 2);
                    $word = trim($parts[0]);
                    if (empty($word)) {
                        continue;
                    }
                    $definition = trim($parts[1] ?? '');
                    $wordsWithDefinitions[$word] = $definition;
                }

                static::$wordFiles[$wordFile] = $wordsWithDefinitions;

                $this->writeLn(sprintf('Definitions from word file with label "%s" loaded.', $file['name']), 'info');

                return $wordsWithDefinitions;
            }
        }

        $this->writeLn(sprintf('Word file with label "%s" not defined.', $wordFile), 'error');

        return [];
    }

    protected function parseBoardLayout($content): array
    {
        // Ensure the Crawler reads the content with the proper encoding
        $contentEncoding = mb_detect_encoding($content);
        $content = sprintf('<?xml version="1.0" encoding="%s" ?>\n', $contentEncoding) . $content;

        $crawler = new Crawler();
        $crawler->addHtmlContent($content);
        $crawler = $crawler->filter('ul');

        // not an <ul> list in the input
        if ($crawler->count() === 0) {
            return [];
        }

        return $this->parseUl($crawler);
    }

    protected function parseUl(Crawler $crawler): array
    {
        $output = [];

        $crawler->children()->each(
            function (Crawler $liNode) use (&$output) {
                if ($liNode->children()->count() == 0) {
                    $output[] = $liNode->html();

                    return;
                }

                $cellText = '';

                $liNode->children()->each(
                    function (Crawler $liChildrenNode) use (&$cellText) {
                        switch ($liChildrenNode->nodeName()) {
                            case 'p':
                                $cellText = $liChildrenNode->html();
                                break;
                            default:
                                // other tags are ignored
                                break;
                        }
                    }
                );

                // uncollected text
                if (!empty($cellText)) {
                    $output[] = $cellText;
                }
            }
        );

        return $output;
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
