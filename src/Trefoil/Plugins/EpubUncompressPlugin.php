<?php
namespace Trefoil\Plugins;

use Easybook\Events\BaseEvent;
use Easybook\Events\EasybookEvents;
use Easybook\Util\Toolkit;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Plugin to uncompress the generated epub ebook
 *
 * For formats: Epub
 *
 */
class EpubUncompressPlugin extends BasePlugin implements EventSubscriberInterface
{

    public static function getSubscribedEvents()
    {
        return array(
            // runs later but before renaming
            EasybookEvents::POST_PUBLISH => array('onPostPublish', -900)
        );
    }

    public function onPostPublish(BaseEvent $event)
    {
        $this->init($event);

        if ($this->format != 'Epub') {
            // not for this format
            return;
        }

        $this->bookUncompress();
    }

    protected function bookUncompress()
    {
        $outputDir = $this->app['publishing.dir.output'];
        $epubFile = $outputDir . '/book.epub';
        $epubFolder = $epubFile . '.uncompressed';

        if (!file_exists($epubFile)) {
            return;
        }

        // remove the uncompressed ebook directory
        if (file_exists($epubFolder)) {
            $this->app['filesystem']->remove($epubFolder);
        }

        $this->app['filesystem']->mkdir($epubFolder);

        Toolkit::unzip($epubFile, $epubFolder);
    }
}
