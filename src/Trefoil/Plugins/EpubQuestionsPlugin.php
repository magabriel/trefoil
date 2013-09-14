<?php
namespace Trefoil\Plugins;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\DomCrawler\Crawler;

use Easybook\Events\EasybookEvents as Events;
use Easybook\Events\ParseEvent;
use Easybook\Events\BaseEvent;

/**
 * This plugin creates the questions in the book.
 *
 */
class EpubQuestionsPlugin extends EpubInteractivePluginBase implements EventSubscriberInterface
{
    protected $questionSets = array();
    protected static $xrefs = array();

    public static function getSubscribedEvents()
    {
        return array(Events::POST_PARSE => 'onItemPostParse',
                Events::PRE_DECORATE => array('onItemPreDecorate', -500),
                Events::POST_PUBLISH => 'onPostPublish');
    }

    public function onItemPostParse(ParseEvent $event)
    {
        $this->init($event);
        $this->app['book.logger']->debug('onItemPostParse:begin', get_class(), $this->item['config']['content']);

        $content = $event->getContent();

        // Get current questions and links
        $this->questions = isset($event->app['publishing.questionsSets']) ? $event->app
                        ->get('publishing.questionSets') : array();
        $this->links = $event->app->get('publishing.links');

        try {
            if ('solutions' != $this->item['config']['element']) {
                $content = $this->processQuestionSets($content);
            } else {
                $content = $this->processSolutions($content);
            }

        } catch (Exception $e) {
            // DEBUG
            //echo $e->getTraceAsString();
            throw $e;
        }

        // Get new questions
        $event->app->set('publishing.questionSets', $this->questionSets);

        $event->setContent($content);

        // Get all questionSets ids to be removed from TOC
        $questionSetsIds = array();
        foreach ($this->app->get('publishing.questionSets') as $questionSet) {
            $questionSetsIds[] = $questionSet['heading']['id'];
            if (isset($questionSet['subheading']['id'])) {
                $questionSetsIds[] = $questionSet['subheading']['id'];
            }
        }

        $this->wrapUp($questionSetsIds);
        $this->app['book.logger']->debug('onItemPostParse:end', get_class(), $this->item['config']['content']);
    }

    public function onItemPreDecorate(BaseEvent $event)
    {
        $item = $event->getItem();

        $item['content'] = $this->fixLinks($item['content'], $event->app->get('publishing.links'));

        $event->setItem($item);
    }

    public function onPostPublish(BaseEvent $event)
    {
        $this->app = $event->app;
        $this->output = $event->app->get('console.output');

        $this->app['book.logger']->debug('onPostPublish:begin', get_class());

        // create the xref report
        $outputDir = $this->app['publishing.dir.output'];
        $reportFile = $outputDir . '/report-epub-questions-xref.txt';

        $report = '';
        $report .= $this->getQuestionsReport();

        file_put_contents($reportFile, $report);

        $this->app['book.logger']->debug('onPostPublish:end', get_class());
    }

