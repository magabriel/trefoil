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

namespace Trefoil\Publishers;

use Trefoil\Util\Toolkit;

/**
 * It publishes the book as a PDF file. All the internal links are transformed
 * into clickable cross-section book links. These links even display automatically
 * the page number where they point into, so no information is lost when printing
 * the book.
 */
class PdfPrinceXmlPublisher extends PdfPublisher
{
    /**
     * @return bool
     */
    public function checkIfThisPublisherIsSupported(): bool
    {
        if (null !== $this->app['prince.path'] && file_exists($this->app['prince.path'])) {
            $princeXMLPath = $this->app['prince.path'];
        } else {
            $princeXMLPath = $this->findPrinceXMLPath();
        }

        $this->app['prince.path'] = $princeXMLPath;

        return null !== $princeXMLPath && file_exists($princeXMLPath);
    }

    public function assembleBook(): void
    {
        // reuse output temp dir for easy debugging and to avoid polluting the cache dir
        $tmpDir = $this->app['app.dir.cache'] . '/easybook_pdf';
        if ($this->app['filesystem']->exists($tmpDir)) {
            $this->app['filesystem']->remove($tmpDir);
        }
        $this->app['filesystem']->mkdir($tmpDir);

        // consolidate book images to temp dir
        $imagesDir = $tmpDir . '/images';
        if (!$this->app['filesystem']->exists($imagesDir)) {
            $this->app['filesystem']->mkdir($imagesDir);
        }
        $this->prepareBookImages($imagesDir);
        $this->prepareBookCoverImage($imagesDir);

        // use PrinceXML to transform the HTML book into a PDF book
        $prince = $this->app['prince'];
        $prince->setBaseURL($imagesDir);

        // Prepare and add stylesheets before PDF conversion
        if ($this->app->edition('include_styles')) {
            $defaultStyles = $tmpDir . '/default_styles.css';
            $this->app->render(
                '@theme/style.css.twig',
                ['resources_dir' => $this->app['app.dir.resources'] . '/'],
                $defaultStyles
            );

            $prince->addStyleSheet($defaultStyles);
        }

        $customCss = $this->getCustomCssFile();
        $hasCustomCss = $customCss !== null && file_exists($customCss);
        if ($hasCustomCss) {
            $customStyles = $tmpDir . '/styles.css';
            $customCssName = pathinfo($customCss, PATHINFO_BASENAME);
            if ('style.css' === $customCssName) {
                $this->app['filesystem']->copy($customCss, $customStyles);
            } else {
                $this->app->render(
                    $customCssName,
                    [],
                    $customStyles
                );
            }

            $prince->addStyleSheet($customStyles);
        }

        // implode all the contents to create the whole book
        $htmlBookFilePath = $tmpDir . '/book.html';
        $this->app->render(
            'book.twig',
            [
                'items' => $this->app['publishing.items'],
                'has_custom_css' => $hasCustomCss,
            ],
            $htmlBookFilePath
        );

        // Optional first-pass script
        $js = $this->getJavascriptFile('prince-1st-pass.js');
        if ($js !== null && file_exists($js)) {
            $prince->addScript($js);
            $prince->setJavaScript(true);
        }

        // Run the first pass
        $outputMessages = [];
        $pdfBookFilePath = $this->app['publishing.dir.output'] . '/book.pdf';
        $prince->convert_file_to_file($htmlBookFilePath, $pdfBookFilePath, $outputMessages);
        $this->displayPdfConversionErrors($outputMessages);

        // Generate the second-pass input script
        $secondPassFile = '';
        $output = $this->getGeneratedOutput($outputMessages);
        if ($output !== '') {
            $secondPassFile = $tmpDir . '/prince-2nd-pass-input.js';
            file_put_contents($secondPassFile, $output);
        }

        // Optional second-pass script
        $js = $this->getJavascriptFile('prince-2nd-pass.js');
        if ($js !== null && file_exists($js)) {
            $prince->addScript($js);
            $prince->setJavaScript(true);

            // Add the second-pass input script
            $js = $secondPassFile;
            if (file_exists($js)) {
                $prince->addScript($js);
                $prince->setJavaScript(true);
            }

            $this->app['console.output']->writeln("\n > PrinceXML running second pass");
            $outputMessages = [];
            $pdfBookFilePath = $this->app['publishing.dir.output'] . '/book.pdf';
            $prince->convert_file_to_file($htmlBookFilePath, $pdfBookFilePath, $outputMessages);
            $this->displayPdfConversionErrors($outputMessages);
        }

        $this->addBookCover($pdfBookFilePath, $this->getCustomCover());
    }

