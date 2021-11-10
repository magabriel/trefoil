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
    protected static WordSearch $wordSearch;

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

        self::$wordSearch = new WordSearch();

        $processor->registerMarker(
            'wordsearch',
            function ($options = []) {

                $id = $options['id'] ?? strval(microtime());
                $rows = $options['rows'] ?? 15;
                $cols = $options['cols'] ?? 15;
                $filler = $options['filler'] ?? WordSearch::FILLER_LETTERS_ENGLISH;

                $words = WordSearch::DEFAULT_WORDS;

                self::$wordSearch->setRandomSeed(56);
                self::$wordSearch->generate($rows, $cols, $words, $filler);

                $items = $this->app['publishing.wordsearch.items'];
                $items[$id] = [
                    'solution' => self::$wordSearch->solutionAsHtml(),
                    'wordlist' => self::$wordSearch->wordListAsHtml(),
                ];
                $this->app['publishing.wordsearch.items'] = $items;

                return '<div class="wordsearch">'.self::$wordSearch->puzzleAsHtml().'</div>';
            });

        $processor->registerMarker(
            'wordsearch_wordlist',
            function ($options = []) {

                $id = $options['id'] ?? null;

                $items = $this->app['publishing.wordsearch.items'];
                if ($id) {
                    $wordlist = $items[$id]['wordlist'] ??
                        sprintf("==== ERROR: wordsearch_wordlist(): Puzzle ID (%s) not found.", $id);
                } else {
                    $wordlist = end($items)['wordlist'] ??
                        '==== ERROR: wordsearch_wordlist(): No puzzle found.';
                }

                return '<div class="wordsearch-wordlist">'.$wordlist.'</div>';
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

    /**
     * @param ParseEvent $event
     */
    public function onItemPostParse(ParseEvent $event)
    {
        $this->init($event);

        $event->setItem($this->item);
    }

}
