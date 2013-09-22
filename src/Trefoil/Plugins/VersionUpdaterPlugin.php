<?php
namespace Trefoil\Plugins;

use Easybook\Events\EasybookEvents;
use Easybook\Util\Toolkit;
use Easybook\Events\BaseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Easybook\Events\EasybookEvents as Events;
use Easybook\Events\ParseEvent;

/**
 * Plugin to update the version in the book config.yml file.
 *
 * The following book configuration option must be defined:
 *
 *     book:
 *         ...
 *         version: "1.0" # current version string, of form "version.revision"
 *         verson_options:
 *             increment_ver: false # don't increment the version part
 *             increment_rev: true  # increment the revision part
 *
 * After execution the book config.yml file will be updated with the
 * new version string:
 *
 *     book:
 *         ...
 *         version: "1.1"
 *
 * The plugin runs just after the book publishing has finished, so the
 * new version will be used the <i>next</i> time it gets published.
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
                EasybookEvents::POST_PUBLISH => array('onPostPublish',1000)
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
            $this->output->writeLn(
                  ' <error>No "book.version" option found. Cannot update version.</error>' . "\n");
            return;
        }

        $newVersionString = $this->calculateNewVersion();
        $this->updateConfigFile($newVersionString);
    }

    /**
     * Calculate the new version string using the rules defined in the <b>book.version</b> configuration option
     *
     * @return string
     */
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

    /**
     * Update the book config file with the new version string.
     * Note that this is a naive implementation of a yaml file updater.
     *
     * @param string $newVersionString
     */
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
