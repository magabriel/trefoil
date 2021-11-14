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
        $processor = new TrefoilMarkerProcessor();

        $processor->registerMarker(
            'wordsearch',
            function ($options = []) {
                $id = $options['id'] ?? null;
                if (!$id) {
                    $this->writeLn('wordsearch(): ID argument missing', 'error');
                    return '';
                }

                $itemsArguments = $this->app['publishing.wordsearch.arguments'] ?? [];
                $itemsArguments[$id]['puzzle']['options'] = $options;
                $this->app['publishing.wordsearch.arguments'] = $itemsArguments;

                return sprintf(
                    '<div class="wordsearch wordsearch-puzzle-simple" data-id="%s" markdown="1"></div>',
                    $id);
            });

        $processor->registerMarker(
            'wordsearch_begin',
            function ($options = []) {
                $id = $options['id'] ?? null;
                if (!$id) {
                    $this->writeLn('wordsearch_begin(): ID argument missing', 'error');
                    return '';
                }

                $itemsArguments = $this->app['publishing.wordsearch.arguments'] ?? [];
                $itemsArguments[$id]['puzzle']['options'] = $options;
                $this->app['publishing.wordsearch.arguments'] = $itemsArguments;

                return sprintf(
                    '<div class="wordsearch wordsearch-puzzle" data-id="%s" markdown="1">',
                    $id);
            });

        $processor->registerMarker(
            'wordsearch_end',
            function () {
                return '</div>';
            });

        $processor->registerMarker(
            'wordsearch_wordlist',
            function ($options = []) {
                $id = $options['id'] ?? null;
                if (!$id) {
                    $this->writeLn('wordsearch_wordlist(): ID argument missing', 'error');
                    return '';
                }

                $itemsArguments = $this->app['publishing.wordsearch.arguments'] ?? [];
                $itemsArguments[$id] ['wordlist'] ['options'] = $options;
                $this->app['publishing.wordsearch.arguments'] = $itemsArguments;

                return sprintf(
                    '<div class="wordsearch wordsearch-wordlist" data-id="%s" markdown="1"></div>',
                    $id);
            });

        $processor->registerMarker(
            'wordsearch_solution',
            function ($options = []) {
                $id = $options['id'] ?? null;
                if (!$id) {
                    $this->writeLn('wordsearch_solution(): ID argument missing', 'error');
                    return '';
                }

                $itemsArguments = $this->app['publishing.wordsearch.arguments'] ?? [];
                $itemsArguments[$id] ['solution'] ['options'] = $options;
                $this->app['publishing.wordsearch.arguments'] = $itemsArguments;

                return sprintf(
                    '<div class="wordsearch wordsearch-solution" data-id="%s" markdown="1"></div>',
                    $id);
            });

        return $processor->parse($content);
    }

    protected function savePluginOptions()
    {
        $this->app['publishing.plugins.options.WordSearch.grid_size'] =
            $this->getEditionOption('plugins.options.WordSearch.grid_size', 20);
    }

    protected function checkBalancedCalls()
    {
        $msg = (new \ReflectionClass($this))->getShortName().': '.$this->item['config']['content'].': ';
        if ($this->wordsearchCalls > 0) {
            throw new PluginException($msg.'wordsearch_begin() call without ending previous.');
        }

        if ($this->wordsearchCalls < 0) {
            throw new PluginException($msg.'wordsearch_end() call without wordsearch_begin().');
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
                $options = $itemsArguments[$id]['puzzle']['options'];
                $rows = $options['rows'] ?? 15;
                $cols = $options['cols'] ?? 15;
                $filler = $options['filler'] ?? WordSearch::FILLER_LETTERS_ENGLISH;
                $title = $options['title'] ?? '';
                $text = $options['text'] ?? '';
                $difficulty = $options['difficulty'] ?? WordSearch::DIFFICULTY_HARD;

                if ($isSimple) {
                    $wordFile = $options['word_file'] ?? '';
                    $numberOfWords = $options['number_of_words'] ?? 0;
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

                $seed = $options['seed'] ?? intval(1000 + $id);

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

                $difficultyTextEasy = $this->getEditionOption('plugins.options.WordSearch.difficulty.text_easy', 'Difficulty: Easy');
                $difficultyTextHard = $this->getEditionOption('plugins.options.WordSearch.difficulty.text_hard', 'Difficulty: Hard');

                return sprintf(
                    '<div class="wordsearch">'.
                    '<div class="wordsearch-title">%s</div>'.
                    '<div class="wordsearch-text">%s</div>'.
                    '<div class="wordsearch-difficulty">%s</div>'.
                    '<div class="wordsearch-puzzle" data-id="%s">%s</div>'.
                    '</div>',
                    $title,
                    $text,
                    $difficulty === WordSearch::DIFFICULTY_EASY ? $difficultyTextEasy : $difficultyTextHard,
                    $options['id'],
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
                $options = $itemsArguments[$id]['wordlist']['options'];
                $sorted = $options['sorted'] ?? false;
                $chunks = $options['chunks'] ?? 1;

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
                    $options['id'],
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
                $options = $itemsArguments[$id]['solution']['options'];

                $title = $options['title'] ?? '';
                $text = $options['text'] ?? '';

                if (!isset($this->app['publishing.wordsearch.items'])) {
                    return '';
                }
                $items = $this->app['publishing.wordsearch.items'] ?? [];
                if (!isset($items[$id])) {
                    return '';
                }

                return sprintf(
                    '<div class="wordsearch">'.
                    '<div class="wordsearch-title">%s</div>'.
                    '<div class="wordsearch-text">%s</div>'.
                    '<div class="wordsearch-solution" data-id="%s">%s</div>'.
                    '</div>',
                    $title,
                    $text,
                    $options['id'],
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