    /**
     * Looks for the executable of the PrinceXML library.
     *
     * @return string The absolute path of the executable
     *
     * @throws \RuntimeException If the PrinceXML executable is not found
     */
    public function findPrinceXMLPath(): string
    {
        /** @var string $path */
        foreach ($this->app['prince.default_paths'] as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // the executable couldn't be found in the common
        // installation directories. Ask the user for the path
        $isInteractive = null !== $this->app['console.input'] && $this->app['console.input']->isInteractive();
        if ($isInteractive) {
            return $this->askForPrinceXMLPath();
        }

        throw new \RuntimeException(
            sprintf(
                "ERROR: The PrinceXML library needed to generate PDF books cannot be found.\n"
                    . " Check that you have installed PrinceXML in a common directory \n"
                    . " or set your custom PrinceXML path in the book's config.yml file:\n\n"
                    . '%s',
                $this->getSampleYamlConfiguration()
            )
        );
    }

    /**
     * @return string
     */
    public function askForPrinceXMLPath(): string
    {
        $this->app['console.output']->write(
            sprintf(
                " In order to generate PDF files, PrinceXML library must be installed. \n\n"
                    . " We couldn't find PrinceXML executable in any of the following directories: \n"
                    . "   -> %s \n\n"
                    . " If you haven't installed it yet, you can download a fully-functional demo at: \n"
                    . " %s \n\n"
                    . " If you have installed in a custom directory, please type its full absolute path:\n > ",
                implode($this->app['prince.default_paths'], "\n   -> "),
                'http://www.princexml.com/download'
            )
        );

        $userGivenPath = trim(fgets(STDIN));

        // output a newline for aesthetic reasons
        $this->app['console.output']->write("\n");

        return $userGivenPath;
    }

    /**
     * It displays the error messages generated by the PDF conversion
     * process in a user-friendly way.
     *
     * @param array $outputMessages The array of messages generated by PrinceXML
     */
    public function displayPdfConversionErrors($outputMessages): void
    {
        if (count($outputMessages) > 0) {
            $this->app['console.output']->writeln("\n PrinceXML errors and warnings");
            $this->app['console.output']->writeln(" -----------------------------\n");

            foreach ($outputMessages as $message) {
                // ignore the output from prince
                if ($message[0] !== 'out') {
                    $this->app['console.output']->writeln(
                        '   [' . strtoupper($message[0]) . '] ' . ucfirst($message[2]) . ' (' . $message[1] . ')'
                    );
                }
            }

            $this->app['console.output']->writeln("\n");
        }
    }

    /**
     * Returns the output messages generated by the PDF conversion (not errors or warnings).
     *
     * @param array $outputMessages The array of messages generated by PrinceXML
     * @return string
     */
    public function getGeneratedOutput(array $outputMessages): string
    {
        $output = [];
        if (count($outputMessages) > 0) {
            foreach ($outputMessages as $message) {
                // get only the output from prince
                if ($message[0] === 'out') {
                    $output[] = $message[1];
                }
            }
        }

        return implode("\n", $output);
    }


    /**
     * It prepares the book cover image (if the book defines one).
     *
     * @param string $targetDir The directory where the cover image is copied.
     *
     * @return array|null Book cover image data or null if the book doesn't
     *                    include a cover image.
     */
    private function prepareBookCoverImage($targetDir): ?array
    {
        $cover = null;

        if (null !== $image = $this->app->getCustomCoverImage()) {
            [$width, $height, $type] = getimagesize($image);

            $cover = [
                'height' => $height,
                'width' => $width,
                'filePath' => 'images/' . basename($image),
                'mediaType' => image_type_to_mime_type($type)
            ];

            $this->app['filesystem']->copy($image, $targetDir . '/' . basename($image));
        }

        return $cover;
    }

    /*
     * It looks for custom book cover PDF. The search order is:
     *   1. <book>/Resources/Templates/<edition-name>/cover.pdf
     *   2. <book>/Resources/Templates/<format-name>/cover.pdf
     *   3. <book>/Resources/Templates/cover.pdf
     *
     * @param string $coverFileName The name of the PDF file that defines the book cover
     *
     * @return null|string The filePath of the PDF cover or null if none exists
     */
    /**
     * @param string $coverFileName
     * @return string|null
     */
    public function getCustomCover($coverFileName = 'cover.pdf'): ?string
    {
        $paths = [
            $this->app['publishing.dir.templates'] . '/' . $this->app['publishing.edition'],
            $this->app['publishing.dir.templates'] . '/' . $this->app->edition('format'),
            $this->app['publishing.dir.templates'],
        ];

        return $this->app->getFirstExistingFile($coverFileName, $paths);
    }

    /*
     * It looks for PrinceXML javascript scripts. The search order is:
     *   1. <theme>/<edition-name>/js/prince.js
     *   2. <theme>/<format-name>/js/prince.js (format=Pdf)
     *
     * @param string $jsFileName The name of the Javascript script file.
     *
     * @return null|string The filePath of the script or null if none exists
 */
    /**
     * @param string $jsFileName
     * @return string|null
     */
    public function getJavascriptFile($jsFileName = 'prince.js'): ?string
    {
        $paths = [
            Toolkit::getCurrentThemeDir($this->app) . '/' . ucfirst($this->app['publishing.edition']) . '/Templates/js',
            Toolkit::getCurrentThemeDir($this->app) . '/' . Toolkit::getCurrentFormat($this->app) . '/Templates/js'
        ];

        return $this->app->getFirstExistingFile($jsFileName, $paths);
    }

    /**
     * It returns the needed configuration to set up the custom PrinceXML path
     * using YAML format.
     *
     * @return string The sample YAML configuration
     */
    private function getSampleYamlConfiguration(): string
    {
        return <<<YAML
  easybook:
      parameters:
          prince.path: '/path/to/utils/PrinceXML/prince'

  book:
      title:  ...
      author: ...
      # ...
YAML;
    }
}
