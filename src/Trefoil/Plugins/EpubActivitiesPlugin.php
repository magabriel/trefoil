<?php
namespace Trefoil\Plugins;

use Easybook\Util\Toolkit;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\DomCrawler\Crawler;
use Easybook\Events\EasybookEvents as Events;
use Easybook\Events\ParseEvent;
use Easybook\Events\BaseEvent;

/**
 * This plugin creates the activities in the book.
 *
 */
class EpubActivitiesPlugin extends EpubInteractivePluginBase implements EventSubscriberInterface
{
    protected $activities = array();
    protected static $xrefs = array();

    public static function getSubscribedEvents()
    {
        return array(Events::POST_PARSE => 'onItemPostParse',
                Events::POST_PUBLISH => 'onPostPublish');
    }

    public function onItemPostParse(ParseEvent $event)
    {
        $this->init($event);
        $this->app['book.logger']->debug('onItemPostParse:begin', get_class(), $this->item['config']['content']);

        $content = $event->getContent();

        // Get current activities and links
        $this->activities = isset($event->app['publishing.activities']) ? $event->app
                        ->get('publishing.activities') : array();
        $this->links = $event->app->get('publishing.links');

        try {
            if ('solutions' != $this->item['config']['element']) {
                $content = $this->processActivities($content);
            } else {
                $content = $this->processSolutions($content);
            }

        } catch (Exception $e) {
            // DEBUG
            //echo $e->getTraceAsString();
            throw $e;
        }

        // Get new activities and links
        $event->app->set('publishing.activities', $this->activities);

        $event->setContent($content);

        // Get all activities ids to be removed from TOC
        $activityIds = array();
        foreach ($this->app->get('publishing.activities') as $activity) {
            $activityIds[] = $activity['heading']['id'];
            if (isset($activity['subheading']['id'])) {
                $activityIds[] = $activity['subheading']['id'];
            }
        }

        $this->wrapUp($activityIds);
        $this->app['book.logger']->debug('onItemPostParse:end', get_class(), $this->item['config']['content']);
    }

    public function onPostPublish(BaseEvent $event)
    {
        $this->app = $event->app;
        $this->output = $event->app->get('console.output');

        $this->app['book.logger']->debug('onPostPublish:begin', get_class());

        // create the xref report
        $outputDir = $this->app['publishing.dir.output'];
        $reportFile = $outputDir . '/report-epub-activities-xref.txt';

        $report = '';
        $report .= $this->getActivitiesReport();

        file_put_contents($reportFile, $report);

        $this->app['book.logger']->debug('onPostPublish:end', get_class());
    }

    /**
     * Extract activity from source
     *
     * @param string $content
     * @return string|mixed Modified content with converted activities
     */
    protected function processActivities($content)
    {
        // capture activities
        $regExp = '/';
        $regExp .= '(?<div>';
        $regExp .= '<div[^(class|>)]*';
        $regExp .= 'class="activity"';
        $regExp .= '.*';
        $regExp .= '<\/div>)';
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $me = $this;
        $content = preg_replace_callback($regExp,
                function ($matches) use ($me)
                {
                    // PRUEBAS
                    //print_r($matches['div']);

                    // create a Crawler object
                    $crawler = new Crawler();
                    $crawler->addHtmlContent($matches['div'], 'UTF-8');
                    $crawler = $crawler->filter('div');

                    // extract activity
                    $activity = $me->extractActivity($crawler);

                    // save it for later
                    $this->activities[] = $activity;

                    // and for reporting
                    $this->saveXref($activity);

                    // PRUEBAS
                    //print_r($activity);

                    // convert it
                    $htmlCode = $me->convertActivity($activity);

                    return $htmlCode;
                }, $content);

        return $content;
    }

    /**
     * Generates solutions to activities
     *
     * @param string $content
     * @return string|mixed Modified content with generated solutions
     */
    protected function processSolutions($content)
    {
        $newContent = '';

        // Get all activities
        $this->activities = $this->app->get('publishing.activities');

        foreach ($this->activities as $activity) {
            $newContent .= $this->convertSolution($activity);
        }

        return $content . $newContent;
    }

