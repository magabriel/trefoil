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
 * Parse an HTML representation of an activity into a QuizActivity object.
 *
 * Example activity:
 *
 * <div class="activity" data-id="1-1">
 *     <h5>Optional heading</h5>
 *     <h6>Optional subheading</h6>
 *     <p>free text</p>
 *     <ul><li>can have unordered lists</li></ul>
 *     <p>more free text</p>
 *
 *     [activity questions]
 * </div>
 *
 * Where [activity questions]:
 *
 *  <ol>
 *    <li><p>Text of question 1</p>
 *      <ol>
 *        <li>Text of response 1</li>
 *        <li>Text of response 2
 *            <p>Optional explanation</p>
 *        </li>
 *        <li>Text of response 3</li>
 *      </ol>
 *    </li>
 *    <li><p>Text of question 2</p>
 *      <ol>
 *        <li>Text of response 1</li>
 *        ...
 *      </ol>
 *    </li>
 *  </ol>
 */
class QuizActivityParser extends QuizItemParser
{

    /**
     * @var QuizActivity
     */
    protected $quizActivity;

    public function __construct($text)
    {
        $this->quizActivity = new QuizActivity();

        parent::__construct($text, $this->quizActivity);
    }

    /**
     * List of responses to be interpreted as "Yes"
     *
     * @var Array|string
     */
    protected $responsesValidAsYes = array('Yes', 'True');

    /**
     * List of responses to be interpreted as "No"
     *
     * @var Array|string
     */
    protected $responsesValidAsNo = array('No', 'False');

    /**
     * List of responses to be interpreted as "Both"
     *
     * @var Array|string
     */
    protected $responsesValidAsBoth = array('Both');

    public function getResponsesValidAsYes()
    {
        return $this->responsesValidAsYes;
    }

    public function setResponsesValidAsYes($responsesValidAsYes)
    {
        $this->responsesValidAsYes = $responsesValidAsYes;
    }

    public function getResponsesValidAsNo()
    {
        return $this->responsesValidAsNo;
    }

    public function setResponsesValidAsNo($responsesValidAsNo)
    {
        $this->responsesValidAsNo = $responsesValidAsNo;
    }

    public function getResponsesValidAsBoth()
    {
        return $this->responsesValidAsBoth;
    }

    public function setResponsesValidAsBoth($responsesValidAsBoth)
    {
        $this->responsesValidAsBoth = $responsesValidAsBoth;
    }

