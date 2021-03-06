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

use Easybook\Events\BaseEvent;
use Easybook\Events\EasybookEvents as Events;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Process\Process;
use Trefoil\Util\Toolkit;

/**
 * It publishes the book as an EPUB file. All the internal links are transformed
 * into clickable cross-section book links.
 * -- This is Trefoil own implementation of this publisher --
 * It is based on the original Easybook Epub2Publisher with some added functionality
 * and fixes.
 */
class Epub2Publisher extends HtmlPublisher
{
    // 'cover' is a very special content for epub books
    protected $excludedElements = ['cover'];

    public function loadContents(): void
    {
        /* 'toc' content type usually makes no sense in epub books (see below)
         * so exclude it if not explicitly requested
         */
        if (!$this->app->edition('include_html_toc')) {
            $this->excludedElements[] = 'toc';
        }

        // strip excluded elements before loading book contents
        $contents = [];
        /** @var array[] $content */
        foreach ($this->app->book('contents') as $content) {
            if (!in_array($content['element'], $this->excludedElements, true)) {
                $contents[] = $content;
            }
        }
        $this->app->book('contents', $contents);

        parent::loadContents();

        /* assign the normalized page names here to make them available
         * to the plugins.
        */
        $bookItems = $this->normalizePageNames($this->app['publishing.items']);
        $this->app['publishing.items'] = $bookItems;
    }

    /**
     * The generated HTML pages aren't named after the items' original slugs
     * (e.g. introduction-to-lorem-ipsum.html) but using their content types
     * and numbers (e.g. chapter-1.html).
     * This method creates a new property for each item called 'page_name' which
     * stores the normalized page name that should have this chunk.
     *
     * @param array $items The original book items.
     * @return array The book items with their new 'page_name' property.
     */
    private function normalizePageNames($items): array
    {
        $itemsWithNormalizedPageNames = [];

        foreach ($items as $item) {

            $itemPageName = array_key_exists(
                'number',
                $item['config']) ? $item['config']['element'].' '.$item['config']['number'] : $item['config']['element'];

            $item['page_name'] = $this->app->slugifyUniquely($itemPageName);

            $itemsWithNormalizedPageNames[] = $item;
        }

        return $itemsWithNormalizedPageNames;
    }

    /**
     * Overrides the base publisher method to avoid the decoration of the book items.
     * Instead of using the regular Twig templates based on the item type (e.g. chapter),
     * ePub books items are decorated with some special Twig templates.
     */
    public function decorateContents(): void
    {
        $decoratedItems = [];

        /** @var array[] $item */
        foreach ($this->app['publishing.items'] as $item) {
            $this->app['publishing.active_item'] = $item;

            // filter the original item content before decorating it
            $event = new BaseEvent($this->app);
            $this->app->dispatch(Events::PRE_DECORATE, $event);

            // try first to render the specific template for each content
            // type, if it exists (e.g. toc.twig, chapter.twig, etc.) and
            // use chunk.twig as the fallback template
            $templateVariables = [
                'item'           => $item,
                'has_custom_css' => null !== $this->getCustomCssFile(),
            ];
            try {
                $templateName = $item['config']['element'].'.twig';
                $item['content'] = $this->app->render($templateName, $templateVariables);
            } catch (\Twig_Error_Loader $e) {
                $item['content'] = $this->app->render('chunk.twig', $templateVariables);
            }
            $this->app['publishing.active_item'] = $item;

            $event = new BaseEvent($this->app);
            $this->app->dispatch(Events::POST_DECORATE, $event);

            // get again 'item' object because POST_DECORATE event can modify it
            $decoratedItems[] = $this->app['publishing.active_item'];
        }

        $this->app['publishing.items'] = $decoratedItems;
    }

