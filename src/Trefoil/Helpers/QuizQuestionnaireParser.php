<?php
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
 * Parse an HTML representation of a questionnaire into a QuizQuestionnaire object.
 *
 * Example questionnaire:
 *
 * <div class="questions" data-id="1-1">
 *     <h5>Optional heading</h5>
 *     <h6>Optional subheading</h6>
 *     <p>free text</p>
 *     <ul><li>can have unordered lists</li></ul>
 *     <p>more free text</p>
 *
 *     [questionnaire questions]
 * </div>
 *
 * Where [questionnaire questions]:
 *
 *  <ol>
 *    <li><p>Text of question 1</p>
 *        ... whatever text and markup ...
 *      <h6>Solution heading (optional)</h6>
 *      <p>Solution text (optional)</p>
 *        ... whatever text and markup ...
 *    </li>
 *    <li><p>Text of question 2</p>
 *        ...
 *    </li>
 *  </ol>
 */
class QuizQuestionnaireParser
{

    /**
     * The original text (UTF-8)
     *
     * @var string
     */
    protected $text;

    /**
     * The parsed questionnaire
     *
     * @var QuizQuestionnaire
     */
    protected $questionnaire;

    public function __construct($text)
    {
        $this->text = $text;
    }

    /**
     *
     * @return QuizQuestionnaire
     */
    public function parse()
    {
        $this->extractQuestionnaire();

        return $this->questionnaire;
    }

    /**
     * Example questionnaire:
     *
     * <div class="questionnaire" data-id="1-1">
     *     <h5>Optional heading</h5>
     *     <h6>Optional subheading</h6>
     *     <p>free text</p>
     *     <ul><li>can have unordered lists</li></ul>
     *     <p>more free text</p>
     *
     *     [questionnaire questions]
     * </div>
     *
     * [questionnaire questions] ::=  <ol>...</ol>
     */
    protected function extractQuestionnaire()
    {
        $crawler = new Crawler();
        $crawler->addHtmlContent($this->text, 'UTF-8');
        $crawler = $crawler->filter('div');

        $this->questionnaire = new QuizQuestionnaire();

        // Common data
        $this->questionnaire->setId($crawler->attr('data-id'));
        if (!$this->questionnaire->getId()) {
            throw new \Exception(sprintf('QuizQestionnaire must have data-id: "%s"', $crawler->text()));
        }

        $this->questionnaire->setOptions(
                            array(
                                'pagebreak' => $crawler->attr('data-pagebreak') != '0')
        );

        // extract heading (optional)
        $this->questionnaire->setHeading($this->extractHeading($crawler, 'h5'));

        // extract subheading (optional)
        if ($this->questionnaire->getHeading()) {
            $this->questionnaire->setSubHeading($this->extractHeading($crawler, 'h6'));
        }

        // introduction text (optional)
        $this->questionnaire->setIntroduction($this->extractQuestionnaireIntroduction($crawler));

        // the questions
        $this->questionnaire->setQuestions($this->extractQuestions($crawler));

        // Unique id for this questionnaire
        $this->questionnaire->setInternalId(hash('crc32', json_encode($this->text)));
    }

    /**
     * Extract questionnaire heading
     *
     * @param Crawler $crawler
     * @param string  $tag
     *
     * @return array $heading
     */
    protected function extractHeading(Crawler $crawler, $tag)
    {
        $headingNode = $crawler->filter($tag);

        if (count($headingNode)) {
            return $headingNode->text();
        }

        return null;
    }

    /**
     * Extract the questionnaire introduction text
     *
     * @param Crawler $crawler
     *
     * @return string
     */
    protected function extractQuestionnaireIntroduction(Crawler $crawler)
    {
        $questionTextNodes = $crawler->filter('div>p, div>ul');

        $text = array();
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
     * Example questionnaire:
     *
     *  <ol>
     *    <li><p>Text of question 1</p>
     *        ... whatever text and markup ...
     *      <h6>Solution heading (optional)</h6>
     *      <p>Solution text (optional)</p>
     *        ... whatever text and markup ...
     *    </li>
     *    <li><p>Text of question 2</p>
     *        ...
     *    </li>
     *  </ol>
     *
     * @param Crawler $crawler
     *
     * @throws \RuntimeException
     * @return array             with questionnaire values
     */
    protected function extractQuestions(Crawler $crawler)
    {
        // 'ol' node contains all the questions
        $olNode = $crawler->filter('ol');

        if (0 == $olNode->count()) {
            throw new \RuntimeException(
                sprintf(
                    'No questions found for questionnaire id "%s"' . "\n"
                    . $this->questionnaire->getId()
                ));
        }

        // collect questions
        $questionsList = array();

        $qnodes = $crawler->filter('ol')->children();

        // all the 1st level "li" nodes are questions
        foreach ($qnodes as $qDomNode) {
            $qnode = new Crawler($qDomNode);

            $question = new QuizQuestionnaireQuestion();

            $qnodeNodes = $qnode->children();

            if (0 == $qnodeNodes->count()) {
                // this "li" question node doesn't have anything in it except some text
                $question->setText('<p>' . $qnode->text() . '</p>');
            } else {
                // a normal question
                foreach ($qnodeNodes as $qnodeDomNode) {
                    $qnodeNode = new Crawler($qnodeDomNode);

                    $nodeName = CrawlerTools::getNodeName($qnodeNode);
                    if ('h6' == $nodeName) {
                        // solution heading
                        $question->setSolution('');
                        $question->setHeading($qnodeNode->text());
                    } else {
                        // piece of question text or solution text
                        $piece = CrawlerTools::getNodeHtml($qnodeNode);
                        if ($question->getHeading()) {
                            // solution
                            $question->setSolution($question->getSolution() . $piece);
                        } else {
                            // question text
                            $question->setText($question->getText() . $piece);
                        }
                    }
                }
            }

            $questionsList[] = $question;
        }

        return $questionsList;
    }

}
