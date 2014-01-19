<?php

namespace Trefoil\Publishers;

/**
 * It publishes the book as a single HTML page. All the internal links
 * are transformed into anchors. This means that the generated book can be
 * browsed offline or copied under any web server directory.
 */
class HtmlPublisher extends BasePublisher
{
    public function assembleBook()
    {
        // generate easybook CSS file
        if ($this->app->edition('include_styles')) {
            $this->app->render(
                      '@theme/style.css.twig',
                      array('resources_dir' => $this->app['app.dir.resources'] . '/'),
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
                  array(
                      'items'          => $this->app['publishing.items'],
                      'has_custom_css' => $hasCustomCss
                  ),
                  $this->app['publishing.dir.output'] . '/book.html'
        );

        // copy book images        
        $this->prepareBookImages($this->app['publishing.dir.output'] . '/images');
    }
}