    public function assembleBook(): void
    {
        $bookTmpDir = $this->prepareBookTemporaryDirectory();

        // generate easybook CSS file
        if ($this->app->edition('include_styles')) {
            $this->app->render(
                '@theme/style.css.twig',
                ['resources_dir' => '..'],
                $bookTmpDir.'/book/OEBPS/css/easybook.css');
        }

        // generate custom CSS file
        $customCss = $this->getCustomCssFile();
        $customCssName = null;
        if ($customCss !== null) {
            $customCssName = pathinfo($customCss, PATHINFO_BASENAME);
        }
        if ($customCssName !== null && 'style.css' === $customCssName) {
            $this->app['filesystem']->copy(
                $customCss,
                $bookTmpDir.'/book/OEBPS/css/styles.css',
                true);
        } else {
            // new in Trefoil:
            // generate custom CSS file from template
            if ('style.css.twig' === $customCssName) {
                $this->app->render(
                    'style.css.twig',
                    [],
                    $bookTmpDir.'/book/OEBPS/css/styles.css');
            }
        }
        $hasCustomCss = ($customCss !== null);

        $bookItems = $this->app['publishing.items'];

        // generate one HTML page for every book item
        foreach ($bookItems as $item) {
            $renderedTemplatePath = $bookTmpDir.'/book/OEBPS/'.$item['page_name'].'.html';

            // book items have already been rendered, so we just need
            // to copy them to the temp dir
            file_put_contents($renderedTemplatePath, $item['content']);
        }

        $bookImages = $this->prepareBookImages($bookTmpDir.'/book/OEBPS/images');
        $bookCover = $this->prepareBookCoverImage($bookTmpDir.'/book/OEBPS/images');
        $bookFonts = $this->prepareBookFonts($bookTmpDir.'/book/OEBPS/fonts');

        // ensure an empty fonts dir is not left begind (epubcheck error)
        $fontFiles = Finder::create()->files()->in($bookTmpDir.'/book/OEBPS/fonts');
        if ($fontFiles->count() === 0) {
            $this->app['filesystem']->remove($bookTmpDir.'/book/OEBPS/fonts');
        }

        // generate the book cover page
        $this->app->render(
            'cover.twig',
            ['customCoverImage' => $bookCover],
            $bookTmpDir.'/book/OEBPS/titlepage.html');

        // generate the OPF file (the ebook manifest)
        $this->app->render(
            'content.opf.twig',
            [
                'cover'          => $bookCover,
                'has_custom_css' => $hasCustomCss,
                'fonts'          => $bookFonts,
                'images'         => $bookImages,
                'items'          => $bookItems,
            ],
            $bookTmpDir.'/book/OEBPS/content.opf');

        // generate the NCX file (the table of contents)
        $this->app->render(
            'toc.ncx.twig',
            ['items' => $bookItems],
            $bookTmpDir.'/book/OEBPS/toc.ncx');

        // generate container.xml and mimetype files
        $this->app->render(
            'container.xml.twig',
            [],
            $bookTmpDir.'/book/META-INF/container.xml');
        $this->app->render(
            'mimetype.twig',
            [],
            $bookTmpDir.'/book/mimetype');

        $this->fixInternalLinks($bookTmpDir.'/book/OEBPS');

        // compress book contents as ZIP file and rename to .epub
        $this->zipBookContents($bookTmpDir.'/book', $bookTmpDir.'/book.zip');
        $this->app['filesystem']->copy(
            $bookTmpDir.'/book.zip',
            $this->app['publishing.dir.output'].'/book.epub',
            true);

        // remove temp directory used to build the book
        $this->app['filesystem']->remove($bookTmpDir);
    }

    /**
     * Prepares the temporary directory where the book contents are generated
     * before packing them into the resulting EPUB file. It also creates the
     * full directory structure required for EPUB books.
     *
     * @return string The absolute path of the directory created.
     */
    private function prepareBookTemporaryDirectory(): string
    {
        $bookDir = $this->app['app.dir.cache'].'/'.uniqid($this->app['publishing.book.slug'], true);

        $this->app['filesystem']->mkdir(
            [
                $bookDir,
                $bookDir.'/book',
                $bookDir.'/book/META-INF',
                $bookDir.'/book/OEBPS',
                $bookDir.'/book/OEBPS/css',
                $bookDir.'/book/OEBPS/images',
                $bookDir.'/book/OEBPS/fonts',
            ]);

        return $bookDir;
    }

