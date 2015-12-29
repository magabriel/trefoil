<?php
/*
 * This file is part of the trefoil application.
 *
 * (c) Miguel Angel Gabriel <magabriel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Trefoil\Publishers;

use Easybook\Publishers\BasePublisher as EasybookBasePublisher;
use Symfony\Component\Finder\Finder;
use Trefoil\Util\Toolkit;

abstract class BasePublisher extends EasybookBasePublisher
{

    /**
     * It controls the book publishing workflow for this particular publisher.
     */
    public function publishBook()
    {
        $this->filterContents();

        parent::publishBook();
    }

    public function parseContents()
    {
        parent::parseContents();

        // Plugins may want to remove some content item.
        // Remove items with 'remove' property set to true.
        $items = array();
        foreach ($this->app['publishing.items'] as $item) {
            $include = !isset($item['remove']) || (isset($item['remove']) && !$item['remove']);
            if ($include) {
                $items[] = $item;
            }
        }

        $this->app['publishing.items'] = $items;
    }

    /**
     * It prepares the book images by copying them into the appropriate
     * temporary directory. It also prepares an array with all the images
     * data needed later to generate the full ebook contents manifest.
     *
     * @param string $targetDir The directory where the images are copied.
     *
     * @throws \RuntimeException
     * @return array             Images data needed to create the book manifest.
     */
    protected function prepareBookImages($targetDir)
    {
        if (!file_exists($targetDir)) {
            throw new \RuntimeException(
                sprintf(
                    " ERROR: Books images couldn't be copied because \n"
                    . " the given '%s' \n"
                    . " directory doesn't exist.",
                    $targetDir
                )
            );
        }

        $edition = $this->app['publishing.edition'];
        $format = Toolkit::getCurrentFormat($this->app);

        // construct the list of source directories for images.
        // they will be used sequentially, so images inside each one will override previous images.
        $sourceDirs = array();

        // images into the Resources directory of the <format> directory of the current theme
        // (which can be set via command line argument):
        //     <current-theme-dir>/<current-theme>/<format>/Resources/images/
        // where <current_theme_dir> can be either
        //        <trefoil-dir>/app/Resources/Themes/
        //         or
        //        <the path set with the "--dir" publish command line argument>
        // 'Common' format takes precedence
        $sourceDirs[] = Toolkit::getCurrentResourcesDir($this->app, 'Common') . '/images';
        $sourceDirs[] = Toolkit::getCurrentResourcesDir($this->app) . '/images';

        // theme images can be overriden by the book:
        //     <book-dir>/Resources/images/
        $sourceDirs[] = sprintf('%s/images', $this->app['publishing.dir.resources']);
        //     <book-dir>/Resources/images/<edition-format>/
        $sourceDirs[] = sprintf('%s/images/%s', $this->app['publishing.dir.resources'], $format);
        //     <book-dir>/Resources/images/<edition-name>/
        $sourceDirs[] = sprintf('%s/images/%s', $this->app['publishing.dir.resources'], $edition);

        // the normal book images:
        //     <book-dir>/images/
        $sourceDirs[] = $this->app['publishing.dir.contents'] . '/images';

        // process each directory in sequence, so each one will override the previously copied images
        $imagesData = array();
        $i = 1;
        foreach ($sourceDirs as $imagesDir) {

            if (file_exists($imagesDir)) {

                $images = Finder::create()
                                ->files()
                                ->sortByName()
                                ->in($imagesDir);

                foreach ($images as $image) {

                    $this->app['filesystem']->copy(
                        $image->getPathName(),
                        $targetDir . '/' . $image->getFileName(),
                        true // overwrite
                    );

                    // The right mediatype for jpeg images is jpeg, not jpg
                    $mediaType = pathinfo($image->getFilename(), PATHINFO_EXTENSION);
                    $mediaType = str_replace('jpg', 'jpeg', $mediaType);

                    $imagesData[$image->getFileName()] = array(
                        'id'        => 'image-' . $i++,
                        'filePath'  => 'images/' . $image->getFileName(),
                        'mediaType' => 'image/' . $mediaType
                    );
                }
            }
        }

        return $imagesData;
    }

    /**
     * Retrieve the custom css file to be used with this book
     *
     * @return null|string
     */
    protected function getCustomCssFile()
    {
        // try the text file "style.css"
        $customCss = $this->app->getCustomTemplate('style.css');
        if ($customCss) {
            return $customCss;
        }

        // try the Twig template "style.css.twig"
        $customCss = $this->app->getCustomTemplate('style.css.twig');
        if ($customCss) {
            return $customCss;
        }

        return null;
    }

    /**
     * Filters out the content items based on certain conditions.
     *
     * - publising edition: if the item has "editions" array, it will only be included
     *   if edition is in "editions" array. Prefixing it with "!" means "not for that
     *   edition".
     *
     * - publising edition format: if the item has "formats" array, it will only be included
     *   if the edition format is in "formats" array. Prefixing it with "!" means "not for that
     *   format".
     */
    protected function filterContents()
    {
        $newContents = [];

        $edition = strtolower($this->app['publishing.edition']);
        $format = strtolower(Toolkit::getCurrentFormat($this->app));
        
        // by default, all content items are included
        foreach ($this->app->book('contents') as $itemConfig) {

            $contentFilters = $this->extractContentFilters($itemConfig);

            // omit editions not in "editions" array
            if (count($contentFilters['editions']) && !in_array($edition, $contentFilters['editions'])) {
                continue;
            }

            // omit editions in "not-editions" array
            if (count($contentFilters['not-editions']) && in_array($edition, $contentFilters['not-editions'])) {
                continue;
            }

            // omit editions which format is not in "formats" array
            if (count($contentFilters['formats']) && !in_array($format, $contentFilters['formats'])) {
                continue;
            }

            // omit editions which format is in "not-formats" array
            if (count($contentFilters['not-formats']) && in_array($format, $contentFilters['not-formats'])) {
                continue;
            }
            
            $newContents[] = $itemConfig;
        }

        $this->app->book('contents', $newContents);
    }

    /**
     * Returns an array of content filters.
     *
     * @param $itemConfig
     *
     * @return array filters
     */
    protected function extractContentFilters($itemConfig)
    {
        $contentFilters = array(
            'editions'     => array(),
            'not-editions' => array(),
            'formats'      => array(),
            'not-formats'  => array()
        );

        if (isset($itemConfig['editions'])) {
            
            foreach ($itemConfig['editions'] as $ed) {
                if (substr($ed, 0, 1) === '!') {
                    $contentFilters['not-editions'][] = substr($ed, 1);
                } else {
                    $contentFilters['editions'][] = $ed;
                }
            }
        }

        if (isset($itemConfig['formats'])) {

            foreach ($itemConfig['formats'] as $fm) {
                if (substr($fm, 0, 1) === '!') {
                    $contentFilters['not-formats'][] = substr($fm, 1);
                } else {
                    $contentFilters['formats'][] = $fm;
                }
            }
        }
        
        return $contentFilters;
    }

}
