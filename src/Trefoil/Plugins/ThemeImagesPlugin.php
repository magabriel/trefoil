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
 * For a theme called "the_theme", images will be copied from the following places:
 *
 * 1.- The "Common" directory of the trefoil-included theme (if it exists):
 *
 *     <trefoil_dir>/app/Resources/Themes/the_theme/Common/Resources/images/
 *
 * 2.- The <format> directory of the current theme (that can be set via command line argument).
 *
 *     <current_theme_dir>/the_theme/<format>/Resources/images/
 *
 *     Where <current_theme_dir> can be either
 *
 *         <trefoil_dir>/app/Resources/Themes/
 *         or
 *         <the path set with the "--dir" publish command line argument>
 *
 *
 * 3.- Any of the user-overridable directories inside the book folder:
 *
 *     <book_dir>/Resources/images/
 *     <book_dir>/Resources/images/<edition_type>/
 *     <book_dir>/Resources/images/<edition_name>/
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

        // images into the format of the current theme
        $localImagesDir = Toolkit::getCurrentResourcesDir($this->app, $this->format).'/images';

        // user-overridable directories
        $userImagesDirs = array(
                // <book-dir>/Resources/images/
                sprintf('%s/images', $this->app['publishing.dir.resources']),
                // <book-dir>/Resources/images/<edition-type>/
                sprintf('%s/images/%s', $this->app['publishing.dir.resources'], Toolkit::getCurrentFormat($this->app)),
                // <book-dir>/Resources/images/<edition-name>/
                sprintf('%s/images/%s', $this->app['publishing.dir.resources'], $this->app['publishing.edition']),
        );

        // get and create the destination dir
        $destDir = $this->app['publishing.dir.contents'] . '/images/theme_tmp';
        if (!file_exists($destDir)) {
            $this->app->get('filesystem')->mkdir($destDir);
        }

        // first, copy the default 'Common' theme images
        if (file_exists($defaultCommonImagesDir) ) {
            $this->app->get('filesystem')->mirror($defaultCommonImagesDir, $destDir, null, array('override' => true));
        }

        // second, copy the theme format images
        if (file_exists($localImagesDir)) {
            $this->app->get('filesystem')->mirror($localImagesDir, $destDir, null, array('override' => true));
        }

        // last, copy each one of the user directories that override all of the above
        foreach ($userImagesDirs as $dir) {
            $dir = realpath($dir);
            if (file_exists($dir)) {
                $this->app->get('filesystem')->mirror($dir, $destDir, null, array('override' => true));
            }
        }
    }

    protected function copyCoverImage()
    {
        if ('Epub2' == $this->format) {
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
        $this->init($event);

        // remove the temp images directory
        $destDir = $this->app['publishing.dir.contents'] . '/images/theme_tmp';
        if (!file_exists($destDir)) {
            return;
        }
        $this->app->get('filesystem')->remove($destDir);
    }

    public function onItemPostParse(ParseEvent $event)
    {
        $this->init($event);

        if ('Epub2' != $this->format) {
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