    /**
     * Example activity:
     *
     * <div class="activity" data-id="1-1">
     *     <h5>Optional heading</h5>
     *     <h6>Optional subheading</h6>
     *     <p>free text</p>
     *     <p>more free text</p>
     *
     *     [activity body]
     * </div>
     *
     * [activity body] ::= [yes-no-both-activity] | [a-b-c-activity]
     *
     * [yes-no-both-activity] ::= <table>..</table>
     * [a-b-c-activity] ::= <ol>...</ol>
     *
     * @param  Crawler $crawler
     * @return array   with activity values
     */
    protected function extractActivity(Crawler $crawler)
    {
        $activity = array();

        // Common data
        $activity['id'] = $crawler->attr('data-id');
        if (!$activity['id']) {
            throw new \Exception(sprintf('Activity must have data-id: "%s"', $crawler->text()));
        }

        $activity['pagebreak'] = ($crawler->attr('data-pagebreak') != '0');

        // extract heading (optional)
        $activity['heading'] = $this->extractHeading($crawler);

        // extract subheading (optional)
        if ($activity['heading']) {
            $activity['subheading'] = $this
                    ->extractSubheading($crawler, $activity['heading']['tag']);
        }

        // introduction text (optional)
        $activity['introduction'] = $this->extractActivityIntroduction($crawler);

        // process by activity type
        if ($crawler->filter('table')->count()) {
            $activity = array_merge($activity, $this->extractActivityYNB($crawler, $activity['id']));
        } elseif ($crawler->filter('ol')->count()) {
            $activity = array_merge($activity, $this->extractActivityABC($crawler, $activity['id']));
        } else {
            throw new \Exception(sprintf('Unknown activity markup: "%s"', $crawler->text()));
        }

        // Unique id for this activity
        $activity['internal-id'] = hash('crc32', json_encode($activity));

        // Look if an ABC activity can be transformed bo YNB
        if ('abc' == $activity['type']) {
            $activity = $this->transformAbcToYnb($activity);
        }

        return $activity;
    }

    /**
     * Example activity YNB:
     *
     *     <div class="table">
     *        <table>
     *            <thead>
     *                <tr>
     *                  <th>This is the question text (overrides activity subheading)</th>
     *                  <th>Yes</th>
     *                  <th>No</th>
     *                  <th>Both</th>
     *                </tr>
     *            </thead>
     *            <tbody>
     *                <tr>
     *                  <td>Question 1</td>
     *                  <td>[solution mark]</td>
     *                  <td></td>
     *                  <td></td>
     *                </tr>
     *                <tr>
     *                  <td>Question 2</td>
     *                  <td></td>
     *                  <td>[solution mark]</td>
     *                  <td></td>
     *                </tr>
     *            </tbody>
     *        </table>
     *     </div>
     *
     *  [solution mark] ::= <not-blank> | [explanation xref]
     *
     *  <dl>
     *      <dt>[explanation xref]</dt>
     *      <dd>explanation text</dd>
     *  </dl>
     *
     * @param  Crawler $crawler
     * @param  string $activityId
     * @return array   with activity values
     */
    protected function extractActivityYNB(Crawler $crawler, $activityId)
    {
        $activity = array();

        // extract common data
        $activity['type'] = 'ynb';

        // process table
        $tableNode = $crawler->filter('table');

        if (0 == $tableNode->count()) {
            throw new \RuntimeException(
                    sprintf(
                            'No "table" found for activity id "%s" of type "ynb"' . "\n"
                                    . $crawler->text(), $activityId));
        }
        // process table rows
        $rows = $tableNode->filter('tr');

        // extract question text (th[0,0])
        $text = $rows->filter('th')->eq(0)
                ->each(
                        function ($node, $i)
                        {
                            return $node->nodeValue;
                        });
        $activity['text'] = $text[0];

        if ($activity['text']) {
            // Override subheading with text
            $activity['subheading']['text'] = $activity['text'];
        }

        // possible responses (th[0, 1...n])
        $responses = $rows->filter('th')
                ->reduce(
                        function ($node, $i)
                        {
                            return (0 != $i);
                        });
        $activity['responses'] = $responses
                ->each(
                        function ($node, $i)
                        {
                            return $node->nodeValue;
                        });

        // questions (td[0, 0..n]),
        $numResponses = count($activity['responses']);
        $questions = $rows->filter('td')
                ->reduce(
                        function ($node, $i) use ($numResponses)
                        {
                            return ($i % ($numResponses + 1) == 0);
                        });

        $activity['questions'] = $questions
                ->each(
                        function ($node, $i)
                        {
                            return $node->nodeValue;
                        });

        // solutions and cross reference to explanation
        $solutions = array();
        $explanationsXref = array();

        foreach ($rows as $rowIdx => $rDomNode) {
            $row = new Crawler($rDomNode);
            $cols = $row->filter('td');
            foreach ($cols as $colIdx => $col) {
                if ($colIdx > 0) {
                    $val = $col->nodeValue;
                    if ($val) {
                        $explanationsXref[$rowIdx - 1] = $val;
                        $solutions[$rowIdx - 1] = $colIdx - 1;
                    }
                }
            }
        }

        $activity['solutions'] = $solutions;

        // explanations
        $explanations = array();
        $dlNode = $crawler->filter('dl');

        if (0 != $dlNode->count()) {

            // we have solutions
            $snodes = $dlNode->children();
            $sid = '';
            foreach ($snodes as $sDomNode) {
                $snode = new Crawler($sDomNode);

                $tag = $this->getNodeName($snode);
                if ('dt' == $tag) {
                    $sid = $sDomNode->nodeValue;
                } else { // dd
                // resolve x-ref
                    $qIndex = array_search($sid, $explanationsXref);
                    if (false !== $qIndex) {
                        $explanations[$qIndex] = $sDomNode->nodeValue;
                    }
                }
            }
        }

        $activity['explanations'] = $explanations;

        return $activity;
    }

