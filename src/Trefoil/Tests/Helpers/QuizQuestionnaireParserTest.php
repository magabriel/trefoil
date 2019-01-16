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

use Michelf\MarkdownExtra;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Generated by PHPUnit_SkeletonGenerator 1.2.1 on 2014-01-13 at 17:49:11.
 */
class QuizQuestionnaireParserTest extends TestCase
{

    public function testParseQuestionnaireWithResponses(): void
    {
        $questionnaire = $this->loadFixture(__DIR__.'/fixtures/questionnaire-responses.md');
        $expected = $this->loadExpected(__DIR__.'/fixtures/questionnaire-responses-expected.yml');

        // internal id is calculated as a hash from source and cannot be predicted
        $expected->setInternalId($questionnaire->getInternalId());

        static::assertEquals($expected, $questionnaire);
    }

    /**
     * @param $mdFile
     * @return QuizItem
     * @throws \Exception
     */
    protected function loadFixture($mdFile): QuizItem
    {
        $fixture = file_get_contents($mdFile);
        $markdown = new MarkdownExtra();
        $text = $markdown->transform($fixture);

        $parser = new QuizQuestionnaireParser($text);

        return $parser->parse();
    }

    /**
     * @param $ymlFile
     * @return QuizQuestionnaire
     */
    protected function loadExpected($ymlFile): QuizQuestionnaire
    {
        $yml = Yaml::parse(file_get_contents($ymlFile));

        $questionnaire = $yml['questionnaire'];

        $expected = new QuizQuestionnaire();
        $expected->setId($questionnaire['id']);
        $expected->setOptions($questionnaire['options']);
        $expected->setHeading($questionnaire['heading']);
        $expected->setSubHeading($questionnaire['subheading']);
        $expected->setIntroduction($this->clean($questionnaire['introduction']));

        $questions = [];
        /** @var string[][][] $questionnaire */
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

    /**
     * @param $string
     * @return mixed|null
     */
    protected function clean($string)
    {
        if (null === $string) {
            return null;
        }

        return str_replace(['> <', "\n"], ['><', ''], $string);
    }

    public function testParseQuestionnaireWithoutResponses(): void
    {
        $questionnaire = $this->loadFixture(__DIR__.'/fixtures/questionnaire-no-responses.md');
        $expected = $this->loadExpected(__DIR__.'/fixtures/questionnaire-no-responses-expected.yml');

        // internal id is calculated as a hash from source and cannot be predicted
        $expected->setInternalId($questionnaire->getInternalId());

        static::assertEquals($expected, $questionnaire);
    }

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
}
