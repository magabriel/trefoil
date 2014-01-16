<?php
namespace Trefoil\Plugins;

use Easybook\Events\BaseEvent;
use Easybook\Events\EasybookEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Plugin to update the version in the book config.yml file.
 *
 * The following book configuration option must be defined:
 *
 *     book:
 *         ...
 *         version: "1.0" # current version string, of form "version.revision"
 *
 *         editions:
 *             <edition>:
 *                 plugins:
 *                     ...
 *                     options:
 *                         VersionUpdater:
 *                             increment_ver: false # don't increment the version part (default)
 *                             increment_rev: true  # increment the revision part (default)
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
class VersionUpdaterPlugin extends BasePlugin implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
                // runs in the first place after publishing
                EasybookEvents::POST_PUBLISH => array('onPostPublish',1000)
        );
    }

    public function onPostPublish(BaseEvent $event)
    {
        $this->init($event);

        $this->updateVersion();
    }

    protected function updateVersion()
    {
        if (!$this->app->book('version')) {
            $this->writeLn('No "book.version" option found. Cannot update version.', 'error');
            return;
        }

        $newVersionString = $this->calculateNewVersion();
        $this->updateConfigFile($newVersionString);

        $this->writeLn(sprintf('Book version updated to "%s"', $newVersionString));
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
        $versionIncrementVer = $this->getEditionOption('plugins.options.VersionUpdater.increment_ver', false);
        $versionIncrementRev = $this->getEditionOption('plugins.options.VersionUpdater.increment_rev', true);

        // analize parts
        $parts = explode('.', $versionString);

        // check for correctness
        if (count($parts) <> 2) {
            $this->writeLn(
                    sprintf('Malformed version string "%s". Expected "int.int"', $versionString), 'error');
            return $versionString;
        }
        if (!ctype_digit($parts[0]) || !ctype_digit($parts[1])) {
           $this->writeLn(
                    sprintf('Malformed version string "%s". Expected "int.int"', $versionString), 'error');
            return $versionString;
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
        $regExp .= '^(?<indent> +)';
        $regExp .= '(?<label>version:)';
        $regExp .= '(?<spaces>[^\w]*)';
        $regExp .= '(?<delim>["\'])(?<version>.*)["\']';
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $config = preg_replace_callback(
                $regExp,
                function ($matches) use ($newVersionString)
                          {
                          $new = $matches['indent'] .
                                 $matches['label'] .
                                 $matches['spaces'] .
                                 $matches['delim'] . $newVersionString . $matches['delim'];
                          return $new;
                          },
                          $config);

        file_put_contents($configFile, $config);
    }

}
