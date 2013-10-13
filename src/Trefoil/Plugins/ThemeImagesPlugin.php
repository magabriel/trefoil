<?php
namespace Trefoil\Plugins;

use Easybook\Events\EasybookEvents;
use Easybook\Events\BaseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Easybook\Events\EasybookEvents as Events;
use Easybook\Events\ParseEvent;

use Trefoil\Events\TrefoilEvents;
use Trefoil\Util\Toolkit;
/**
 * Plugin to use images included into a theme.
 *
 * Images can be in two places:
 *
 * 1.- The theme directory set as input argument when publishing the book (if set):
 *
 * <theme_dir>/
 *     my_theme/
 *         <format>/
 *             Resources/
 *                 images/
 *                     ...all image files
 *
 * 2.- The theme directory inside trefoil:
 *
 * <trefoil_dir>/
 *     app/
 *         Resources/
 *             Themes/
 *                 my_theme/
 *                     <format>/
 *                         Resources/
 *                             images/
 *                                 ...all image files
 *
 * The plugin works by copying the theme images into a temporary directory
 * inside the book <i>Contents/images</i> directory called <i>theme_tmp</i>
 * just before the book is published by easybook, and then removing the temp
 * directory after the book has been published. I know it is a dirty hack
 * but is the best I could come up without hacking easybook.
 */
class ThemeImagesPlugin extends BasePlugin implements EventSubscriberInterface
{
    protected $app;
    protected $output;

    public static function getSubscribedEvents()
    {
        return array(
                TrefoilEvents::PRE_PUBLISH_AND_READY => 'onPrePublishAndReady',
                EasybookEvents::POST_PARSE => array('onItemPostParse', -600),
                EasybookEvents::POST_PUBLISH => 'onPostPublish');
    }

    public function onPrePublishAndReady(BaseEvent $event)
    {
        $this->init($event);

        $this->copyThemeImages();
        $this->copyCoverImage();
    }

    public function copyThemeImages()
    {
        // images into the "Common" format of theme into the default trefoil themes
        $defaultCommonImagesDir = $this->app['trefoil.app.dir.resources'].'/Themes'.'/'.$this->theme.'/Common/Resources/images';

        // images into the format of the theme
        $localImagesDir = Toolkit::getCurrentResourcesDir($this->app, $this->format).'/images';

        if (!file_exists($defaultCommonImagesDir) && !file_exists($localImagesDir)) {
            return;
        }

        // get the destination dir
        $destDir = $this->app['publishing.dir.contents'] . '/images/theme_tmp';
        if (!file_exists($destDir)) {
            $this->app->get('filesystem')->mkdir($destDir);
        }

        // first copy the default images, then the local images
        if (file_exists($defaultCommonImagesDir) ) {
            $this->app->get('filesystem')->mirror($defaultCommonImagesDir, $destDir, null, true);
        }
        if (file_exists($localImagesDir)) {
            $this->app->get('filesystem')->mirror($localImagesDir, $destDir, null, true);
        }
    }

    protected function copyCoverImage()
    {
        //$format = Toolkit::camelize($this->app->edition('format'), true);

        if ('Epub' == $this->format) {
            // the EPUB publisher will take care of it
            return;
        }

        if (null != $image = $this->app->getCustomCoverImage()) {
            $destDir = $this->app['publishing.dir.contents'] . '/images/theme_tmp';
            // copy the cover image
            $this->app->get('filesystem')->copy(
                    $image,
                    $destDir.'/'.basename($image)
            );
        }
    }

    public function onPostPublish(BaseEvent $event)
    {
        $this->app = $event->app;
        $this->output = $event->app->get('console.output');

        // remove the temp images directory
        $destDir = $this->app['publishing.dir.contents'] . '/images/theme_tmp';
        if (!file_exists($destDir)) {
            return;
        }
        $this->app->get('filesystem')->remove($destDir);
    }

    public function onItemPostParse(ParseEvent $event)
    {
        $this->app = $event->app;
        $this->item = $event->getItem();
        $format = Toolkit::camelize($this->app->edition('format'), true);

        if ('Epub' != $format) {
            // correct image paths for formats other than EPUB
            $this->item['content'] = $this
                    ->correctImagePaths($this->item['content']);
        }

        $event->setItem($this->item);
    }

    protected function correctImagePaths($content)
    {
        // get all the images
        $tmpDir = $this->app['publishing.dir.contents'] . '/images';

        $files = $this->app->get('finder')->files()->in($tmpDir);

        // fill in a lookup array for image name => image relative path
        $images = array();
        foreach ($files as $file) {

            $imagePath = $this->app->get('filesystem')
                    ->makePathRelative($file,
                            $this->app['publishing.dir.contents']);
            if (substr($imagePath, -1) == '/') {
                $imagePath = substr($imagePath, 0, -1);
            }

            $images[basename($file)] = $imagePath;
        }

        // find all the images in the item content and correct them
        $content = preg_replace_callback(
                '/<img(?<prev>.*) src="(?<src>.*)"(?<post>.*) \/>/U',
                function ($matches) use ($images) {
                    $src = $matches['src'];
                    $image = basename($src);
                    $newSrc = isset($images[$image]) ? $images[$image] : $src;

                    return sprintf('<img%s src="%s"%s />', $matches['prev'], $newSrc, $matches['post']);
                },
                $content
        );

        return $content;
    }

}
