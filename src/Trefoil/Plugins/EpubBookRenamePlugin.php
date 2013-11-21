<?php
namespace Trefoil\Plugins;

use Easybook\Events\EasybookEvents;
use Easybook\Util\Toolkit;
use Easybook\Events\BaseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Easybook\Events\EasybookEvents as Events;
use Easybook\Events\ParseEvent;

/**
 * plugin to rename the generated file book.epub to <book-slug>.epub
 *
 */
class EpubBookRenamePlugin extends BasePlugin implements EventSubscriberInterface
{
    /**
     * Default naming schema for epub book (Twig syntax BUT with single curly brackets).
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
        $oldFile = $outputDir . '/book.'.$extension;
        if (!file_exists($oldFile)) {
            return;
        }

        // get the parameters
        $newNameSchema = $this->getEditionOption('EpubBookRename.schema', static::RENAME_SCHEMA_DEFAULT);
        $keepOriginal = $this->getEditionOption('EpubBookRename.keep_original', true);

        // resolve it
        $newName = $this->resolveNamingSchema($newNameSchema);

        $newFile = $outputDir . '/' . $newName . '.'.$extension;

        if (file_exists($newFile)) {
            $this->app->get('filesystem')->remove($newFile);
        }

        // rename it to an aux name
        $newFileAux = $newFile . '.aux';
        $this->app->get('filesystem')->rename($oldFile, $newFileAux);

        // delete other versions
        $files = $this->app->get('finder')->files()->name('*.'.$extension)
                ->in($outputDir);
        $this->app->get('filesystem')->remove($files);

        // and let the new version with it real name
        $this->app->get('filesystem')->rename($newFileAux, $newFile);

        $this->writeLn(sprintf('<comment>Output file renamed to "%s"</comment>', $newName));

        // and with the default name for testing purposes
        if ($keepOriginal) {
            $this->app->get('filesystem')->copy($newFile, $oldFile);
            $this->writeLn('<comment>Original output file kept</comment>');
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
