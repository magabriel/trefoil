<?php
/*
 * This file is part of the trefoil application.
 *
 * (c) Miguel Angel Gabriel <magabriel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Trefoil\Bridge\Easybook;

use Easybook\Events\BaseEvent;
use Easybook\Events\EasybookEvents;
use Symfony\Component\Console\Application;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Finder\Finder;
use Trefoil\Events\TrefoilEvents;
use Trefoil\Util\Toolkit;

/**
 * Register our own resources into easybook
 *
 */
class RegisterResources implements EventSubscriberInterface
{
    protected $app;
    protected $output;

    public static function getSubscribedEvents()
    {
        return array(EasybookEvents::PRE_PUBLISH => 'onPrePublish');
    }

    public function onPrePublish(BaseEvent $event)
    {
        $this->app = $event->app;
        $this->output = $event->app['console.output'];

        $this->registerOwnPlugins();

        $this->registerOwnThemes();

        /** @var \Easybook\DependencyInjection\Application $app */
        $app = $this->app;
        $app->dispatch(
            TrefoilEvents::PRE_PUBLISH_AND_READY,
            new BaseEvent($app)
        );
    }

    /**
     * Register our own plugins
     */
    public function registerOwnPlugins()
    {
        // register mandatory plugins
        $this->registerEventSubscribers(__DIR__ . '/../../Plugins', 'Trefoil\\Plugins\\');

        // register optional plugins
        $edition = $this->app['publishing.edition'];
        $bookEditions = $this->app->book('editions');
        if (!isset($bookEditions[$edition]['plugins'])) {
            // no 'plugins' section
            return;
        }

        if (!isset($bookEditions[$edition]['plugins']['enabled'])) {
            // no plugins to register
            return;
        }

        $enabledPlugins = $bookEditions[$edition]['plugins']['enabled'];

        $registered = $this->registerEventSubscribers(
                           __DIR__ . '/../../Plugins/Optional',
                           'Trefoil\\Plugins\\Optional\\',
                           $enabledPlugins
        );

        // tell the user
        foreach ($enabledPlugins as $plugin) {
            if (!in_array($plugin, $registered)) {
                throw new \Exception(
                    'Enabled plugin was not registered: ' . $plugin);
            }
            $this->output->writeLn(sprintf(" > Using plugin %s", $plugin));
        }
    }

    /**
     * @param       $dir
     * @param       $namespace
     * @param array $selectedPlugins List of plugins to register,
     *                               or null to register all
     *
     * @return array
     */
    private function registerEventSubscribers($dir,
                                              $namespace,
                                              array $selectedPlugins = null)
    {
        if (!file_exists($dir)) {
            return array();
        }

        // find and register all plugins in dir
        $files = Finder::create()->files()->name('*Plugin.php')->depth(0)->in($dir);

        $registered = array();
        foreach ($files as $file) {
            $className = $file->getBasename('.php'); // strip .php extension

            $pluginName = $file->getBasename('Plugin.php');

            if ($selectedPlugins !== null && !in_array($pluginName, $selectedPlugins)) {
                continue;
            }

            $this->registerPlugin($namespace, $className);
            $registered[] = $pluginName;
        }

        return $registered;
    }

    /**
     * Register one plugin.
     *
     * @param $namespace
     * @param $className
     */
    protected function registerPlugin($namespace, $className)
    {
        $r = new \ReflectionClass($namespace . $className);

        if ($r->implementsInterface('Symfony\\Component\\EventDispatcher\\EventSubscriberInterface')) {
            $this->app->get('dispatcher')->addSubscriber($r->newInstance());
        }
    }

    protected function registerOwnThemes()
    {
        // themes get actually registered in the TwigServiceProvider class
        // here we only tell the user what's being used

        $theme = ucfirst($this->app->edition('theme'));

        $themesDir = toolkit::getCurrentThemeDir($this->app);
        if (!file_exists($themesDir)) {
            $this->output->writeLn(
                         sprintf(
                             " > <bg=yellow;fg=black> WARNING </> " .
                             "Theme %s not found in themes directory, assuming default easybook theme",
                             $theme
                         )
            );

            return;
        }

        $this->output->writeLn(sprintf(" > Using theme  %s from %s", $theme, $themesDir));
    }
}
