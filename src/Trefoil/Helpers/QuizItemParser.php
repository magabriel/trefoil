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

namespace Trefoil\Helpers;

use Symfony\Component\DomCrawler\Crawler;
use Trefoil\Util\CrawlerTools;

/**
 * Parse an HTML representation of a quiz into a QuizItem object.
 * This is a base class to be extended by each quiz item type.
 * Example quiz item:
 * <div class="quiz-item-type" data-id="1-1">
 *     <h5>Optional heading</h5>
 *     <h6>Optional subheading</h6>
 *     <p>free text</p>
 *     <ul><li>can have unordered lists</li></ul>
 *     <p>more free text</p>
 *     [quiz item body]
 * </div>
 */
abstract class QuizItemParser
{

    /**
     * The original text (UTF-8)
     *
     * @var string
     */
    protected $text;

    /**
     * The parsed quizItem
     *
     * @var QuizItem
     */
    protected $quizItem;

    /**
     * QuizItemParser constructor.
     *
     * @param string   $text
     * @param QuizItem $quizItem
     */
    public function __construct(string $text,
                                   QuizItem $quizItem)
    {
        $this->text = $text;
        $this->quizItem = $quizItem;
    }

    /**
     * @return QuizItem
     * @throws \Exception
     */
    public function parse(): QuizItem
    {
        if (!$this->quizItem) {
            throw new \LogicException('quizItem property is not assigned');
        }

        $crawler = new Crawler();
        $crawler->addHtmlContent($this->text);
        $crawler = $crawler->filter('div');

        $this->parseHeader($crawler);

        $this->parseBody($crawler);

        return $this->quizItem;
    }

    /**
     * Parse the header part of the quiz item.
     * Example:
     * <div class="quiz-item-type" data-id="1-1">
     *     <h5>Optional heading</h5>
     *     <h6>Optional subheading</h6>
     *     <p>free text</p>
     *     <ul><li>can have unordered lists</li></ul>
     *     <p>more free text</p>
     *     [quiz item body]
     * </div>
     *
     * @param Crawler $crawler
     * @throws \Exception
     */
    protected function parseHeader(Crawler $crawler): void
    {
        // Common data
        $this->quizItem->setId($crawler->attr('data-id'));
        if (!$this->quizItem->getId()) {
            throw new \RuntimeException(sprintf('QuizQestionnaire must have data-id: "%s"', $crawler->text()));
        }

        $this->quizItem->setOptions([
                                        'pagebreak' => $crawler->attr('data-pagebreak') !== '0',
                                    ]);

        // extract heading (optional)
        $this->quizItem->setHeading($this->extractHeading($crawler, 'h5'));

        // extract subheading (optional)
        if ($this->quizItem->getHeading()) {
            $this->quizItem->setSubHeading($this->extractHeading($crawler, 'h6'));
        }

        // introduction text (optional)
        $this->quizItem->setIntroduction($this->extractIntroduction($crawler));

        // Unique id for this questionnaire
        $this->quizItem->setInternalId(hash('crc32', json_encode($this->text)));
    }

    /**
     * Extract quiz item heading
     *
     * @param Crawler $crawler
     * @param string  $tag
     * @return string|null $heading
     */
    protected function extractHeading(Crawler $crawler,
                                      $tag): ?string
    {
        $headingNode = $crawler->filter('div>'.$tag);

        if (count($headingNode)) {
            return $headingNode->text();
        }

        return null;
    }

    /**
     * Extract the quiz item introduction text.
     * It can only contain <p>..</p> and <ul>..</ul> tags.
     *
     * @param Crawler $crawler
     * @return string
     */
    protected function extractIntroduction(Crawler $crawler): ?string
    {
        $questionTextNodes = $crawler->filter('div>p, div>ul');

        $text = [];
        foreach ($questionTextNodes as $pNode) {
            $p = new Crawler($pNode);
            $text[] = CrawlerTools::getNodeHtml($p);
        }

        if (!$text) {
            return null;
        }

        return implode('', $text);
    }

    /**
     * Parse the body part of the quiz item.
     * Must be overriden by inheritor classes to provide a valid implementation for the quiz item type.
     *
     * @param  Crawler $crawler
     * @throws \LogicException
     */
    protected function parseBody(Crawler $crawler): void
    {
        throw new \LogicException('Method parseBody() must be overriden.');
    }

}
