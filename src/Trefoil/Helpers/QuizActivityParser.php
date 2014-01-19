<?php

namespace Trefoil\Helpers;

use Symfony\Component\DomCrawler\Crawler;
use Trefoil\Util\CrawlerTools;

/**
 * Parse an HTML representation of an activity into a QuizQuizActivity object.
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
class QuizActivityParser
{

    /**
     * The original text (UTF-8)
     *
     * @var string
     */
    protected $text;

    /**
     * The parsed activity
     *
     * @var QuizActivity
     */
    protected $activity;

    /**
     * List of responses to be intepreted as "Yes"
     *
     * @var Array|string
     */
    protected $responsesValidAsYes = array('Yes', 'True');

    /**
     * List of responses to be intepreted as "No"
     *
     * @var Array|string
     */
    protected $responsesValidAsNo = array('No', 'False');

    /**
     * List of responses to be intepreted as "Both"
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

    public function __construct($text)
    {
        $this->text = $text;
    }

    /**
     *
     * @return QuizActivity
     */
    public function parse()
    {
        $this->extractQuizActivity();

        return $this->activity;
    }

    /**
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
     * [activity questions] ::=  <ol>...</ol>
     */
    protected function extractQuizActivity()
    {
        $crawler = new Crawler();
        $crawler->addHtmlContent($this->text, 'UTF-8');
        $crawler = $crawler->filter('div');

        $this->activity = new QuizActivity();

        // Type ABC by default
        $this->activity->setType(QuizActivity::QUIZ_ACTIVITY_TYPE_ABC);

        // Common data
        $this->activity->setId($crawler->attr('data-id'));
        if (!$this->activity->getId()) {
            throw new \Exception(sprintf('QuizActivity must have data-id: "%s"', $crawler->text()));
        }

        $this->activity->setOptions(
                       array(
                           'pagebreak' => $crawler->attr('data-pagebreak') != '0')
        );

        // extract heading (optional)
        $this->activity->setHeading($this->extractHeading($crawler, 'h5'));

        // extract subheading (optional)
        if ($this->activity->getHeading()) {
            $this->activity->setSubHeading($this->extractHeading($crawler, 'h6'));
        }

        // introduction text (optional)
        $this->activity->setIntroduction($this->extractQuizActivityIntroduction($crawler));

        // the questions
        $this->activity->setQuestions($this->extractQuestions($crawler));

        // Unique id for this activity
        $this->activity->setInternalId(hash('crc32', json_encode($this->text)));

        // Look if an ABC activity can be transformed bo YNB
        $this->transformAbcToYnb();
    }

    /**
     * Extract activity heading
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
     * Extract the activity introduction text
     *
     * @param Crawler $crawler
     *
     * @return string
     */
    protected function extractQuizActivityIntroduction(Crawler $crawler)
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
     * Example activity:
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
    protected function extractQuestions(Crawler $crawler)
    {
        // 'ol' node contains all the questions
        $olNode = $crawler->filter('ol');

        if (0 == $olNode->count()) {
            throw new \RuntimeException(
                sprintf(
                    'No questions found for activity id "%s" of type "%s"' . "\n"
                    . $this->activity->getId(),
                    $this->activity->getType()
                ));
        }

        // collect questions
        $questionsList = array();

        $qnodes = $crawler->filter('ol')->children();

        // all the 1st level "li" nodes are questions
        foreach ($qnodes as $qIndex => $qDomNode) {
            $qnode = new Crawler($qDomNode);

            $question = new QuizActivityQuestion();
            $question->setText(CrawlerTools::getNodeHtml($qnode->filter('p')));

            // the 2nd level "li" nodes are responses to that question
            $responses = $qnode->filter('ol');
            if (0 == $responses->count()) {
                throw new \RuntimeException(
                    sprintf(
                        'No responses found for activity id "%s", question #%s of type "abc"',
                        $this->activity->getId(),
                        $qIndex,
                        $this->activity->getType()
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
                    for ($i = 1; $i < $ps->count(); $i++) {
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

        return $questionsList;
    }

    /**
     * Look if a ABC activity can be treated as a YNB activity.
     * - All responses for all questions must be the same.
     * - All responses for each question must be "True", "False" and (opt.) "Both"
     *   or equivalent values.
     */
    protected function transformAbcToYnb()
    {
        if ($this->activity->getType() != QuizActivity::QUIZ_ACTIVITY_TYPE_ABC) {
            return;
        }

        // Look for each group of responses
        $responses = array();

        foreach ($this->activity->getQuestions() as $question) {
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
        $this->activity->setType(QuizActivity::QUIZ_ACTIVITY_TYPE_YNB);
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
