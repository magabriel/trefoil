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

use Easybook\Events\EasybookEvents;
use Easybook\Events\ParseEvent;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Trefoil\Exception\PluginException;
use Trefoil\Helpers\TrefoilMarkerProcessor;
use Trefoil\Helpers\WordSearch;
use Trefoil\Plugins\BasePlugin;

/**
 * Class WordSearchPlugin
 *
 * The provided functions use a "trefoil marker" syntax ('{@..@}' blocks).
 * Expected syntax:
 *      {@ wordsearch (..arguments..) @}
 *
 *      {@ wordsearch_solutions (..arguments..) @}
 *
 * @see     WordSearch
 * @package Trefoil\Plugins\Optional
 */
class WordSearchPlugin extends BasePlugin implements EventSubscriberInterface
{
    protected static array $wordFiles = [];
    protected int $wordsearchCalls = 0;

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EasybookEvents::PRE_PARSE  => ['onItemPreParse', -100], // after TwigExtensionPlugin
            EasybookEvents::POST_PARSE => ['onItemPostParse', -1100], // after ParserPlugin
        ];
    }

    /**
     * @param ParseEvent $event
     * @throws PluginException
     * @throws \ReflectionException
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
        $this->wordsearchCalls = 0;

        $processor = new TrefoilMarkerProcessor();

        $processor->registerMarker(
            'wordsearch',
            function (int   $id,
                      array $arguments = []) {
                $arguments['id'] = $id;

                $itemsArguments = $this->app['publishing.wordsearch.arguments'] ?? [];
                $itemsArguments[$id]['puzzle'] = $arguments;
                $this->app['publishing.wordsearch.arguments'] = $itemsArguments;

                return sprintf(
                    '<div class="wordsearch wordsearch-puzzle-simple" data-id="%s" markdown="1"></div>',
                    $id);
            });

        $processor->registerMarker(
            'wordsearch_begin',
            function (int   $id,
                      array $arguments = []) {
                $arguments['id'] = $id;

                $this->wordsearchCalls++;

                $itemsArguments = $this->app['publishing.wordsearch.arguments'] ?? [];
                $itemsArguments[$id]['puzzle'] = $arguments;
                $this->app['publishing.wordsearch.arguments'] = $itemsArguments;

                return sprintf(
                    '<div class="wordsearch wordsearch-puzzle" data-id="%s" markdown="1">',
                    $id);
            });

        $processor->registerMarker(
            'wordsearch_end',
            function () {
                $this->wordsearchCalls--;

                return '</div>';
            });

        $processor->registerMarker(
            'wordsearch_wordlist',
            function (int   $id,
                      array $arguments = []) {
                $arguments['id'] = $id;

                $itemsArguments = $this->app['publishing.wordsearch.arguments'] ?? [];
                $itemsArguments[$id] ['wordlist'] = $arguments;
                $this->app['publishing.wordsearch.arguments'] = $itemsArguments;

                return sprintf(
                    '<div class="wordsearch wordsearch-wordlist" data-id="%s" markdown="1"></div>',
                    $id);
            });

        $processor->registerMarker(
            'wordsearch_solution',
            function (int   $id,
                      array $arguments = []) {
                $arguments['id'] = $id;

                $itemsArguments = $this->app['publishing.wordsearch.arguments'] ?? [];
                $itemsArguments[$id] ['solution'] = $arguments;
                $this->app['publishing.wordsearch.arguments'] = $itemsArguments;

                return sprintf(
                    '<div class="wordsearch wordsearch-solution" data-id="%s" markdown="1"></div>',
                    $id);
            });

        return $processor->parse($content);
    }

    protected function savePluginOptions()
    {
        $gridSize = $this->getEditionOption('plugins.options.WordSearch.grid_size');
        if ($gridSize) {
            $this->app['publishing.plugins.options.WordSearch.grid_size'] = $gridSize;
        }

        $solutionGridSize = $this->getEditionOption('plugins.options.WordSearch.solution_grid_size');
        if ($solutionGridSize) {
            $this->app['publishing.plugins.options.WordSearch.solution_grid_size'] = $solutionGridSize;
        }
    }

    protected function checkBalancedCalls()
    {
        if ($this->wordsearchCalls > 0) {
            $this->writeLn('wordsearch_begin() call without ending previous.', 'error');
        }

        if ($this->wordsearchCalls < 0) {
            $this->writeLn('wordsearch_end() call without wordsearch_begin().', 'error');
        }
    }

    /**
     * @param ParseEvent $event
     */
    public function onItemPostParse(ParseEvent $event)
    {
        $this->init($event);

        $this->processWordSearches();

        $event->setItem($this->item);
    }

    protected function processWordSearches()
    {
        $this->processPuzzle();
        $this->processWordList();
        $this->processSolution();
    }

    protected function processPuzzle()
    {
        $regExp = '/'
            .'(?<div>'
            .'<div +(?<pre>[^>]*)'
            .'class="(?<class>wordsearch wordsearch-puzzle(?<simple>-simple)?)"'
            .' +'
            .'data-id="(?<id>\d+)"'
            .'(?<post>[^>]*)>'
            .')' // div group
            .'(?<content>.*)'
            .'<\/div>'
            .'/Ums'; // Ungreedy, multiline, dotall

        $content = preg_replace_callback(
            $regExp,
            function ($matches) {
                $id = $matches['id'];
                $isSimple = (bool)$matches['simple'];
                $itemsArguments = $this->app['publishing.wordsearch.arguments'];
                if (!isset($itemsArguments[$id])) {
                    $this->writeLn(sprintf('Puzzle with id "%s" not found.', $id), 'error');

                    return sprintf('ERROR: Puzzle with id "%s" not found.', $id);
                }
                $arguments = $itemsArguments[$id]['puzzle'];
                $rows = $arguments['rows'] ?? 15;
                $cols = $arguments['cols'] ?? 15;
                $filler = $arguments['filler']
                    ?? $this->getEditionOption('plugins.options.WordSearch.default.filler')
                    ?? WordSearch::FILLER_LETTERS_ENGLISH;
                $title = $arguments['title']
                    ?? $this->getEditionOption('plugins.options.WordSearch.strings.title') ?? '';
                $text = $arguments['text']
                    ?? $this->getEditionOption('plugins.options.WordSearch.strings.text') ?? '';
                $text2 = $arguments['text2']
                    ?? $this->getEditionOption('plugins.options.WordSearch.strings.text2') ?? '';
                $difficulty = $arguments['difficulty']
                    ?? $this->getEditionOption('plugins.options.WordSearch.default.difficulty')
                    ?? WordSearch::DIFFICULTY_HARD;

                if ($isSimple) {
                    $wordFile = $arguments['word_file'] ?? '';
                    $numberOfWords = $arguments['number_of_words'] ?? 0;
                    $words = WordSearch::DEFAULT_WORDS;
                    if ($wordFile) {
                        $words = $this->readWordsFromFile($wordFile);
                    }
                } else {
                    $words = $this->parsePuzzleWords($matches['content']);
                    if (!$words) {
                        $this->writeLn(sprintf('No words found for puzzle id "%s".', $id), 'error');
                    }
                    $numberOfWords = 0;
                }

                $seed = $arguments['seed'] ?? intval(1000 + $id);

                $this->writeLn(sprintf('Generating puzzle id "%s".', $id), 'info');

                $wordSearch = new WordSearch();
                $wordSearch->setRandomSeed($seed);

                $success = $wordSearch->generate($rows, $cols, $words, $filler, $numberOfWords, $difficulty);
                if (!$success) {
                    $this->writeLn(sprintf('Puzzle %s generated with error.', $id), 'error');
                }
                if ($wordSearch->getErrors()) {
                    foreach ($wordSearch->getErrors() as $error) {
                        $this->writeLn(sprintf('Puzzle %s: %s', $id, $error), 'error');
                    }
                }

                $items = $this->app['publishing.wordsearch.items'] ?? [];

                $items[$id] = [
                    'solution' => $wordSearch->solutionAsHtml(),
                    'wordlist' => [
                        'sorted-1-chunk'   => $wordSearch->wordListAsHtml(true),
                        'unsorted-1-chunk' => $wordSearch->wordListAsHtml(false),
                        'sorted-2-chunk'   => $wordSearch->wordListAsHtml(true, 2),
                        'unsorted-2-chunk' => $wordSearch->wordListAsHtml(false, 2),
                        'sorted-3-chunk'   => $wordSearch->wordListAsHtml(true, 3),
                        'unsorted-3-chunk' => $wordSearch->wordListAsHtml(false, 3),
                        'sorted-4-chunk'   => $wordSearch->wordListAsHtml(true, 4),
                        'unsorted-4-chunk' => $wordSearch->wordListAsHtml(false, 4),
                    ],
                ];
                $this->app['publishing.wordsearch.items'] = $items;

                $difficultyTextEasy = $this->getEditionOption(
                    'plugins.options.WordSearch.strings.difficulty.easy',
                    'Difficulty: Easy');
                $difficultyTextHard = $this->getEditionOption(
                    'plugins.options.WordSearch.strings.difficulty.hard',
                    'Difficulty: Hard');

                return sprintf(
                    '<div class="wordsearch wordsearch-puzzle-container" data-id="%s">'.
                    '<div class="wordsearch-title">%s</div>'.
                    '<div class="wordsearch-text">%s</div>'.
                    '<div class="wordsearch-text2">%s</div>'.
                    '<div class="wordsearch-difficulty">%s</div>'.
                    '<div class="wordsearch-puzzle">%s</div>'.
                    '</div>',
                    $arguments['id'],
                    sprintf($title, $id),
                    $text,
                    $text2,
                    $difficulty === WordSearch::DIFFICULTY_EASY ? $difficultyTextEasy : $difficultyTextHard,
                    $wordSearch->puzzleAsHtml());
            },
            $this->item['content']);

        $this->item['content'] = $content;
    }

    protected function processWordList()
    {
        $regExp = '/'
            .'(?<div>'
            .'<div +(?<pre>[^>]*)'
            .'class="(?<class>wordsearch wordsearch-wordlist)"'
            .' +'
            .'data-id="(?<id>\d+)"'
            .'(?<post>[^>]*)>'
            .')' // div group
            .'.*'
            .'<\/div>'
            .'/Ums'; // Ungreedy, multiline, dotall

        $content = preg_replace_callback(
            $regExp,
            function ($matches) {

                $id = $matches['id'];

                $itemsArguments = $this->app['publishing.wordsearch.arguments'];
                if (!isset($itemsArguments[$id])) {
                    $this->writeLn(sprintf('Puzzle with id "%s" not found.', $id), 'error');

                    return sprintf('ERROR: Puzzle with id "%s" not found.', $id);
                }
                $arguments = $itemsArguments[$id]['wordlist'];
                $sorted = $arguments['sorted'] ?? false;
                $chunks = $arguments['chunks'] ?? 1;

                if ($chunks > 4) {
                    $this->writeLn(
                        sprintf('Puzzle with id "%s": chunks value (%s) out of range.', $id, $chunks),
                        'error');
                    $chunks = 1;
                }

                if (!isset($this->app['publishing.wordsearch.items'])) {
                    return '';
                }
                $items = $this->app['publishing.wordsearch.items'] ?? [];
                if (!isset($items[$id])) {
                    return '';
                }

                $wordlist = $items[$id]['wordlist'];

                $wordlistKey = sprintf(
                    "%s-%d-chunk",
                    $sorted ? 'sorted' : 'unsorted',
                    $chunks);

                return sprintf(
                    '<div class="wordsearch wordsearch-wordlist" data-id="%s">%s</div>',
                    $arguments['id'],
                    $wordlist[$wordlistKey]);
            },
            $this->item['content']);

        $this->item['content'] = $content;

    }

    protected function processSolution()
    {
        $regExp = '/'
            .'(?<div>'
            .'<div +(?<pre>[^>]*)'
            .'class="(?<class>wordsearch wordsearch-solution)"'
            .' +'
            .'data-id="(?<id>\d+)"'
            .'(?<post>[^>]*)>'
            .')' // div group
            .'.*'
            .'<\/div>'
            .'/Ums'; // Ungreedy, multiline, dotall

        $content = preg_replace_callback(
            $regExp,
            function ($matches) {

                $id = $matches['id'];

                $itemsArguments = $this->app['publishing.wordsearch.arguments'];
                if (!isset($itemsArguments[$id])) {
                    $this->writeLn(sprintf('Puzzle with id "%s" not found.', $id), 'error');

                    return sprintf('ERROR: Puzzle with id "%s" not found.', $id);
                }
                $arguments = $itemsArguments[$id]['solution'];

                $title = $arguments['title'] ?? $this->getEditionOption(
                        'plugins.options.WordSearch.strings.title') ?? '';
                $text = $arguments['text'] ?? '';

                if (!isset($this->app['publishing.wordsearch.items'])) {
                    return '';
                }
                $items = $this->app['publishing.wordsearch.items'] ?? [];
                if (!isset($items[$id])) {
                    return '';
                }

                return sprintf(
                    '<div class="wordsearch wordsearch-solution-container" data-id="%s">'.
                    '<div class="wordsearch-title">%s</div>'.
                    '<div class="wordsearch-text">%s</div>'.
                    '<div class="wordsearch-solution">%s</div>'.
                    '</div>',
                    $arguments['id'],
                    sprintf($title, $id),
                    $text,
                    $items[$id]['solution']);

            },
            $this->item['content']);

        $this->item['content'] = $content;

    }

    protected function readWordsFromFile($wordFile): array
    {
        $files = $this->getEditionOption('plugins.options.WordSearch.word_files', []);
        $contentsDir = $this->app['publishing.dir.book'].'/Contents';

        foreach ($files as $file) {
            if (!isset($file['label'])) {
                $this->writeLn(sprintf('Word file without a label.', 'error'));

                return [];
            }
            if ($file['label'] === $wordFile) {

                if (isset(static::$wordFiles[$wordFile])) {
                    return static::$wordFiles[$wordFile];
                }

                $filePath = $this->app->getFirstExistingFile($file['name'], [$contentsDir]);
                if (!$filePath) {
                    $this->writeLn(
                        sprintf('Word file with name "%s" not found in "%s".', $file['name'], $contentsDir),
                        'error');

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
                &
                $output
            ) {
                if ($liNode->children()->count() == 0) {
                    $output [] = $liNode->html();

                    return;
                }

                $cellText = '';

                $liNode->children()->each(
                    function (Crawler $liChildrenNode) use
                    (
                        &
                        $cellText
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

}
