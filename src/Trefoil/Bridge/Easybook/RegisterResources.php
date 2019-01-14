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

namespace Trefoil\Bridge\Easybook;

use Easybook\Events\BaseEvent;
use Easybook\Events\EasybookEvents;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Finder\Finder;
use Trefoil\DependencyInjection\Application;
use Trefoil\Events\TrefoilEvents;
use Trefoil\Util\Toolkit;

/**
 * Register our own resources into easybook
 */
class RegisterResources implements EventSubscriberInterface
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * @var ConsoleOutput
     */
    protected $output;

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [EasybookEvents::PRE_PUBLISH => 'onPrePublish'];
    }

    /**
     * @param BaseEvent $event
     * @throws \Exception
     */
    public function onPrePublish(BaseEvent $event): void
    {
        $this->app = $event->app;
        $this->output = $event->app['console.output'];

        $this->registerOwnPlugins();

        $this->registerOwnThemes();

        $app = $this->app;
        $app->dispatch(
            TrefoilEvents::PRE_PUBLISH_AND_READY,
            new BaseEvent($app)
        );
    }

    /**
     * Register our own plugins.
     *
     * @throws \Exception
     */
    public function registerOwnPlugins(): void
    {
        // register mandatory plugins
        $this->registerEventSubscribers(__DIR__.'/../../Plugins', 'Trefoil\\Plugins\\');

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

        /** @var string[] $enabledPlugins */
        $enabledPlugins = $bookEditions[$edition]['plugins']['enabled'];

        $registered = $this->registerEventSubscribers(
            __DIR__.'/../../Plugins/Optional',
            'Trefoil\\Plugins\\Optional\\',
            $enabledPlugins
        );

        // tell the user
        foreach ($enabledPlugins as $plugin) {
            if (!in_array($plugin, $registered, true)) {
                throw new \RuntimeException('Enabled plugin was not registered: '.$plugin);
            }
            $this->output->writeln(sprintf(' > Using plugin %s', $plugin));
        }
    }

    /**
     * @param       $dir
     * @param       $namespace
     * @param array $selectedPlugins List of plugins to register,
     *                               or null to register all
     * @return array
     * @throws \ReflectionException
     */
    private function registerEventSubscribers(
        string $dir,
        string $namespace,
        array $selectedPlugins = null
    ): array {

        if (!file_exists($dir)) {
            return [];
        }

        // find and register all plugins in dir
        $files = Finder::create()->files()->name('*Plugin.php')->depth(0)->in($dir);

        $registered = [];
        foreach ($files as $file) {
            $className = $file->getBasename('.php'); // strip .php extension

            $pluginName = $file->getBasename('Plugin.php');

            if ($selectedPlugins !== null && !in_array($pluginName, $selectedPlugins, true)) {
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
     * @throws \ReflectionException
     */
    protected function registerPlugin(string $namespace, string $className): void
    {
        $r = new \ReflectionClass($namespace.$className);

        if ($r->implementsInterface(EventSubscriberInterface::class)) {
            $this->app['dispatcher']->addSubscriber($r->newInstance());
        }
    }

    /**
     * Themes get actually registered in the TwigServiceProvider class.
     * Here we only tell the user what's being used.
     */
    protected function registerOwnThemes(): void
    {
        $theme = ucfirst($this->app->edition('theme'));

        $themesDir = Toolkit::getCurrentThemeDir($this->app);
        if ($themesDir !== null && !file_exists($themesDir)) {
            $this->output->writeln(
                sprintf(
                    ' > <bg=yellow;fg=black> WARNING </> '.
                    'Theme %s not found in themes directory, assuming default easybook theme',
                    $theme
                )
            );

            return;
        }

        $this->output->writeln(sprintf(' > Using theme  %s from %s', $theme, $themesDir));
    }
}
