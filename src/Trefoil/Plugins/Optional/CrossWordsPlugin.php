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
use Trefoil\Helpers\CrossWords;
use Trefoil\Helpers\TrefoilMarkerProcessor;
use Trefoil\Plugins\BasePlugin;
use Trefoil\Util\SimpleReport;

/**
 * Class CrossWordsPlugin
 *
 * The provided functions use a "trefoil marker" syntax ('{@..@}' blocks).
 * Expected syntax:
 *      {@ crosswords (..arguments..) @}
 *
 *      {@ crosswords_solutions (..arguments..) @}
 *
 * @see     CrossWords
 * @package Trefoil\Plugins\Optional
 */
class CrossWordsPlugin extends BasePlugin implements EventSubscriberInterface {

    protected static array $wordFiles = [];
    protected int $crosswordsCalls = 0;
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
        $this->crosswordsCalls = 0;

        $processor = new TrefoilMarkerProcessor();

        $processor->registerMarker(
                'crosswords',
                function (
                        int $id,
                        array $arguments = []
                ) {
                    $arguments['id'] = $id;

                    $itemsArguments = $this->app['publishing.crosswords.arguments'] ?? [];
                    $itemsArguments[$id]['puzzle'] = $arguments;
                    $this->app['publishing.crosswords.arguments'] = $itemsArguments;

                    return sprintf(
                    '<div class="crosswords crosswords-puzzle-simple" data-id="%s" markdown="1"></div>', $id);
                });

        $processor->registerMarker(
                'crosswords_begin',
                function (
                        int $id,
                        array $arguments = []
                ) {
                    $arguments['id'] = $id;

                    $this->crosswordsCalls++;

                    $itemsArguments = $this->app['publishing.crosswords.arguments'] ?? [];
                    $itemsArguments[$id]['puzzle'] = $arguments;
                    $this->app['publishing.crosswords.arguments'] = $itemsArguments;

                    return sprintf(
                    '<div class="crosswords crosswords-puzzle" data-id="%s" markdown="1">', $id);
                });

        $processor->registerMarker(
                'crosswords_end',
                function () {
                    $this->crosswordsCalls--;

                    return '</div>';
                });

        $processor->registerMarker(
                'crosswords_wordlist',
                function (
                        int $id,
                        array $arguments = []
                ) {
                    $arguments['id'] = $id;

                    $itemsArguments = $this->app['publishing.crosswords.arguments'] ?? [];
                    $itemsArguments[$id] ['wordlist'] = $arguments;
                    $this->app['publishing.crosswords.arguments'] = $itemsArguments;

                    return sprintf(
                    '<div class="crosswords crosswords-wordlist" data-id="%s" markdown="1"></div>', $id);
                });

        $processor->registerMarker(
                'crosswords_solution',
                function (
                        int $id,
                        array $arguments = []
                ) {
                    $arguments['id'] = $id;

                    $itemsArguments = $this->app['publishing.crosswords.arguments'] ?? [];
                    $itemsArguments[$id] ['solution'] = $arguments;
                    $this->app['publishing.crosswords.arguments'] = $itemsArguments;

                    return sprintf(
                    '<div class="crosswords crosswords-solution" data-id="%s" markdown="1"></div>', $id);
                });

