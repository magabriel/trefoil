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
 * plugin to use images embedded into a them
 *
 */
class ThemeImagesPlugin implements EventSubscriberInterface
{
    protected $app;
    protected $output;

    public static function getSubscribedEvents()
    {
        return array(TrefoilEvents::PRE_PUBLISH_AND_READY => 'onPrePublishAndReady',
                EasybookEvents::POST_PARSE => array('onItemPostParse', -600),
                EasybookEvents::POST_PUBLISH => 'onPostPublish');
    }

    public function onPrePublishAndReady(BaseEvent $event)
    {
        $this->app = $event->app;
        $this->output = $event->app->get('console.output');

        $this->copyThemeImages();

        $this->copyCoverImage();
    }

    public function copyThemeImages()
    {
        $edition = $this->app['publishing.edition'];
        $theme = ucfirst($this->app->edition('theme'));
        $format = Toolkit::camelize($this->app->edition('format'), true);

        // get the source dir (inside theme)
        $themeDir = Toolkit::getCurrentThemeDir($this->app);

        // TODO: fix the following hack
        if ('Epub' == $format) {
            $format = 'Epub2';
        }
        $sourceDir = sprintf('%s/%s/Resources/images', $themeDir, $format);

        if (!file_exists($sourceDir)) {
            return;
        }

        // get the destination dir
        $destDir = $this->app['publishing.dir.contents'] . '/images/theme_tmp';
        if (!file_exists($destDir)) {
            $this->app->get('filesystem')->mkdir($destDir);
        }

        // and copy contents
        $this->app->get('filesystem')->mirror($sourceDir, $destDir, null, true);

    }

    protected function copyCoverImage()
    {
        $edition = $this->app['publishing.edition'];
        $theme = ucfirst($this->app->edition('theme'));
        $format = Toolkit::camelize($this->app->edition('format'), true);

        if ('Epub' == $format) {
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