    /**
     * Example activity ABC:
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
     * @param string $activityId
     * @return array   with activity values
     */
    protected function extractActivityABC(Crawler $crawler, $activityId)
    {
        // extract common data
        $activity['type'] = 'abc';

        // process dl
        $dlNode = $crawler->filter('ol');

        if (0 == $dlNode->count()) {
            throw new \RuntimeException(
                    sprintf(
                            'No "ol" found for activity id "%s" of type "abc"' . "\n"
                                    . $crawler->text(), $activityId));
        }

        $qnodes = $crawler->filter('ol')->children();
        $questions = array();

        // all the 1st level "li" nodes are questions
        foreach ($qnodes as $qIndex => $qDomNode) {
            $qnode = new Crawler($qDomNode);

            $question = array();
            $question['text'] = $this->getNodeHtml($qnode->filter('p'));

            // the 2nd level "li" nodes are responses to that question
            $responses = $qnode->filter('ol');
            if (0 == $responses->count()) {
                throw new \RuntimeException (
                        sprintf(
                                'No responses found for activity id "%s", question #%s of type "abc"',
                                $activityId, $qIndex));
            }
            $rnodes = $qnode->filter('ol')->children();
            $question['responses'] = array();

            foreach ($rnodes as $rIndex => $rDomNode) {
                $rnode = new Crawler($rDomNode);
                $ps = $rnode->filter('p, ul, ol');

                if ($ps->count() <= 1) {
                    // Response only
                    $question['responses'][$rIndex] = $rnode->text();
                } else {
                    // we have response (first p) and explanation (rest)
                    $question['responses'][$rIndex] = $ps->eq(0)->text();

                    // get all the p's or li's
                    $explanation = array();
                    for ($i = 1; $i < $ps->count(); $i++) {
                        $nodeName = $this->getNodeName($ps->eq($i));
                        if ('p' == $nodeName) {
                            // can contain HTML
                            $explanation[] = '<p>' . $this->getNodeHtml($ps->eq($i)) . '</p>';
                        } else {
                            // ul or ol, so take its li's
                            $liNodes = $ps->eq($i)->children();
                            $explanation[] = '<' . $nodeName . '>';
                            foreach ($liNodes as $liIndex => $liDomNode) {
                                $liNode = new Crawler($liDomNode);

                                // can contain HTML
                                $explanation[] = '<li>' . $this->getNodeHtml($liNode) . '</li>';
                            }
                            $explanation[] = '</' . $nodeName . '>';
                        }
                    }

                    // Join everything
                    $explString = implode('', $explanation);

                    // Clean start and end paragraph tags
                    /*
                    if (substr($explString, 0, 3) == '<p>') {
                        $explString = substr($explString, 3);
                    }

                    if (substr($explString, -4) == '</p>') {
                        $explString = substr($explString, 0, -4);
                    }
                    */
                    $question['explanation'] = $explString;
                }

                if ($rnode->filter('strong')->count() > 0) {
                    $question['solution'] = $rIndex;
                }

            }

            $questions[] = $question;
        }

        $activity['questions'] = $questions;

        // DEBUG
        //print_r($activity);

        return $activity;
    }