        return $processor->parse($content);
    }

    protected function savePluginOptions()
    {
        $gridSize = $this->getEditionOption('plugins.options.CrossWords.grid_size');
        if ($gridSize) {
            $this->app['publishing.plugins.options.CrossWords.grid_size'] = $gridSize;
        }

        $solutionGridSize = $this->getEditionOption('plugins.options.CrossWords.solution_grid_size');
        if ($solutionGridSize) {
            $this->app['publishing.plugins.options.CrossWords.solution_grid_size'] = $solutionGridSize;
        }

        $highlightType = $this->getEditionOption('plugins.options.CrossWords.highlight_type');
        if ($highlightType) {
            $this->app['publishing.plugins.options.CrossWords.highlight_type'] = $highlightType;
        }
    }

    protected function checkBalancedCalls()
    {
        if ($this->crosswordsCalls > 0) {
            $this->writeLn('crosswords_begin() call without ending previous.', 'error');
        }

        if ($this->crosswordsCalls < 0) {
            $this->writeLn('crosswords_end() call without crosswords_begin().', 'error');
        }
    }

    /**
     * @param ParseEvent $event
     */
    public function onItemPostParse(ParseEvent $event)
    {
        $this->init($event);

        $this->processCrossWordses();

        $event->setItem($this->item);
    }

    protected function processCrossWordses()
    {
        $this->processPuzzle();
        $this->processWordList();
        $this->processSolution();
    }

    protected function processPuzzle()
    {
        $regExp = '/'
                . '(?<div>'
                . '<div +(?<pre>[^>]*)'
                . 'class="(?<class>crosswords crosswords-puzzle(?<simple>-simple)?)"'
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
                    $id = $matches['id'];
                    $isSimple = (bool) $matches['simple'];
                    $itemsArguments = $this->app['publishing.crosswords.arguments'];
                    if (!isset($itemsArguments[$id])) {
                        $this->writeLn(sprintf('Puzzle with id "%s" not found.', $id), 'error');
                        $this->saveProblem(sprintf('Puzzle with id "%s" not found.', $id), 'error');

                        return sprintf('ERROR: Puzzle with id "%s" not found.', $id);
                    }
                    $arguments = $itemsArguments[$id]['puzzle'];
                    $rows = $arguments['rows'] ?? 15;
                    $cols = $arguments['cols'] ?? 15;
                    $title = $arguments['title'] ?? $this->getEditionOption('plugins.options.CrossWords.strings.title') ?? '';
                    $text = $arguments['text'] ?? $this->getEditionOption('plugins.options.CrossWords.strings.text') ?? '';
                    $text2 = $arguments['text2'] ?? $this->getEditionOption('plugins.options.CrossWords.strings.text2') ?? '';
                    $difficulty = $arguments['difficulty'] ?? $this->getEditionOption('plugins.options.CrossWords.default.difficulty')
                                ?? CrossWords::DIFFICULTY_EASY;

                    if ($isSimple) {
                        $wordFile = $arguments['word_file'] ?? '';
                        $numberOfWords = $arguments['number_of_words'] ?? 0;
                        $words = CrossWords::DEFAULT_WORDS;
                        if ($wordFile) {
                            $words = $this->readWordsFromFile($wordFile);
                        }
                    } else {
                        $words = $this->parsePuzzleWords($matches['content']);
                        if (!$words) {
                            $this->writeLn(sprintf('No words found for puzzle id "%s".', $id), 'error');
                            $this->saveProblem(sprintf('No words found for puzzle id "%s".', $id), 'error');
                        }
                        $numberOfWords = 0;
                    }

                    $seed = $arguments['seed'] ?? intval(1000 + $id);

                    $timeStart = microtime(true);
                    $this->write(sprintf('Generating puzzle id "%s".', $id), 'info');

                    $crossWords = new CrossWords();
                    $crossWords->setRandomSeed($seed);
                    $success = $crossWords->generate($rows, $cols, $words, $numberOfWords, $difficulty);

                    $timeEnd = microtime(true);
                    $this->writeLn(sprintf(' %.2f sec. with %s tries.', $timeEnd - $timeStart,
                                    number_format($crossWords->getTotalTries())), 'plain');

                    if (!$success) {
//                        $this->writeLn(sprintf('Puzzle %s generated with error.', $id), 'error');
                        if ($crossWords->getErrors()) {
                            foreach ($crossWords->getErrors() as $error) {
                                $this->writeLn(sprintf('Puzzle %s: %s', $id, $error), 'error');
                                $this->saveProblem(sprintf('Puzzle %s: %s', $id, $error), 'error');
                            }
                        }
                    }
                    if ($crossWords->getWarnings()) {
                        foreach ($crossWords->getWarnings() as $warning) {
                            $this->writeLn(sprintf('Puzzle %s: %s', $id, $warning), 'warning');
                            $this->saveProblem(sprintf('Puzzle %s: %s', $id, $warning), 'warning');
                        }
                    }

                    $items = $this->app['publishing.crosswords.items'] ?? [];

                    $items[$id] = [
                        'solution' => $crossWords->solutionAsHtml(),
                        'wordlist' => [
                            'sorted-1-chunk' => $crossWords->wordListAsHtml(true),
                            'unsorted-1-chunk' => $crossWords->wordListAsHtml(false),
                            'sorted-2-chunk' => $crossWords->wordListAsHtml(true, 2),
                            'unsorted-2-chunk' => $crossWords->wordListAsHtml(false, 2),
                            'sorted-3-chunk' => $crossWords->wordListAsHtml(true, 3),
                            'unsorted-3-chunk' => $crossWords->wordListAsHtml(false, 3),
                            'sorted-4-chunk' => $crossWords->wordListAsHtml(true, 4),
                            'unsorted-4-chunk' => $crossWords->wordListAsHtml(false, 4),
                        ],
                    ];
                    $this->app['publishing.crosswords.items'] = $items;

                    $difficultyTextEasy = $this->getEditionOption(
                            'plugins.options.CrossWords.strings.difficulty.easy', 'Difficulty: Easy');
                    $difficultyTextMedium = $this->getEditionOption(
                            'plugins.options.CrossWords.strings.difficulty.medium', 'Difficulty: Medium');
                    $difficultyTextHard = $this->getEditionOption(
                            'plugins.options.CrossWords.strings.difficulty.hard', 'Difficulty: Hard');
                    $difficultyTextVeryHard = $this->getEditionOption(
                            'plugins.options.CrossWords.strings.difficulty.very-hard', 'Difficulty: Very Hard');

                    switch ($difficulty) {
                        case CrossWords::DIFFICULTY_EASY:
                            $difficultyText = $difficultyTextEasy;
                            break;
                        case CrossWords::DIFFICULTY_MEDIUM:
                            $difficultyText = $difficultyTextMedium;
                            break;
                        case CrossWords::DIFFICULTY_HARD:
                            $difficultyText = $difficultyTextHard;
                            break;
                        case CrossWords::DIFFICULTY_VERY_HARD:
                            $difficultyText = $difficultyTextVeryHard;
                            break;
                    }

                    return sprintf(
                    '<div class="crosswords crosswords-puzzle-container" data-id="%s">' .
                    '<div class="crosswords-title">%s</div>' .
                    '<div class="crosswords-text">%s</div>' .
                    '<div class="crosswords-text2">%s</div>' .
                    '<div class="crosswords-difficulty">%s</div>' .
                    '<div class="crosswords-puzzle">%s</div>' .
                    '</div>', $arguments['id'], sprintf($title, $id, $id), $text, $text2, $difficultyText,
                    $crossWords->puzzleAsHtml());
                }, $this->item['content']);

        $this->item['content'] = $content;
    }

    protected function processWordList()
    {
        $regExp = '/'
                . '(?<div>'
                . '<div +(?<pre>[^>]*)'
                . 'class="(?<class>crosswords crosswords-wordlist)"'
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

                    $itemsArguments = $this->app['publishing.crosswords.arguments'];
                    if (!isset($itemsArguments[$id])) {
                        $this->writeLn(sprintf('Puzzle with id "%s" not found.', $id), 'error');

                        return sprintf('ERROR: Puzzle with id "%s" not found.', $id);
                    }
                    $arguments = $itemsArguments[$id]['wordlist'];
                    $sorted = $arguments['sorted'] ?? false;
                    $chunks = $arguments['chunks'] ?? 1;

                    if ($chunks > 4) {
                        $this->writeLn(
                                sprintf('Puzzle with id "%s": chunks value (%s) out of range.', $id, $chunks), 'error');
                        $chunks = 1;
                    }

                    if (!isset($this->app['publishing.crosswords.items'])) {
                        return '';
                    }
                    $items = $this->app['publishing.crosswords.items'] ?? [];
                    if (!isset($items[$id])) {
                        return '';
                    }

                    $wordlist = $items[$id]['wordlist'];

                    $wordlistKey = sprintf(
                            "%s-%d-chunk", $sorted ? 'sorted' : 'unsorted', $chunks);

                    return sprintf(
                    '<div class="crosswords crosswords-wordlist" data-id="%s">%s</div>', $arguments['id'], $wordlist[$wordlistKey]);
                }, $this->item['content']);

        $this->item['content'] = $content;
    }

    protected function processSolution()
    {
        $regExp = '/'
                . '(?<div>'
                . '<div +(?<pre>[^>]*)'
                . 'class="(?<class>crosswords crosswords-solution)"'
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

                    $itemsArguments = $this->app['publishing.crosswords.arguments'];
                    if (!isset($itemsArguments[$id])) {
                        $this->writeLn(sprintf('Puzzle with id "%s" not found.', $id), 'error');

                        return sprintf('ERROR: Puzzle with id "%s" not found.', $id);
                    }
                    $arguments = $itemsArguments[$id]['solution'];

                    $title = $arguments['title'] ?? $this->getEditionOption('plugins.options.CrossWords.strings.solution_title') ?? '';

                    $text = $arguments['text'] ?? '';

                    if (!isset($this->app['publishing.crosswords.items'])) {
                        return '';
                    }
                    $items = $this->app['publishing.crosswords.items'] ?? [];
                    if (!isset($items[$id])) {
                        return '';
                    }

                    return sprintf(
                    '<div class="crosswords crosswords-solution-container" data-id="%s">' .
                    '<div class="crosswords-title">%s</div>' .
                    '<div class="crosswords-text">%s</div>' .
                    '<div class="crosswords-solution">%s</div>' .
                    '</div>', $arguments['id'], sprintf($title, $id, $id), $text, $items[$id]['solution']);
                }, $this->item['content']);

        $this->item['content'] = $content;
    }

    protected function readWordsFromFile($wordFile): array
    {
        $files = $this->getEditionOption('plugins.options.CrossWords.word_files', []);
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
                                        $contentsDir . DIRECTORY_SEPARATOR . $file['name'], PATHINFO_DIRNAME));
                    }
                }

                $filePath = $this->app->getFirstExistingFile($fileName, $dirs);
                if (!$filePath) {
                    $this->writeLn(
                            sprintf('Word file with name "%s" not found in "%s".', $file['name'], $contentsDir), 'error');

                    return [];
                }

                $allWords = file($filePath, FILE_IGNORE_NEW_LINES + FILE_SKIP_EMPTY_LINES);
                if (!$allWords) {
                    $this->writeLn(sprintf('Word file with name "%s" is empty.', $file['name']), 'error');
                }

                static::$wordFiles[$wordFile] = $allWords;

                return $allWords;
            }
        }

        $this->writeLn(sprintf('Word file with label "%s" not defined.', $wordFile), 'error');

        return [];
    }

    protected function parsePuzzleWords($content): array
    {
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
                function (Crawler $liNode) use
                (
                        &$output
                ) {
                    if ($liNode->children()->count() == 0) {
                        $output [] = $liNode->html();

                        return;
                    }

                    $cellText = '';

                    $liNode->children()->each(
                            function (Crawler $liChildrenNode) use
                            (
                                    &$cellText
                            ) {
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
            string $severity)
    {
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
        $reportFile = $outputDir . '/report-CrossWordsPlugin.txt';

        $report = new SimpleReport();
        $report->setTitle('CrossWordsPlugin');
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
                ]);
            }
        }

        if ($count === 0) {
            $report->addSummaryLine('No problems found');
        } else {
            $report->addSummaryLine(sprintf('%s problems found', $count));
            $this->problemsFound = true;
        }

        file_put_contents($reportFile, $report->getText());
    }

}
