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
class QuizQuestionnaireParser extends QuizItemParser
{
    /**
     * @var QuizQuestionnaire
     */
    protected $quizQuestionnaire;
    
    public function __construct($text)
    {        
        $this->quizQuestionnaire = new QuizQuestionnaire();
        parent::__construct($text, $this->quizQuestionnaire);
    }

    /**
     * Example body:
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
     * @param Crawler $crawler pointing to "div" node of the quiz item
     *
     * @throws \RuntimeException
     * @return array             with questionnaire values
     */
    protected function parseBody(Crawler $crawler)
    {
        // 'ol' node contains all the questions
        $olNode = $crawler->filter('div>ol');

        if (0 == $olNode->count()) {
            throw new \RuntimeException(
                sprintf(
                    'No questions found for questionnaire id "%s"' . "\n"
                    . $this->quizQuestionnaire->getId()
                ));
        }

        // collect questions
        $questionsList = array();

        $qnodes = $olNode->children();

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

        $this->quizQuestionnaire->setQuestions($questionsList);
    }

}