    /**
     * Try to transform an ABC type activity to an equivalent YNB
     *
     * @param array $activity
     */
    protected function transformAbcToYnb(array $activity)
    {
        if ('abc' != $activity['type']) {
            return $activity;
        }

        $newActivity = $activity;
        $newActivity['type'] = 'ynb';
        $newActivity['questions'] = array();
        $newActivity['responses'] = array();
        $newActivity['solutions'] = array();
        $newActivity['explanations'] = array();

        // Look for each group of responses
        $responses = array();

        foreach ($activity['questions'] as $qindex => $question) {
            $newActivity['questions'][] = $question['text'];

            $responsesClean = array();
            foreach ($question['responses'] as $response) {
                // Remove ending dot if any
                $response = trim($response);
                $responsesClean[] = ('.' == substr($response, -1)) ? substr($response, 0, -1)
                        : $response;
            }

            if (!$responses) {
                $responses = $responsesClean;
            } else {
                // Look if equals
                if (array_merge(array_diff($responses, $responsesClean),
                        array_diff($responsesClean, $responses))) {
                    // Different, cannot continue
                    return $activity;
                }
            }

            // Look for Yes/No/Both type
            if (!$this->checkYNBTypeResponses($responsesClean)) {
                return $activity;
            }

            $newActivity['responses'] = $responses;

            if (isset($question['solution'])) {
                $newActivity['solutions'][$qindex] = $question['solution'];
            }

            if (isset($question['explanation'])) {
                $newActivity['explanations'][$qindex] = $question['explanation'];
            }
        }

        return $newActivity;
    }

