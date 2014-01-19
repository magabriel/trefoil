<?php

namespace Trefoil\Helpers;

use Michelf\MarkdownExtra;
use PHPUnit_Framework_TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Generated by PHPUnit_SkeletonGenerator 1.2.1 on 2014-01-13 at 17:49:11.
 */
class QuizQuestionnaireParserTest extends PHPUnit_Framework_TestCase
{

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {

    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {

    }

    /**
     * @covers Trefoil\Helpers\QuestionnaireParser::parse
     */
    public function testParseQuestionnaireWithResponses()
    {
        $questionnaire = $this->loadFixture(__DIR__ . '/fixtures/questionnaire-responses.md');
        $expected = $this->loadExpected(__DIR__ . '/fixtures/questionnaire-responses-expected.yml');

        // internal id is calculated as a hash from source and cannot be predicted
        $expected->setInternalId($questionnaire->getInternalId());

        $this->assertEquals($expected, $questionnaire);
    }

    /**
     * @covers Trefoil\Helpers\QuestionnaireParser::parse
     */
    public function testParseQuestionnaireWithoutResponses()
    {
        $questionnaire = $this->loadFixture(__DIR__ . '/fixtures/questionnaire-no-responses.md');
        $expected = $this->loadExpected(__DIR__ . '/fixtures/questionnaire-no-responses-expected.yml');

        // internal id is calculated as a hash from source and cannot be predicted
        $expected->setInternalId($questionnaire->getInternalId());

        $this->assertEquals($expected, $questionnaire);
    }

    protected function loadFixture($mdFile)
    {
        $fixture = file_get_contents($mdFile);
        $markdown = new MarkdownExtra();
        $text = $markdown->transform($fixture);

        $parser = new QuizQuestionnaireParser($text);

        $questionnaire = $parser->parse();

        return $questionnaire;
    }

    protected function loadExpected($ymlFile)
    {
        $yml = Yaml::parse(file_get_contents($ymlFile));

        $questionnaire = $yml['questionnaire'];

        $expected = new QuizQuestionnaire();
        $expected->setId($questionnaire['id']);
        $expected->setOptions($questionnaire['options']);
        $expected->setHeading($questionnaire['heading']);
        $expected->setSubHeading($questionnaire['subheading']);
        $expected->setIntroduction($this->clean($questionnaire['introduction']));

        $questions = array();
        foreach ($questionnaire['questions'] as $question) {
            $questionObj = new QuizQuestionnaireQuestion();
            $questionObj->setText($question['text']);
            $questionObj->setHeading($question['heading']);
            $questionObj->setSolution($question['solution']);

            $questions[] = $questionObj;
        }

        $expected->setQuestions($questions);

        return $expected;
    }

    protected function clean($string)
    {
        if (null === $string) {
            return null;
        }

        return str_replace(array('> <', "\n"), array('><', ''), $string);
    }
}