    /**
     * It prepares the book cover image (if the book defines one).
     *
     * @param string $targetDir The directory where the cover image is copied.
     * @return array|null Book cover image data or null if the book doesn't
     *                          include a cover image.
     */
    private function prepareBookCoverImage($targetDir): ?array
    {
        $cover = null;

        if (null !== $image = $this->app->getCustomCoverImage()) {
            [$width, $height, $type] = getimagesize($image);

            $cover = [
                'height'    => $height,
                'width'     => $width,
                'filePath'  => 'images/'.basename($image),
                'mediaType' => image_type_to_mime_type($type),
            ];

            $this->app['filesystem']->copy($image, $targetDir.'/'.basename($image));
        }

        return $cover;
    }

    /**
     * It prepares the book fonts by copying them into the appropriate
     * temporary directory. It also prepares an array with all the font
     * data needed later to generate the full ebook contents manifest.
     * For now, epub books only include the Inconsolata font to display
     * their code listings.
     *
     * @param string $targetDir The directory where the fonts are copied.
     * @throws \RuntimeException
     * @return array             Font data needed to create the book manifest.
     */
    private function prepareBookFonts($targetDir): array
    {
        // new in trefoil
        if (!$this->app->edition('include_fonts')) {
            return [];
        }

        if (!file_exists($targetDir)) {
            throw new \RuntimeException(
                sprintf(
                    " ERROR: Books fonts couldn't be copied because \n"." the given '%s' \n"." directory doesn't exist.",
                    $targetDir));
        }

        $sourceDirs = [];
        // the standard easybook fonts dir
        //     <easybook>/app/Resources/Fonts/
        $sourceDirs[] = $this->app['app.dir.resources'].'/Fonts';

        // new in trefoil
        // fonts inside the Resources directory of the <format> directory of the current theme
        // (which can be set via command line argument):
        //     <current-theme-dir>/<current-theme>/<format>/Resources/images/
        // where <current_theme_dir> can be either
        //        <trefoil-dir>/app/Resources/Themes/
        //         or
        //        <the path set with the "--dir" publish command line argument>
        // 'Common' format takes precedence
        $sourceDirs[] = Toolkit::getCurrentResourcesDir($this->app, 'Common').'/Fonts';
        $sourceDirs[] = Toolkit::getCurrentResourcesDir($this->app).'/Fonts';

        // the fonts inside the book
        //     <book-dir>/Fonts/
        $sourceDirs[] = $this->app['publishing.dir.resources'].'/Fonts';

        // new in trefoil
        $allowedFonts = $this->app->edition('fonts');

        $fontsData = [];
        $i = 1;
        foreach ($sourceDirs as $fontDir) {
            if (file_exists($fontDir)) {

                $fonts = Finder::create()->files()->name('*.ttf')->name('*.otf')->sortByName()->in($fontDir);

                /** @var SplFileInfo $font */
                foreach ($fonts as $font) {
                    /*@var $font SplFileInfo */

                    $fontName = $font->getBasename('.'.$font->getExtension());

                    if (is_array($allowedFonts) && !in_array($fontName, $allowedFonts, true)) {
                        continue;
                    }

                    $this->app['filesystem']->copy(
                        $font->getPathname(),
                        $targetDir.'/'.$font->getFilename());

                    $fontsData[] = [
                        'id'        => 'font-'.$i++,
                        'filePath'  => 'fonts/'.$font->getFilename(),
                        'mediaType' => finfo_file(finfo_open(FILEINFO_MIME_TYPE), $font->getPathname()),
                    ];
                }
            }
        }

        return $fontsData;
    }

    /*
     * It creates the ZIP file of the .epub book contents.
     *
     * The PHP ZIP extension is not suitable for generating EPUB files.
     *
     * This method will generate he ZIP file using the OS 'zip' command.
     *
     * @param string $directory Book contents directory
     * @param string $zip_file  The path of the generated ZIP file
     */

