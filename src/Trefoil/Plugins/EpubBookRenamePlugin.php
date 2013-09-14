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
class EpubBookRenamePlugin implements EventSubscriberInterface
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

    protected $app;
    protected $output;

    public static function getSubscribedEvents()
    {
        return array(
                // runs in the last place
                EasybookEvents::POST_PUBLISH => array('onPostPublish', -1000));
    }

    public function onPostPublish(BaseEvent $event)
    {
        $this->app = $event->app;
        $this->output = $this->app->get('console.output');

        $edition = $this->app->get('publishing.edition');

        // only for epub
        if ('epub' == $this->app->book('editions')[$edition]['format']) {
            $this->epubRename();
        }
    }

    protected function epubRename()
    {
        $this->app['book.logger']->debug('onPostPublish:begin', get_class());

        $outputDir = $this->app['publishing.dir.output'];

        // check output file generated
        $oldFile = $outputDir . '/book.epub';
        if (!file_exists($oldFile)) {
            return;
        }

        // get new name schema
        $newNameSchema = static::RENAME_SCHEMA_DEFAULT;
        $edition = $this->app['publishing.edition'];
        if (isset($this->app->book('editions')[$edition]['epub_rename'])) {
            $newNameSchema = $this
                    ->app->book('editions')[$edition]['epub_rename'];
        }

        // resolve it
        $newName = $this->resolveNamingSchema($newNameSchema);

        $newFile = $outputDir . '/' . $newName . '.epub';

        if (file_exists($newFile)) {
            $this->app->get('filesystem')->remove($newFile);
        }

        // rename it to an aux name
        $newFileAux = $newFile . '.aux';
        $this->app->get('filesystem')->rename($oldFile, $newFileAux);

        // delete other versions
        $files = $this->app->get('finder')->files()->name('*.epub')
                ->in($outputDir);
        $this->app->get('filesystem')->remove($files);

        // and let the new version with it real name
        $this->app->get('filesystem')->rename($newFileAux, $newFile);
        // and with the default name for testing purposes
        $this->app->get('filesystem')->copy($newFile, $oldFile);

        $this->app['book.logger']->debug('onPostPublish:end', get_class());
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
