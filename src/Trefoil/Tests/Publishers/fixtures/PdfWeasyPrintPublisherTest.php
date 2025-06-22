<?php

/*
 * This file is part of the trefoil application.
 *
 * (c) Javier Eguiluz <magabriel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Trefoil\Tests\Publishers;

use Trefoil\Publishers\PdfWeasyPrintPublisher;
use Symfony\Component\Console\Input\ArrayInput;
use Trefoil\DependencyInjection\Application;
use ZendPdf\PdfDocument;
use ZendPdf\Font;
use ZendPdf\Page;

class PdfWeasyPrintPublisherTest extends \PHPUnit\Framework\TestCase
{
    public function testHelpIsDisplayedWhenThisPublisherIsNotSupported()
    {
        $app = new Application();

        $app['weasyprint.path'] = null;
        $app['weasyprint.default_paths'] = array();
        $app['console.input'] = null;

        $helpMessage = <<<HELP
  easybook:
      parameters:
          weasyprint.path: '/path/to/utils/WeasyPrint/weasyprint'
HELP;

        $publisher = new PdfWeasyPrintPublisher($app);

        try {
            $publisher->checkIfThisPublisherIsSupported();
        } catch (\Exception $e) {
            $this->assertInstanceOf('RuntimeException', $e);
            $this->assertContains($helpMessage, $e->getMessage());
        }
    }

    public function testUserGivesWrongWeasyPrintPath()
    {
        $app = new Application();

        $app['weasyprint.path'] = null;
        $app['weasyprint.default_paths'] = array();
        $app['console.input'] = new ArrayInput(array());

        $publisher = $this->getMockBuilder('Trefoil\Publishers\PdfWeasyPrintPublisher')
            ->setMethods(array('askForWeasyPrintPath'))
            ->setConstructorArgs(array($app))
            ->getMock();

        $publisher->expects($this->once())
            ->method('askForWeasyPrintPath')
            ->will(
                $this->returnValue(uniqid('this_path_does_not_exist_'))
            );

        $this->assertFalse(
            $publisher->checkIfThisPublisherIsSupported(),
            'The given WeasyPrint path is wrong, so this publisher is not supported'
        );
    }

    public function testUserGivesCorrectWeasyPrintPath()
    {
        $app = new Application();

        $app['weasyprint.path'] = null;
        $app['weasyprint.default_paths'] = array();
        $app['console.input'] = new ArrayInput(array());

        $publisher = $this->getMockBuilder('Trefoil\Publishers\PdfWeasyPrintPublisher')
            ->setMethods(array('askForWeasyPrintPath'))
            ->setConstructorArgs(array($app))
            ->getMock();

        $publisher->expects($this->once())
            ->method('askForWeasyPrintPath')
            ->will(
                $this->returnValue(__FILE__)
            );

        $this->assertTrue(
            $publisher->checkIfThisPublisherIsSupported(),
            'The given WeasyPrint path is correct, so this publisher is supported'
        );
    }

    public function testThisPublisherIsSupported()
    {
        $app = new Application();

        // for this test, any valid path is enough to simulate WeasyPrint
        $weasyPrintPath = __FILE__;

        $app['weasyprint.path'] = $weasyPrintPath;
        $publisher = new PdfWeasyPrintPublisher($app);
        $this->assertTrue(
            $publisher->checkIfThisPublisherIsSupported(),
            'If the WeasyPrint path exists, this publisher is supported'
        );

        $app['weasyprint.path'] = null;
        $app['weasyprint.default_paths'] = array($weasyPrintPath);
        $publisher = new PdfWeasyPrintPublisher($app);
        $this->assertTrue(
            $publisher->checkIfThisPublisherIsSupported(),
            'If the WeasyPrint default path exists, this publisher is supported'
        );

        $app['weasyprint.path'] = null;
        $app['weasyprint.default_paths'] = array(null, null, $weasyPrintPath);
        $publisher = new PdfWeasyPrintPublisher($app);
        $this->assertTrue(
            $publisher->checkIfThisPublisherIsSupported(),
            'If any of the WeasyPrint default paths exists, this publisher is supported'
        );
    }

    /**
     * @dataProvider provideCoverSampleData
     */
    public function testBookUsesTheRightCustomCover($existingCoverFiles, $coverThatShouldBeUsed)
    {
        $app = new Application();
        $app['publishing.book.config'] = $this->getBookConfig(false);
        $app['publishing.dir.templates'] = $app['app.dir.cache'] . '/' . uniqid('phpunit_');
        $app['publishing.edition'] = 'print';

        $app['filesystem']->mkdir(
            array(
                $app['publishing.dir.templates'],
                $app['publishing.dir.templates'] . '/print',
                $app['publishing.dir.templates'] . '/pdf',
            )
        );

        foreach ($existingCoverFiles as $cover) {
            $app['filesystem']->touch($app['publishing.dir.templates'] . '/' . $cover);
        }

        $publisher = new PdfWeasyPrintPublisher($app);
        $selectedCoverPath = $publisher->getCustomCover();
        $selectedCover = str_replace($app['publishing.dir.templates'] . '/', '', $selectedCoverPath);

        $this->assertEquals($coverThatShouldBeUsed, $selectedCover);

        $app['filesystem']->remove($app['publishing.dir.templates']);
    }

    public function provideCoverSampleData()
    {
        return array(
            array(
                array('print/cover.pdf', 'pdf/cover.pdf', 'cover.pdf'),
                'print/cover.pdf',
            ),
            array(
                array('print/cover.jpg', 'pdf/cover.pdf', 'cover.pdf'),
                'pdf/cover.pdf',
            ),
            array(
                array('print/cover.jpg', 'pdf/cover.png', 'cover.pdf'),
                'cover.pdf',
            ),
            array(
                array('pdf/cover.pdf', 'cover.pdf'),
                'pdf/cover.pdf',
            ),
            array(
                array('print/cover.pdf'),
                'print/cover.pdf',
            ),
            array(
                array('pdf/cover.pdf'),
                'pdf/cover.pdf',
            ),
            array(
                array('cover.pdf'),
                'cover.pdf',
            ),
            array(
                array('print/cover.png'),
                '',
            ),
        );
    }

    public function testOneSidedPrintedBookDontIncludeBlankPages()
    {
        $app = new Application();
        $app['publishing.book.config'] = $this->getBookConfig(false);
        $app['publishing.edition'] = 'print';

        $bookCss = $app->render('@theme/style.css.twig');

        $this->assertNotContains(
            ".item {\n    page-break-before: right;",
            $bookCss,
            "One-sided books don't include blank pages."
        );
    }

    public function testTwoSidedPrintedBookIncludeBlankPages()
    {
        $app = new Application();
        $app['publishing.book.config'] = $this->getBookConfig(true);
        $app['publishing.edition'] = 'print';

        $bookCss = $app->render('@theme/style.css.twig');

        $this->assertContains(
            ".item {\n    page-break-before: right;",
            $bookCss,
            'Two-sided books include blank pages when needed.'
        );
    }

    private function getBookConfig($twoSided)
    {
        return array(
            'book' => array(
                'language' => 'en',
                'editions' => array(
                    'print' => array(
                        'format' => 'pdf',
                        'theme' => 'clean',
                        'two_sided' => $twoSided,
                    ),
                ),
            ),
        );
    }

    public function testAddBookCover()
    {
        $app = new Application();

        $tmpDir = $app['app.dir.cache'] . '/' . uniqid('phpunit_');
        $app['filesystem']->mkdir($tmpDir);

        $coverFilePath = $tmpDir . '/cover.pdf';
        $bookFilePath = $tmpDir . '/book.pdf';

        $this->createPdfFile($coverFilePath, 'EASYBOOK COVER');
        $this->createPdfFile($bookFilePath, 'easybook contents');

        $publisher = new PdfWeasyPrintPublisher($app);
        $publisher->addBookCover($bookFilePath, $coverFilePath);

        $resultingPdfBook = PdfDocument::load($bookFilePath);

        $this->assertCount(
            2,
            $resultingPdfBook->pages,
            'The cover page has been added to the book.'
        );

        $this->assertFileExists(
            $coverFilePath,
            'The cover PDF file is NOT deleted after adding it to the book.'
        );

        $this->assertContains(
            'EASYBOOK COVER',
            $resultingPdfBook->render(),
            'The resulting book contains the cover text.'
        );
        $this->assertContains(
            'easybook contents',
            $resultingPdfBook->render(),
            'The resulting book contains the original book contents.'
        );

        $app['filesystem']->remove($tmpDir);
    }

    private function createPdfFile($filePath, $contents)
    {
        $pdf = new PdfDocument();

        $page = new Page(Page::SIZE_A4);

        $font = Font::fontWithName(Font::FONT_HELVETICA);
        $page->setFont($font, 18);
        $page->drawText($contents, 50, 780);

        $pdf->pages[] = $page;

        $pdf->save($filePath);
    }
}