    /**
     * If fixes the internal links of the book (the links that point to chapters
     * and sections of the book).
     * The author of the book always uses relative links, such as:
     *   see <a href="#new-content-types">this section</a> for more information
     * In order to work, the relative URIs must be replaced by absolute URIs:
     *   see <a href="chapter3/page-slug.html#new-content-types">this section</a>
     * Unlike books published as websites, the absolute URIs of the ePub books
     * cannot start with './' or '../' In other words, ./chapter.html and
     * ./chapter.html#section-slug are wrong and chapter.html or
     * chapter.html#section-slug are right.
     *
     * @param string $chunksDir The directory where the book's HTML page/chunks
     *                          are stored
     */
    private function fixInternalLinks($chunksDir): void
    {
        $generatedChunks = Finder::create()->files()->name('*.html')->in($chunksDir);

        // maps the original internal links (e.g. #new-content-types)
        // with the correct absolute URL needed for a website
        // (e.g. chapter-3/advanced-features.html#new-content-types
        $internalLinkMapper = [];

        //look for all the IDs of html tags in the rendered book
        foreach ($generatedChunks as $chunk) {
            /** @var SplFileInfo $chunk */
            $htmlContent = file_get_contents($chunk->getPathname());

            $matches = [];

            $numAnchors = preg_match_all(
                '/<.*id="(?<id>.*)"/U',
                $htmlContent,
                $matches,
                PREG_SET_ORDER);

            if ($numAnchors > 0) {
                foreach ($matches as $match) {
                    $relativeUri = '#'.$match['id'];
                    $absoluteUri = $chunk->getRelativePathname().$relativeUri;

                    $internalLinkMapper[$relativeUri] = $absoluteUri;
                }
            }
        }

        // replace the internal relative URIs for the mapped absolute URIs
        foreach ($generatedChunks as $chunk) {
            /** @var SplFileInfo $chunk */
            $htmlContent = file_get_contents($chunk->getPathname());

            $htmlContent = preg_replace_callback(
                '/<a href="(?<uri>#.*)"(?<attr>.*)>(?<content>.*)<\/a>/Us',
                function ($matches) use
                (
                    $internalLinkMapper
                ) {
                    if (array_key_exists($matches['uri'], $internalLinkMapper)) {
                        $newUri = $internalLinkMapper[$matches['uri']];
                    } else {
                        $newUri = $matches['uri'];
                    }

                    // add "internal" to link class
                    $attributes = Toolkit::parseHTMLAttributes($matches['attr']);
                    $attributes['class'] = isset($attributes['class']) ? $attributes['class'].' ' : '';
                    $attributes['class'] .= 'internal';

                    // render the new link tag
                    $attributes['href'] = $newUri;

                    return Toolkit::renderHTMLTag('a', $matches['content'], $attributes);

                },
                $htmlContent);

            file_put_contents($chunk->getPathname(), $htmlContent);
        }
    }

    /**
     * @param $directory
     * @param $zip_file
     */
    private function zipBookContents($directory,
                                     $zip_file): void
    {
        // After several hours trying to create ZIP files with lots of PHP
        // tools and libraries (Archive_Zip, Pclzip, zetacomponents/archive, ...)
        // I can't produce a proper ZIP file for ebook readers.
        // Therefore, if ZIP extension isn't enabled, the ePub ZIP file is
        // generated by executing 'zip' command

        // check if 'zip' command exists
        $process = new Process('zip');
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(
                "[ERROR] You must enable the ZIP extension in PHP \n"." or your system should be able to execute 'zip' console command.");
        }

        // To generate the ePub file, you must execute the following commands:
        //   $ cd /path/to/ebook/contents
        //   $ zip -X0 book.zip mimetype
        //   $ zip -rX9 book.zip * -x mimetype
        $command = sprintf(
            'cd %s && zip -X0 %s mimetype && zip -rX9 %s * -x mimetype',
            $directory,
            $zip_file,
            $zip_file);

        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(
                "[ERROR] 'zip' command execution wasn't successful.\n\n"."Executed command:\n"." $command\n\n"."Result:\n".$process->getErrorOutput());
        }
    }
}
