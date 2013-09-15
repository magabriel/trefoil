<?php
namespace Trefoil\Plugins;

use Easybook\Events\EasybookEvents;
use Easybook\Util\Toolkit;
use Easybook\Events\BaseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Easybook\Events\EasybookEvents as Events;
use Easybook\Events\ParseEvent;

/**
 * plugin to update the version in the book.yml
 *
 */
class VersionUpdaterPlugin implements EventSubscriberInterface
{
    protected $app;
    protected $output;

    public static function getSubscribedEvents()
    {
        return array(
                // runs in the first place after publishing
                EasybookEvents::POST_PUBLISH => array(
                        'onPostPublish',
                        1000
                )
        );
    }

    public function onPostPublish(BaseEvent $event)
    {
        $this->app = $event->app;
        $this->output = $this->app->get('console.output');

        $this->updateVersion();
    }

    protected function updateVersion()
    {
        if (!$this->app->book('version')) {
            $this->output
                    ->writeLn(
                            ' <error>No "book.version" option found. Cannot update version.</error>' . "\n");
            return;
        }

        $newVersionString = $this->calculateNewVersion();
        $this->updateConfigFile($newVersionString);
    }

    protected function calculateNewVersion()
    {
        // read current version
        $versionString = $this->app->book('version');

        // read version options
        $versionIncrementVer = false;
        $versionIncrementRev = true;
        if ($this->app->book('version_options')) {
            $versionOptions = $this->app->book('version_options');
            $versionIncrementVer = isset($versionOptions['increment_ver']) ? $versionOptions['increment_ver'] : false;
            $versionIncrementRev = isset($versionOptions['increment_rev']) ? $versionOptions['increment_rev'] : true;
        }

        // analize parts
        $parts = explode('.', $versionString);

        // check for correctness
        if (count($parts) <> 2) {
            throw new \Exception(
                    sprintf('Malformed version string "%s". Expected "int.int"', $versionString));
        }
        if (!ctype_digit($parts[0]) || !ctype_digit($parts[1])) {
            throw new \Exception(
                    sprintf('Malformed version string "%s". Expected "int.int"', $versionString));

        }

        if ($versionIncrementRev) {
            // calculate new revision
            $revision = $parts[1];
            $parts[1] = $revision + 1;
        }

        if ($versionIncrementVer) {
            // calculate new version
            $version = $parts[0];
            $parts[0] = $version + 1;

            // reset revision
            $parts[1] = 0;
        }

        $newVersionString = implode('.', $parts);

        return $newVersionString;
    }

    protected function updateConfigFile($newVersionString)
    {
        $configFile = $this->app->get('publishing.dir.book') . '/config.yml';

        $config = file_get_contents($configFile);

        // capture the current version
        $regExp = '/';
        $regExp .= '(?<label>version:)';
        $regExp .= '(?<spaces>[^\w]*)';
        $regExp .= '"(?<version>.*)"';
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $config = preg_replace_callback(
                $regExp,
                function ($matches) use ($newVersionString)
                          {
                          $new = $matches['label'] . $matches['spaces'] . '"' . $newVersionString . '"';
                          return $new;
                          },
                          $config);

        file_put_contents($configFile, $config);
    }

}