    /**
     * Extract questionSets from source
     *
     * @param string $content
     * @return string|mixed Modified content with converted questionSets
     */
    protected function processQuestionSets($content)
    {
        // capture questionSets
        $regExp = '/';
        $regExp .= '(?<div>';
        $regExp .= '<div[^(class|>)]*';
        $regExp .= 'class="questions"';
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

                    // extract questionSet
                    $questionSet = $me->extractQuestionSet($crawler);

                    if ($this->questionSetIdExists($questionSet['id'])) {
                        throw new \Exception(
                                sprintf('questionSet id "%s" already used', $questionSet['id']));
                    }

                    // save it for later
                    $this->questionSets[] = $questionSet;

                    // and for reporting
                    $this->saveXref($questionSet);

                    // PRUEBAS
                    //print_r($questionSet);

                    // convert it
                    $htmlCode = $me->convertQuestionSet($questionSet);

                    return $htmlCode;
                }, $content);

        return $content;
    }

    protected function questionSetIdExists($id)
    {
        foreach ($this->questionSets as $questionSet) {
            if ($questionSet['id'] == $id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generates solutions to questions
     *
     * @param string $content
     * @return string|mixed Modified content with generated solutions
     */
    protected function processSolutions($content)
    {
        $newContent = '';

        // Get all activities
        $this->questionSets = $this->app->get('publishing.questionSets');

        foreach ($this->questionSets as $questionSet) {
            $newContent .= $this->convertSolution($questionSet);
        }

        return $content . $newContent;
    }

    /**
     * Example questionSet:
     *
     * <div class="questions" data-id="1-1">
     *     <h5>Optional heading (title, i.e. "Exercise 2"))</h5>
     *     <h6>Optional subheading (overall question, i.e. "Think and respond to the following questions")</h6>
     *
     *     <p>Introduction (statement of the problem)</p>
     *     <p>free text</p>
     *     <p>more free text</p>
     *     <ul>
     *         <li>An unordered list</li>
     *     </ul>
     *
     *     [questionSet body]
     * </div>
     *
     * [questionSet body] ::= <ol>...</ol>
     *
     * @param  Crawler $crawler
     * @return array   with questionSet values
     */
    protected function extractQuestionSet(Crawler $crawler)
    {
        // DEBUG
        //print_r($this->getNodeHtml($crawler, true));

        $questionSet = array();

        // Common data
        $questionSet['id'] = $crawler->attr('data-id');
        if (!$questionSet['id']) {
            throw new \Exception(sprintf('questionSet must have data-id: "%s"', $crawler->text()));
        }

        $questionSet['pagebreak'] = ($crawler->attr('data-pagebreak') != '0');

        // extract heading (optional)
        $questionSet['heading'] = $this->extractHeading($crawler);

        // extract subheading (optional)
        if ($questionSet['heading']) {
            $questionSet['subheading'] = $this
                    ->extractSubheading($crawler, $questionSet['heading']['tag']);
        }

        // introduction text (optional)
        $questionSet['introduction'] = $this->extractQuestionSetIntroduction($crawler);

        // process
        if ($crawler->filter('ol')->count()) {
            $questionSet = array_merge($questionSet,
                    $this->extractQuestionSetQuestions($crawler, $questionSet['id']));
        } else {
            throw new \Exception(sprintf('Unknown questionSet markup: "%s"', $crawler->text()));
        }

        // Unique id for this question
        $questionSet['internal-id'] = hash('crc32', json_encode($questionSet));

        return $questionSet;
    }

    /**
     * Example questionSet body:
     *
     *  <ol>
     *    <li><p>Text of question 1</p>
     *        ... whatever text and markup ...
     *
     *      <h6>Solution heading</h6>
     *        <p>Solution text</p>
     *        ... whatever text and markup ...
     *    </li>
     *    <li><p>Text of question 2</p>
     *        ...
     *    </li>
     *  </ol>
     *
     * @param Crawler $crawler
     * @param string $questionSetId
     * @return array  with questionSet values
     */
    protected function extractQuestionSetQuestions(Crawler $crawler, $questionSetId)
    {
        // DEBUG
        //echo "questionSetId $questionSetId\n";

        // process ol
        $olNode = $crawler->filter('ol');

        if (0 == $olNode->count()) {
            throw new \RuntimeException(
                    sprintf('No "ol" found for questionSet id "%s"', $questionSetId));
        }

        $qnodes = $crawler->filter('ol')->children();
        $questions = array();

        // all the 1st level "li" nodes are questions
        foreach ($qnodes as $qIndex => $qDomNode) {
            $qnode = new Crawler($qDomNode);

            $question = array();
            $question['text'] = '';

            $qnodeNodes = $qnode->children();

            if (0 == $qnodeNodes->count()) {
                // this "li" question node doesn't have anything in it except some text
                $question['text'] = '<p>' . $qnode->text() . '</p>';
            } else {
                // a normal question
                foreach ($qnodeNodes as $qnodeIndex => $qnodeDomNode) {
                    $qnodeNode = new Crawler($qnodeDomNode);

                    $nodeName = $this->getNodeName($qnodeNode);
                    if ('h6' == $nodeName) {
                        // solution heading
                        $question['solution'] = array();
                        $question['solution']['heading'] = $qnodeNode->text();
                        $question['solution']['text'] = '';
                    } else {
                        // question text or solution text
                        if (isset($question['solution'])) {
                            // solution
                            $question['solution']['text'] .= $this->getNodeHtml($qnodeNode, true);
                        } else {
                            // question text
                            $question['text'] .= $this->getNodeHtml($qnodeNode, true);
                        }
                    }
                }
            }

            $questions[] = $question;
        }

        // DEBUG
        //print_r($questions);

        $questionSet['questions'] = $questions;

        return $questionSet;
    }

    /**
     * Render the questionSet using the corresponding template.
     *
     * @param  array  $questions
     * @return string rendered output
     */
    protected function convertQuestionSet(array $questionSet)
    {
        $html = '';

        // PRUEBAS
        //print_r($questionSet);

        $template = 'questions.twig';
        $variables = array('questionSet' => $questionSet);
        $templatePath = realpath(__DIR__ . '/../../Templates/');

        $rendered = $this->app->get('twig')->render($template, $variables, null, $templatePath);

        // save all the anchor links
        foreach ($questionSet['questions'] as $qindex => $question) {
            $this->registerAnchorLink('q-' . $questionSet['id'] . '_' . $qindex);
        }
        $this->registerAnchorLink('start-q-' . $questionSet['id']);
        $this->registerAnchorLink('skip-q-' . $questionSet['id']);

        return $rendered;
    }

    /**
     * Render the questionSet solutions using the corresponding template.
     *
     * @param  array  $questionSet
     * @return string rendered output
     */
    protected function convertSolution(array $questionSet)
    {
        $html = '';

        // PRUEBAS
        //print_r($questions);

        // Quality check
        foreach ($questionSet['questions'] as $qindex => $question) {
            // Check there is a solution
            if (!isset($question['solution'])) {
                /*
                throw new \RuntimeException(
                        sprintf(
                                'No solution found for questionSet id="%s", question#="%s"'
                                        . "\n", $questionSet['id'],
                                $qindex + 1));
                 */
                // no solution means 'check with your teacher'
                $question['solution'] = '';
            }
        }

        // Do the actual rendering
        $template = 'questions-solutions.twig';
        $variables = array('questionSet' => $questionSet);
        $templatePath = realpath(__DIR__ . '/../../Templates/');

        $rendered = $this->app->get('twig')->render($template, $variables, null, $templatePath);

        // save all the anchor links
        foreach ($questionSet['questions'] as $qindex => $question) {
            $this->registerAnchorLink('q-' . $questionSet['id'] . '_' . $qindex . '-solution');
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
    protected function extractQuestionSetIntroduction(Crawler $crawler)
    {
        $questionTextNodes = $crawler->filter('div>p, div>ul, div>table');

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

    /**
     * Return the node name
     *
     * @param Crawler $node
     * @return Ambigous <NULL>
     */
    protected function getNodeName(Crawler $node)
    {
        foreach ($node as $i => $n) {
            $domNode = $n;
            break;
        }

        return $domNode->nodeName;
    }

    /**
     * Return the node HTML contents
     *
     * @param Crawler $node
     * @return string|mixed
     */
    protected function getNodeHtml(Crawler $node, $withTags = false)
    {
        $domNode = null;

        foreach ($node as $i => $n) {
            $domNode = $n;
            break;
        }

        $html = $domNode->ownerDocument->saveHtml($domNode);

        // remove surrounding tag
        $regExp = '/';
        $regExp .= '<(?<tag>.*)>';
        $regExp .= '(?<html>.*)$';
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $html = preg_replace_callback($regExp,
                function ($matches) use ($withTags)
                {
                    if ($withTags) {
                        return '<' . $matches['tag'] . '>' . $matches['html'];
                    }
                    $clean = substr($matches['html'], 0, -strlen('</' . $matches['tag'] . '>'));
                    return $clean;
                }, $html);

        return $html;
    }

    protected function saveXref($questionSet)
    {
        $name = $this->item['config']['content'];

        if (!isset(static::$xrefs[$name])) {
            static::$xrefs[$name] = array();
        }

        static::$xrefs[$name][] = $questionSet;
    }

    protected function getQuestionsReport()
    {
        $report = array();

        $report[] = 'Questions X-Ref';
        $report[] = '===============';

        $report[] = $this
                ->utf8Sprintf('%-30s %-11s %-30s %-9s', 'Item', 'Activity id',
                        'Activity heading', 'Questions');
        $report[] = $this->utf8Sprintf("%'--30s %'--11s %'--30s %'-9s", '', '', '', '');

        foreach (static::$xrefs as $item => $questionSet) {
            $auxItem = $item;
            foreach ($questionSet as $question) {
                $report[] = $this
                        ->utf8Sprintf('%-30s %-11s %-30s %9u', $auxItem, $question['id'],
                                $question['heading']['text'], count($question['questions']));
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
