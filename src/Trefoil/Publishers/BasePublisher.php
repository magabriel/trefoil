<?php

namespace Trefoil\Publishers;

use Easybook\Publishers\BasePublisher as EasybookBasePublisher;
use Symfony\Component\Finder\Finder;
use Trefoil\Util\Toolkit;

class BasePublisher extends EasybookBasePublisher
{

    /**
     * It prepares the book images by copying them into the appropriate
     * temporary directory. It also prepares an array with all the images
     * data needed later to generate the full ebook contents manifest.
     *
     * @param  string $targetDir The directory where the images are copied.
     *
     * @throws \RuntimeException
     * @return array             Images data needed to create the book manifest.
     */
    protected function prepareBookImages($targetDir)
    {
        if (!file_exists($targetDir)) {
            throw new \RuntimeException(sprintf(
                                            " ERROR: Books images couldn't be copied because \n"
                                            . " the given '%s' \n"
                                            . " directory doesn't exist.",
                                            $targetDir
                                        ));
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
}
