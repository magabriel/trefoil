<?php
namespace Trefoil\Plugins;
use Symfony\Component\Finder\Finder;

use Easybook\Publishers\Epub2Publisher;

use Easybook\Events\EasybookEvents;

use Easybook\Util\Toolkit;

use Easybook\Events\BaseEvent;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Easybook\Events\EasybookEvents as Events;
use Easybook\Events\ParseEvent;

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
                EasybookEvents::POST_PUBLISH => array('onPostPublish',-900)
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
            $this->app->get('filesystem')->remove($epubFolder);
        }

        $this->app->get('filesystem')->mkdir($epubFolder);

        Toolkit::unzip($epubFile, $epubFolder);
    }
}
