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

/**
 * It publishes the book as a single HTML page. All the internal links
 * are transformed into anchors. This means that the generated book can be
 * browsed offline or copied under any web server directory.
 */
class HtmlPublisher extends BasePublisher
{
    public function assembleBook(): void
    {
        // generate easybook CSS file
        if ($this->app->edition('include_styles')) {
            $this->app->render(
                '@theme/style.css.twig',
                ['resources_dir' => $this->app['app.dir.resources'] . '/'],
                      $this->app['publishing.dir.output'] . '/css/easybook.css'
            );
        }

        // generate custom CSS file
        $customCss = $this->app->getCustomTemplate('style.css');
        $hasCustomCss = file_exists($customCss);
        if ($hasCustomCss) {
            $this->app['filesystem']->copy(
                                    $customCss,
                                    $this->app['publishing.dir.output'] . '/css/styles.css',
                                    true
            );
        }

        // implode all the contents to create the whole book
        $this->app->render(
            'book.twig',
            [
                      'items'          => $this->app['publishing.items'],
                      'has_custom_css' => $hasCustomCss
            ],
                  $this->app['publishing.dir.output'] . '/book.html'
        );

        // copy book images
        $targetImagesDir = $this->app['publishing.dir.output'] . '/images';
        $this->app['filesystem']->mkDir($targetImagesDir);
        $this->prepareBookImages($targetImagesDir);
    }
}
