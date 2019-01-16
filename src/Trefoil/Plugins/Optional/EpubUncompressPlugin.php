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

namespace Trefoil\Plugins\Optional;

use Easybook\Events\BaseEvent;
use Easybook\Events\EasybookEvents;
use Easybook\Util\Toolkit;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Trefoil\Plugins\BasePlugin;

/**
 * Plugin to uncompress the generated epub ebook
 * For formats: Epub
 */
class EpubUncompressPlugin extends BasePlugin implements EventSubscriberInterface
{

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            // runs later but before renaming
            EasybookEvents::POST_PUBLISH => ['onPostPublish', -900],
        ];
    }

    /**
     * @param BaseEvent $event
     */
    public function onPostPublish(BaseEvent $event)
    {
        $this->init($event);

        if ($this->format !== 'Epub') {
            // not for this format
            return;
        }

        $this->bookUncompress();
    }

    protected function bookUncompress()
    {
        $outputDir = $this->app['publishing.dir.output'];
        $epubFile = $outputDir.'/book.epub';
        $epubFolder = $epubFile.'.uncompressed';

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
