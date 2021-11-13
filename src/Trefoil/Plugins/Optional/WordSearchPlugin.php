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

    }

    /**
     * @param $content
     * @return string|null
     */
    protected function processTrefoilMarkers($content): ?string
    {
        // Lazy initialize
        if (!isset($this->app['publishing.wordsearch.items'])) {
            $this->app['publishing.wordsearch.items'] = [];
        }

        $processor = new TrefoilMarkerProcessor();

        $wordSearch = new WordSearch();

        $processor->registerMarker(
            'wordsearch',
            function ($options = []) use
            (
                $wordSearch
            ) {

                $id = $options['id'] ?? strval(microtime());
                $rows = $options['rows'] ?? 15;
                $cols = $options['cols'] ?? 15;
                $filler = $options['filler'] ?? WordSearch::FILLER_LETTERS_ENGLISH;
                $wordFile = $options['word_file'] ?? '';
                $numberOfWords = $options['number_of_words'] ?? 0;
                $seed = $options['seed'] ?? $id;

                $words = WordSearch::DEFAULT_WORDS;
                if ($wordFile) {
                    $words = $this->readWordsFromFile($wordFile);
                }

                $wordSearch->setRandomSeed($seed);
                $success = $wordSearch->generate($rows, $cols, $words, $filler, $numberOfWords);

                if (!$success) {
                    $this->writeln(sprintf('ERROR: Puzzle %s generated with error.', $id), 'error');
                }
                if ($wordSearch->getErrors()) {
                    foreach ($wordSearch->getErrors() as $error) {
                        $this->writeln(sprintf('ERROR: Puzzle %s: %s', $id, $error), 'error');
                    }
                }

                $items = $this->app['publishing.wordsearch.items'];
                $items[$id] = [
                    'solution' => $wordSearch->solutionAsHtml(),
                    'wordlist' => [
                        'sorted' => $wordSearch->wordListAsHtml(true),
                        'plain'  => $wordSearch->wordListAsHtml(false),
                    ],
                ];
                $this->app['publishing.wordsearch.items'] = $items;

                return '<div class="wordsearch">'.$wordSearch->puzzleAsHtml().'</div>';
            });

        $processor->registerMarker(
            'wordsearch_wordlist',
            function ($options = []) {

                $id = $options['id'] ?? null;
                $sorted = $options['sorted'] ?? false;

                $items = $this->app['publishing.wordsearch.items'];
                if ($id) {
                    $wordlist = $items[$id]['wordlist'] ??
                        sprintf("==== ERROR: wordsearch_wordlist(): Puzzle ID (%s) not found.", $id);
                } else {
                    $wordlist = end($items)['wordlist'] ??
                        '==== ERROR: wordsearch_wordlist(): No puzzle found.';
                }

                $theWordlist = $sorted ? $wordlist['sorted'] : $wordlist['plain'];

                return '<div class="wordsearch-wordlist">'.$theWordlist.'</div>';
            });

        $processor->registerMarker(
            'wordsearch_solution',
            function ($options = []) {

                $id = $options['id'] ?? null;

                $items = $this->app['publishing.wordsearch.items'];
                if ($id) {
                    $solution = $items[$id]['solution'] ??
                        sprintf("==== ERROR: wordsearch_solution(): Puzzle ID (%s) not found.", $id);
                } else {
                    $solution = end($items)['solution'] ??
                        '==== ERROR: wordsearch_solution(): No puzzle found.';
                }

                return '<div class="wordsearch-solution">'.$solution.'</div>';
            });

        return $processor->parse($content);
    }

    protected function savePluginOptions()
    {
        $this->app['publishing.plugins.options.WordSearch.grid_size'] =
            $this->getEditionOption('plugins.options.WordSearch.grid_size', 20);
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

    /**
     * @param ParseEvent $event
     */
    public function onItemPostParse(ParseEvent $event)
    {
        $this->init($event);

        $event->setItem($this->item);
    }

}