    /**
     * Example body:
     *
     *  <ol>
     *    <li><p>Text of question 1</p>
     *      <ol>
     *        <li>Text of response 1</li>
     *        <li>Text of response 2
     *            <p>Optional explanation</p>
     *        </li>
     *        <li>Text of response 3</li>
     *      </ol>
     *    </li>
     *    <li><p>Text of question 2</p>
     *      <ol>
     *        <li>Text of response 1</li>
     *        ...
     *      </ol>
     *    </li>
     *  </ol>
     *
     * @param Crawler $crawler
     *
     * @throws \RuntimeException
     * @return array             with activity values
     */
    protected function parseBody(Crawler $crawler)
    {
        // 'ol' node contains all the questions
        $olNode = $crawler->filter('div>ol');

        if (0 == $olNode->count()) {
            throw new \RuntimeException(
                sprintf(
                    'No questions found for activity id "%s" of type "%s"' . "\n"
                    . $this->quizActivity->getId(),
                    $this->quizActivity->getType()
                ));
        }

        // collect questions
        $questionsList = array();

        $qnodes = $olNode->children();

        // all the 1st level "li" nodes are questions
        foreach ($qnodes as $qIndex => $qDomNode) {
            $qnode = new Crawler($qDomNode);

            $question = new QuizActivityQuestion();

            // select all tags other than "ol"
            $question->setText(CrawlerTools::getNodeHtml($qnode->filterXPath('li/*[not(self::ol)]')));

            // the 2nd level "li" nodes are responses to that question
            $responses = $qnode->filter('ol');
            if (0 == $responses->count()) {
                throw new \RuntimeException(
                    sprintf(
                        'No responses found for activity id "%s", question #%s of type "abc"',
                        $this->quizActivity->getId(),
                        $qIndex,
                        $this->quizActivity->getType()
                    ));
            }

            // collect responses and explanations
            $responsesList = array();
            $explanationsList = array();

            $rnodes = $responses->children();

            foreach ($rnodes as $rIndex => $rDomNode) {
                $rnode = new Crawler($rDomNode);

                // allowed tags inside a response
                $ps = $rnode->filter('p, ul, ol');

                $explanation = array();

                if ($ps->count() <= 1) {
                    // Response only
                    $responsesList[$rIndex] = $rnode->text();
                    $explanationsList[$rIndex] = null;
                } else {
                    // we have response (first p) and explanation (rest)
                    $responsesList[$rIndex] = $ps->eq(0)->text();

                    // get all the p's or li's
                    $count = $ps->count();
                    for ($i = 1; $i < $count; $i++) {
                        $nodeName = CrawlerTools::getNodeName($ps->eq($i));
                        if ('p' == $nodeName) {
                            // can contain HTML
                            $explanation[] = CrawlerTools::getNodeHtml($ps->eq($i));
                        } else {
                            // ul or ol, so take its li's
                            $liNodes = $ps->eq($i)->children();
                            $explanation[] = '<' . $nodeName . '>';
                            foreach ($liNodes as $liDomNode) {
                                $liNode = new Crawler($liDomNode);

                                // can contain HTML
                                $explanation[] = '<li>' . CrawlerTools::getNodeHtml($liNode) . '</li>';
                            }
                            $explanation[] = '</' . $nodeName . '>';
                        }
                    }

                    // Join everything
                    $explString = implode('', $explanation);

                    $explanationsList[$rIndex] = $explString;
                }

                if ($rnode->filter('strong')->count() > 0) {
                    $question->setSolution($rIndex);
                }
            }

            $question->setResponses($responsesList);
            $question->setExplanations($explanationsList);

            $questionsList[] = $question;
        }

        $this->quizActivity->setQuestions($questionsList);

        // Type ABC by default
        $this->quizActivity->setType(QuizActivity::QUIZ_ACTIVITY_TYPE_ABC);

        // Look if an ABC activity can be transformed bo YNB
        $this->transformAbcToYnb();
    }

    /**
     * Look if a ABC activity can be treated as a YNB activity.
     * - All responses for all questions must be the same.
     * - All responses for each question must be "True", "False" and (opt.) "Both"
     *   or equivalent values.
     */
    protected function transformAbcToYnb()
    {
        if ($this->quizActivity->getType() != QuizActivity::QUIZ_ACTIVITY_TYPE_ABC) {
            return;
        }

        // Look for each group of responses
        $responses = array();

        foreach ($this->quizActivity->getQuestions() as $question) {
            /** @var $question QuizActivityQuestion */
            $responsesClean = array();
            foreach ($question->getResponses() as $response) {
                // Remove ending dot if any
                $response = trim($response);
                $responsesClean[] = ('.' == substr($response, -1)) ? substr($response, 0, -1) : $response;
            }

            if (!$responses) {
                // the first set of responses is used as model
                $responses = $responsesClean;
            } else {
                // look if current set of responses is equal to the saved model
                if (array_merge(array_diff($responses, $responsesClean), array_diff($responsesClean, $responses))) {
                    // Different, cannot continue
                    return;
                }
            }

            // Look for Yes/No/Both type
            if (!$this->checkYNBTypeResponses($responsesClean)) {
                return;
            }
        }

        // OK for YNB type
        $this->quizActivity->setType(QuizActivity::QUIZ_ACTIVITY_TYPE_YNB);
    }

    /**
     * Check if a set of responses can be considered as a "Yes/No/Both" set.
     *
     * @param array $responses The responses to be checked
     *
     * @return boolean
     */
    protected function checkYNBTypeResponses(array $responses)
    {
        if (count($responses) > 3) {
            return false;
        }

        // Check that each response is a valid one
        foreach ($responses as $response) {
            $resp = strtolower($response);
            // note the case insensitive search in the arrays
            if (!in_array($resp, array_map('strtolower', $this->responsesValidAsYes)) &&
                !in_array($resp, array_map('strtolower', $this->responsesValidAsNo)) &&
                !in_array($resp, array_map('strtolower', $this->responsesValidAsBoth))
            ) {
                return false;
            }
        }

        return true;
    }
}
