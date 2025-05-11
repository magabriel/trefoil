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
use Trefoil\Helpers\Paperdle;
use Trefoil\Helpers\TrefoilMarkerProcessor;
use Trefoil\Plugins\BasePlugin;
use Trefoil\Util\SimpleReport;

/**
 * Class PaperdlePlugin
 *
 * The provided functions use a "trefoil marker" syntax ('{@..@}' blocks).
 * Expected syntax:
 * 
 *      {@ paperdle (..arguments..) @}
 * 
 *      {@ paperdle_letters (..arguments..) @}
 * 
 *      {@ paperdle_randomizer (..arguments..) @}
 * 
 *      {@ paperdle_decoder (..arguments..) @}
 * 
 *      {@ paperdle_solution (..arguments..) @}
 * 
 *      {@ paperdle_solution_decoder (..arguments..) @}
 *
 * @see     paperdle
 * @package Trefoil\Plugins\Optional
 */
class PaperdlePlugin extends BasePlugin implements EventSubscriberInterface
{

    protected static array $wordFiles = [];
    protected int $paperdleCalls = 0;
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

        // $this->savePluginOptions();

        $event->setItemProperty('original', $content);

        $this->checkBalancedCalls();
    }

    /**
     * @param $content
     * @return string|null
     */
    protected function processTrefoilMarkers($content): ?string
    {
        $this->paperdleCalls = 0;

        $processor = new TrefoilMarkerProcessor();

        $processor->registerMarker(
            'paperdle',
            function (int $id, array $arguments = []) {
                $this->saveMarkerArguments('board', $id, $arguments);
                return sprintf(
                    '<div class="paperdle paperdle-board" data-id="%s" markdown="1"></div>',
                    $id
                );
            }
        );

        $processor->registerMarker(
            'paperdle_letters',
            function (int $id, array $arguments = []) {
                $this->saveMarkerArguments('letters', $id, $arguments);
                return sprintf(
                    '<div class="paperdle paperdle-board-letters" data-id="%s" markdown="1"></div>',
                    $id
                );
            }
        );

        $processor->registerMarker(
            'paperdle_randomizer',
            function (int $id, array $arguments = []) {
                $this->saveMarkerArguments('randomizer', $id, $arguments);
                return sprintf(
                    '<div class="paperdle paperdle-randomizer" data-id="%s" markdown="1"></div>',
                    $id
                );
            }
        );

        $processor->registerMarker(
            'paperdle_decoder',
            function (int $id, array $arguments = []) {
                $this->saveMarkerArguments('decoder', $id, $arguments);
                return sprintf(
                    '<div class="paperdle paperdle-decoder" data-id="%s" markdown="1"></div>',
                    $id
                );
            }
        );

        $processor->registerMarker(
            'paperdle_solution',
            function (int $id, array $arguments = []) {
                $this->saveMarkerArguments('solution', $id, $arguments);
                return sprintf(
                    '<div class="paperdle paperdle-solution" data-id="%s" markdown="1"></div>',
                    $id
                );
            }
        );

        $processor->registerMarker(
            'paperdle_solution_decoder',
            function (int $id, array $arguments = []) {
                $this->saveMarkerArguments('solution_decoder', $id, $arguments);
                return sprintf(
                    '<div class="paperdle paperdle-solution-decoder" data-id="%s" markdown="1"></div>',
                    $id
                );
            }
        );

        return $processor->parse($content);
    }

    protected function saveMarkerArguments(
        string $key,
        int $id,
        array $arguments
    ) {
        $arguments['id'] = $id;

        $argumentsKey = 'publishing.Paperdle.arguments';

        $itemsArguments = $this->app[$argumentsKey] ?? [];
        $itemsArguments[$id][$key] = $arguments;
        $this->app[$argumentsKey] = $itemsArguments;
    }

    protected function retrieveMarkerArguments(
        string $key,
        int $id
    ): array {
        $argumentsKey = 'publishing.Paperdle.arguments';

        $itemsArguments = $this->app[$argumentsKey] ?? [];
        if (!isset($itemsArguments[$id])) {
            $this->writeLn(sprintf('Puzzle with id "%s" not found.', $id), 'error');
            $this->saveProblem(sprintf('Puzzle with id "%s" not found.', $id), 'error');

            return [];
        }

        return $itemsArguments[$id][$key];
    }

    protected function saveItems(
        int $id,
        array $items
    ) {
        $itemsKey = 'publishing.Paperdle.items';

        $itemsList = $this->app[$itemsKey] ?? [];
        $itemsList[$id] = $items;
        $this->app[$itemsKey] = $itemsList;
    }

    protected function retrieveItems(
        int $id
    ): array {
        $itemsKey = 'publishing.Paperdle.items';

        $itemsList = $this->app[$itemsKey] ?? [];

        if (!isset($itemsList[$id])) {
            $this->writeLn(sprintf('Puzzle with id "%s" not found.', $id), 'error');
            $this->saveProblem(sprintf('Puzzle with id "%s" not found.', $id), 'error');

            return [];
        }

        return $itemsList[$id];
    }

    // protected function savePluginOptions()
    // {
    //     $alphabet = $this->getEditionOption('plugins.options.Paperdle.alphabet');
    //     if ($alphabet) {
    //         $this->app['publishing.plugins.options.Paperdle.alphabet'] = $alphabet;
    //     }

    //     $tries = $this->getEditionOption('plugins.options.Paperdle.tries');
    //     if ($tries) {
    //         $this->app['publishing.plugins.options.Paperdle.tries'] = $tries;
    //     }

    //     $markers = $this->getEditionOption('plugins.options.Paperdle.markers');
    //     if ($markers) {
    //         $this->app['publishing.plugins.options.Paperdle.markers'] = $markers;
    //     }
    // }

    protected function checkBalancedCalls()
    {
    }

    /**
     * @param ParseEvent $event
     */
    public function onItemPostParse(ParseEvent $event)
    {
        $this->init($event);

        $this->processPaperdlePuzzles();

        $event->setItem($this->item);
    }

    protected function processPaperdlePuzzles()
    {
        $this->processBoard();
        $this->processBoardLetters();
        $this->processRandomizer();
        $this->processDecoder();
        $this->processSolution();
        $this->processSolutionDecoder();
    }

    protected function processBoard()
    {
        $regExp = '/'
            . '(?<div>'
            . '<div +(?<pre>[^>]*)'
            . 'class="(?<class>paperdle paperdle-board)"'
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
                $id = intval($matches['id']);

                $arguments = $this->retrieveMarkerArguments('board', $id);
                if (!$arguments) {
                    return sprintf('ERROR: Puzzle with id "%s" not found.', $id);
                }

                $alphabet = $arguments['alphabet'] ?? $this->getEditionOption('plugins.options.Paperdle.alphabet') ?? Paperdle::ALPHABET_ENGLISH;
                $tries = $arguments['tries'] ?? $this->getEditionOption('plugins.options.Paperdle.tries') ?? 6;
                $markers = mb_str_split($arguments['markers'] ?? $this->getEditionOption('plugins.options.Paperdle.markers') ?? "=?#");

                $word = $arguments['word'] ?? "";
                $title = $arguments['title'] ?? $this->getEditionOption('plugins.options.Paperdle.strings.board.title') ?? '';
                $text = $arguments['text'] ?? $this->getEditionOption('plugins.options.Paperdle.strings.board.text') ?? '';
                $text2 = $arguments['text2'] ?? $this->getEditionOption('plugins.options.Paperdle.strings.board.text2') ?? '';

                if (!$word) {
                    $this->writeLn(sprintf('No word to guess for puzzle id "%s".', $id), 'error');
                    $this->saveProblem(sprintf('No word to guess for puzzle id "%s".', $id), 'error');
                } else {
                    $this->saveProblem(sprintf('Word "%s" used for puzzle id "%s".', $word, $id), "info");
                }

                $timeStart = microtime(true);
                $this->write(sprintf('Generating puzzle id "%s".', $id), 'info');

                $paperdle = new Paperdle();
                $success = $paperdle->generate(
                    $word,
                    $alphabet,
                    $markers[0],
                    $markers[1],
                    $markers[2],
                );

                $timeEnd = microtime(true);
                $this->writeLn(sprintf(
                    ' %.2f sec.',
                    $timeEnd - $timeStart
                ), 'plain');

                if (!$success) {
                    if ($paperdle->getErrors()) {
                        foreach ($paperdle->getErrors() as $error) {
                            $this->writeLn(sprintf('Puzzle %s: %s', $id, $error), 'error');
                            $this->saveProblem(sprintf('Puzzle %s: %s', $id, $error), 'error');
                        }
                    }
                }
                if ($paperdle->getWarnings()) {
                    foreach ($paperdle->getWarnings() as $warning) {
                        $this->writeLn(sprintf('Puzzle %s: %s', $id, $warning), 'warning');
                        $this->saveProblem(sprintf('Puzzle %s: %s', $id, $warning), 'warning');
                    }
                }

                // Save all the generated data for later use
                $items = [
                    'word_to_guess' => $paperdle->getWordToGuess(),
                    'alphabet' => $paperdle->getAlphabet(),
                    'solution' => $paperdle->getEncodedSolution(),
                    'randomizer_table' => $paperdle->getRandomizerTableAsHtml(),
                    'decoder_table' => $paperdle->getDecoderTableAsHtml(),
                    'solution_decoder_table' => $paperdle->getSolutionDecoderTableAsHtml(),
                ];

                $this->saveItems($id, $items);

                return $this->renderMarkerHtml(
                    "board",
                    $id,
                    $title,
                    $text,
                    $this->getBoard($tries, mb_strlen($paperdle->getWordToGuess())),
                    $text2
                );
            },
            $this->item['content']
        );

        $this->item['content'] = $content;
    }

    protected function renderMarkerHtml(
        string $type,
        int $id,
        string $title,
        string $text,
        string $board,
        string $text2
    ) {
        return sprintf(
            '<div class="paperdle paperdle-%s-container" data-id="%s">' .
            ' <div class="paperdle-heading">' .
            '  <div class="paperdle-title">%s</div>' .
            '  <div class="paperdle-text">%s</div>' .
            ' </div>' .
            ' <div class="paperdle-%s">%s</div>' .
            ' <div class="paperdle-text2">%s</div>' .
            '</div>',
            $type,
            $id,
            sprintf($title, $id, $id),
            $text,
            $type,
            $board,
            $text2,
        );
    }

    protected function processBoardLetters()
    {
        $regExp = '/'
            . '(?<div>'
            . '<div +(?<pre>[^>]*)'
            . 'class="(?<class>paperdle paperdle-board-letters)"'
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

                $id = intval($matches['id']);

                $arguments = $this->retrieveMarkerArguments('letters', $id);
                $items = $this->retrieveItems($id);
                if (!$arguments || !$items) {
                    return sprintf('ERROR: Puzzle with id "%s" not found.', $id);
                }

                $title = $arguments['title'] ?? $this->getEditionOption('plugins.options.Paperdle.strings.letters.title') ?? '';
                $text = $arguments['text'] ?? $this->getEditionOption('plugins.options.Paperdle.strings.letters.text') ?? '';
                $text2 = $arguments['text2'] ?? $this->getEditionOption('plugins.options.Paperdle.strings.letters.text2') ?? '';

                return $this->renderMarkerHtml(
                    "letters",
                    $id,
                    $title,
                    $text,
                    $this->getBoardLetters($items['alphabet']),
                    $text2
                );
            },
            $this->item['content']
        );

        $this->item['content'] = $content;
    }

    protected function processRandomizer()
    {
        $regExp = '/'
            . '(?<div>'
            . '<div +(?<pre>[^>]*)'
            . 'class="(?<class>paperdle paperdle-randomizer)"'
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

                $id = intval($matches['id']);

                $arguments = $this->retrieveMarkerArguments('randomizer', $id);
                $items = $this->retrieveItems($id);
                if (!$arguments || !$items) {
                    return sprintf('ERROR: Puzzle with id "%s" not found.', $id);
                }

                $title = $arguments['title'] ?? $this->getEditionOption('plugins.options.Paperdle.strings.randomizer.title') ?? '';
                $text = $arguments['text'] ?? $this->getEditionOption('plugins.options.Paperdle.strings.randomizer.text') ?? '';
                $text2 = $arguments['text2'] ?? $this->getEditionOption('plugins.options.Paperdle.strings.randomizer.text2') ?? '';

                return $this->renderMarkerHtml(
                    "randomizer",
                    $id,
                    $title,
                    $text,
                    $items['randomizer_table'],
                    $text2
                );
            },
            $this->item['content']
        );

        $this->item['content'] = $content;
    }

    protected function processDecoder()
    {
        $regExp = '/'
            . '(?<div>'
            . '<div +(?<pre>[^>]*)'
            . 'class="(?<class>paperdle paperdle-decoder)"'
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

                $id = intval($matches['id']);

                $arguments = $this->retrieveMarkerArguments('decoder', $id);
                $items = $this->retrieveItems($id);
                if (!$arguments || !$items) {
                    return sprintf('ERROR: Puzzle with id "%s" not found.', $id);
                }

                $title = $arguments['title'] ?? $this->getEditionOption('plugins.options.Paperdle.strings.decoder.title') ?? '';
                $text = $arguments['text'] ?? $this->getEditionOption('plugins.options.Paperdle.strings.decoder.text') ?? '';
                $text2 = $arguments['text2'] ?? $this->getEditionOption('plugins.options.Paperdle.strings.decoder.text2') ?? '';

                return $this->renderMarkerHtml(
                    "decoder",
                    $id,
                    $title,
                    $text,
                    $items['decoder_table'],
                    $text2
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
            . 'class="(?<class>paperdle paperdle-solution)"'
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

                $id = intval($matches['id']);

                $arguments = $this->retrieveMarkerArguments('solution', $id);
                $items = $this->retrieveItems($id);
                if (!$arguments || !$items) {
                    return sprintf('ERROR: Puzzle with id "%s" not found.', $id);
                }

                $title = $arguments['title'] ?? $this->getEditionOption('plugins.options.Paperdle.strings.solution.title') ?? '';
                $text = $arguments['text'] ?? $this->getEditionOption('plugins.options.Paperdle.strings.solution.text') ?? '';
                $text2 = $arguments['text2'] ?? $this->getEditionOption('plugins.options.Paperdle.strings.solution.text2') ?? '';

                return $this->renderMarkerHtml(
                    "solution",
                    $id,
                    $title,
                    $text,
                    $items['solution'],
                    $text2
                );
            },
            $this->item['content']
        );

        $this->item['content'] = $content;
    }

    protected function processSolutionDecoder()
    {
        $regExp = '/'
            . '(?<div>'
            . '<div +(?<pre>[^>]*)'
            . 'class="(?<class>paperdle paperdle-solution-decoder)"'
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

                $id = intval($matches['id']);

                $arguments = $this->retrieveMarkerArguments('solution_decoder', $id);
                $items = $this->retrieveItems($id);
                if (!$arguments || !$items) {
                    return sprintf('ERROR: Puzzle with id "%s" not found.', $id);
                }

                $title = $arguments['title'] ?? $this->getEditionOption('plugins.options.Paperdle.strings.solution-decoder.title') ?? '';
                $text = $arguments['text'] ?? $this->getEditionOption('plugins.options.Paperdle.strings.solution-decoder.text') ?? '';
                $text2 = $arguments['text2'] ?? $this->getEditionOption('plugins.options.Paperdle.strings.solution-decoder.text2') ?? '';

                return $this->renderMarkerHtml(
                    "solution-decoder",
                    $id,
                    $title,
                    $text,
                    $items['solution_decoder_table'],
                    $text2
                );
            },
            $this->item['content']
        );

        $this->item['content'] = $content;
    }

    protected function getBoard(int $rows, int $columns): string
    {
        $board = '<table>';
        // Create $board as an html table with $rows rows and $columns columns

        $board .= "<tr>";
        for ($i = 0; $i < $columns; $i++) {
            $board .= '<th>' . ($i + 1) . '</th>';
        }
        $board .= "</tr>";

        for ($i = 0; $i < $rows; $i++) {
            $board .= '<tr>';
            for ($j = 0; $j < $columns; $j++) {
                $board .= '<td></td>';
            }
            $board .= '</tr>';
        }
        $board .= '</table>';

        return $board;
    }

    protected function getBoardLetters(string $alphabet)
    {
        $lettersList = mb_str_split($alphabet);

        $rows = 3;
        $columns = ceil(count($lettersList) / 3);

        $letters = '<table>';

        $index = 0;
        for ($i = 0; $i < $rows; $i++) {
            $letters .= '<tr>';
            for ($j = 0; $j < $columns; $j++) {
                if (!isset($lettersList[$index])) {
                    continue;
                }
                $letters .= sprintf('<td>%s</td>', $lettersList[$index]);
                $index++;
            }
            $letters .= '</tr>';
        }
        $letters .= '</table>';

        return $letters;
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
        $reportFile = $outputDir . '/report-paperdlePlugin.txt';

        $report = new SimpleReport();
        $report->setTitle('paperdlePlugin');
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
