<?php
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
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Finder\Finder;
use Trefoil\Plugins\BasePlugin;

/**
 * plugin to rename the generated file book.<ext> to <something-else>.<ext>
 *
 */
class EbookRenamePlugin extends BasePlugin implements EventSubscriberInterface
{
    /**
     * Default naming schema for ebook file (Twig syntax BUT with single curly brackets).
     * Allowed variables are:
     * - 'publishing.book.slug'
     * - any 'book' or 'edition' config variable
     *
     * Example: "{publishing.book.slug}-{book.version}_{edition.isbn}"
     */
    const RENAME_SCHEMA_DEFAULT = "{publishing.book.slug}-{book.version}";

    public static function getSubscribedEvents()
    {
        return array(
            // runs in the last place
            EasybookEvents::POST_PUBLISH => array('onPostPublish', -1000));
    }

    public function onPostPublish(BaseEvent $event)
    {
        $this->init($event);

        // only for epub or mobi
        if (!in_array($this->format, array('Epub', 'Mobi'))) {
            return;
        }

        $this->epubRename();
    }

    protected function epubRename()
    {
        $outputDir = $this->app['publishing.dir.output'];

        $extension = strtolower($this->format);

        // check output file generated
        $oldFile = $outputDir . '/book.' . $extension;
        if (!file_exists($oldFile)) {
            return;
        }

        // get the parameters
        $newNameSchema = $this->getEditionOption('plugins.options.EbookRename.schema', static::RENAME_SCHEMA_DEFAULT);
        $keepOriginal = $this->getEditionOption('plugins.options.EbookRename.keep_original', true);

        // resolve it
        $newName = $this->resolveNamingSchema($newNameSchema);

        $newFile = $outputDir . '/' . $newName . '.' . $extension;

        if (file_exists($newFile)) {
            $this->app['filesystem']->remove($newFile);
        }

        // rename it to an aux name
        $newFileAux = $newFile . '.aux';
        $this->app['filesystem']->rename($oldFile, $newFileAux);

        // delete other versions
        $files = Finder::create()->files()->name('*.' . $extension)
                       ->in($outputDir);
        $this->app['filesystem']->remove($files);

        // and let the new version with its real name
        $this->app['filesystem']->rename($newFileAux, $newFile);

        $this->writeLn(sprintf('Output file renamed to "%s"', basename($newFile)));

        // and with the default name for testing purposes
        if ($keepOriginal) {
            $this->app['filesystem']->copy($newFile, $oldFile);
            $this->writeLn('Original output file kept.');
        }
    }

    protected function resolveNamingSchema($namingSchema)
    {
        // replace single by double curly brackets
        $namingSchema = str_replace('{', '{{', $namingSchema);
        $namingSchema = str_replace('}', '}}', $namingSchema);

        // add 'publishing' values to the twig variables
        $vars = array(
            'publishing' => array(
                'book' => array(
                    'slug' => $this->app['publishing.book.slug'])));

        $name = $this->app->renderString($namingSchema, $vars);

        return $name;
    }

}