    protected function checkYNBTypeResponses($responses)
    {
        $valid = array(
                'yes' => array('si', 'cierto', 'cierta', 'verdadero', 'verdadera', 'yes', 'true'),
                'no' => array('no', 'falso', 'falsa', 'no', 'false'),
                'both' => array('ambos', 'ambas', 'both'),);

        if (count($responses) > 3) {
            return false;
        }

        // Check that each response is a valid one
        foreach ($responses as $response) {
            $resp = strtolower($response);
            if (!in_array($resp, $valid['yes']) && !in_array($resp, $valid['no'])
                    && !in_array($resp, $valid['both'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Render the activity using the corresponding template.
     *
     * @param  array  $activity
     * @return string rendered output
     */
    protected function convertActivity(array $activity)
    {
        $html = '';

        // PRUEBAS
        //print_r($activity);

        $template = sprintf('activity-%s.twig', $activity['type']);
        $variables = array('activity' => $activity);

        $rendered = $this->app->get('twig')->render($template, $variables);

        // save all the anchor links
        foreach ($activity['questions'] as $qindex => $question) {
            $this->registerAnchorLink('a-' . $activity['id'] . '_' . $qindex);
        }
        $this->registerAnchorLink('start-a-' . $activity['id']);
        $this->registerAnchorLink('skip-a-' . $activity['id']);

        return $rendered;
    }

    /**
     * Render the activity solution using the corresponding template.
     *
     * @param  array  $activity
     * @return string rendered output
     */
    protected function convertSolution(array $activity)
    {
        $html = '';

        // PRUEBAS
        //print_r($activity);

        // Quality check
        foreach ($activity['questions'] as $qindex => $question) {
            // Check there is one solution
            if ('ynb' == $activity['type']) {

                if (!isset($activity['solutions'][$qindex])) {
                    throw new \RuntimeException(
                            sprintf(
                                    'No solution found for activity id="%s", response#="%s"' . "\n",
                                    $activity['id'], $qindex + 1));
                }

            } elseif ('abc' == $activity['type']) {
                if (!isset($question['solution'])) {
                    throw new \RuntimeException(
                            sprintf(
                                    'No solution found for activity id="%s", response#="%s"' . "\n",
                                    $activity['id'], $qindex + 1));
                }
            }
        }

        // Do the actual rendering
        $template = sprintf('activity-%s-solution.twig', $activity['type']);
        $variables = array('activity' => $activity);
        $templatePath = realpath(__DIR__ . '/../../Templates/');

        $rendered = $this->app->get('twig')->render($template, $variables, null, $templatePath);

        // save all the anchor links
        foreach ($activity['questions'] as $qindex => $question) {
            if ('ynb' == $activity['type']) {
                foreach ($activity['responses'] as $rindex => $response) {
                    $this
                            ->registerAnchorLink(
                                    'a-' . $activity['id'] . '_' . $qindex . '_' . $rindex);
                }
            } elseif ('abc' == $activity['type']) {
                foreach ($question['responses'] as $rindex => $response) {
                    $this
                            ->registerAnchorLink(
                                    'a-' . $activity['id'] . '_' . $qindex . '_' . $rindex);
                }
            }
        }

        return $rendered;
    }

    /**
     * Extract activity heading
     *
     * @param  Crawler $crawler
     * @return array   $heading
     */
    protected function extractHeading(Crawler $crawler)
    {
        $headingNode = $crawler->filter('h1, h2, h3, h4, h5, h6');

        $heading = array();

        if (count($headingNode)) {
            $heading['text'] = $headingNode->text();
            $heading['class'] = $headingNode->attr('class');
            $heading['id'] = $headingNode->attr('id');
            $heading['tag'] = $this->getNodeName($headingNode);
        }

        return $heading;
    }

    /**
     * Extract activity subheading
     *
     * @param  Crawler $crawler
     * @param  string  $headingTag
     * @return array   $subheading
     */
    protected function extractSubheading(Crawler $crawler, $headingTag)
    {
        $h = $headingTag;
        $headingNode = $crawler
                ->filter(sprintf('%s~h2, %s~h3, %s~h4, %s~h5, %s~h6', $h, $h, $h, $h, $h));

        $subheading = array();

        if (count($headingNode)) {
            $subheading['text'] = $headingNode->text();
            $subheading['id'] = $headingNode->attr('id');
            $subheading['tag'] = $this->getNodeName($headingNode);
        }

        return $subheading;
    }

    /**
     * Extract the activity introduction text
     *
     * @param  Crawler $crawler
     * @return string
     */
    protected function extractActivityIntroduction(Crawler $crawler)
    {
        // TODO: div>table interferes with Activity Type YNB detection!!!
        // $questionTextNodes = $crawler->filter('div>p, div>ul, div>table');
        $questionTextNodes = $crawler->filter('div>p, div>ul');

        $text = array();
        foreach ($questionTextNodes as $pNode) {
            $p = new Crawler($pNode);
            $text[] = $this->getNodeHtml($p, true);
        }

        if (!$text) {
            return '';
        }

        //return '<p>' . implode('</p></p>', $text) . '</p>';
        return implode('', $text);
    }

    protected function saveXref($activity)
    {
        $name = $this->item['config']['content'];

        if (!isset(static::$xrefs[$name])) {
            static::$xrefs[$name] = array();
        }

        static::$xrefs[$name][] = $activity;
    }

    protected function getActivitiesReport()
    {
        $report = array();

        $report[] = 'Activities X-Ref';
        $report[] = '================';

        $report[] = $this
                ->utf8Sprintf('%-30s %-11s %-30s %-10s %-9s', 'Item', 'Activity id',
                        'Activity heading', 'Type', 'Questions');
        $report[] = $this->utf8Sprintf("%'--30s %'--11s %'--30s %'--10s %'-9s", '', '', '', '', '');

        foreach (static::$xrefs as $item => $activities) {
            $auxItem = $item;
            foreach ($activities as $activity) {
                $report[] = $this
                        ->utf8Sprintf('%-30s %-11s %-30s %-10s %9u', $auxItem, $activity['id'],
                                $activity['heading']['text'], $activity['type'],
                                count($activity['questions']));
                $auxItem = '';
            }
        }

        return implode("\n", $report) . "\n";
    }

    protected function utf8Sprintf($format)
    {
        $args = func_get_args();

        for ($i = 1; $i < count($args); $i++) {
            $args[$i] = iconv('UTF-8', 'ISO-8859-15', $args[$i]);
        }

        return iconv('ISO-8859-15', 'UTF-8', call_user_func_array('sprintf', $args));
    }
}
