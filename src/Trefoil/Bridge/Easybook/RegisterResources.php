<?php
namespace Trefoil\Bridge\Easybook;

use Symfony\Component\Finder\Finder;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Easybook\Publishers\BasePublisher;
use Easybook\Events\BaseEvent;
use Easybook\Events\EasybookEvents as Events;
use Easybook\Events\ParseEvent;
use Trefoil\Events\TrefoilEvents;
use Trefoil\Util\Toolkit;
use Trefoil\Util\Logger;

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
        return array(Events::PRE_PUBLISH => 'onPrePublish');
    }

    public function onPrePublish(BaseEvent $event)
    {
        $this->app = $event->app;
        $this->output = $event->app->get('console.output');

        $this->registerOwnServices();

        $this->registerOwnPlugins();

        $this->registerOwnThemes();

        $this->app
                ->dispatch(TrefoilEvents::PRE_PUBLISH_AND_READY,
                        new BaseEvent($this->app));
    }

    /**
     * Register services that need a dynamic configuration
     *
     * @return \Trefoil\Util\Logger
     */
    public function registerOwnServices()
    {
        // nothing here
    }

    /**
     * Register our own plugins
     */
    public function registerOwnPlugins()
    {
        $edition = $this->app['publishing.edition'];

        if (!isset($this->app->book('editions')[$edition]['plugins'])) {
            // no plugins to register
            return;
        }

        $enabledPlugins = $this->app->book('editions')[$edition]['plugins'];

        $this->registerEventSubscribers(__DIR__.'/../../Plugins', 'Trefoil\Plugins', $enabledPlugins);
    }

    private function registerEventSubscribers($dir, $namespace = '',
            $enabledPlugins = array())
    {
        if (!file_exists($dir)) {
            return;
        }

        $files = Finder::create()->files()->name('*Plugin.php')
                ->in($dir);

        $registered = array();
        foreach ($files as $file) {
            $className = $file->getBasename('.php'); // strip .php extension

            $pluginName = $file->getBasename('Plugin.php');
            if (!in_array($pluginName, $enabledPlugins)) {
                continue;
            }

            $registered[] = $pluginName;
            $this->output->writeLn(sprintf(" > Using plugin %s", $pluginName));

            // if book plugins aren't namespaced, we must include the classes.
            if ('' == $namespace) {
                include_once $file->getPathName();
            }

            $r = new \ReflectionClass($namespace . '\\' . $className);
            if ($r
                    ->implementsInterface(
                            'Symfony\\Component\\EventDispatcher\\EventSubscriberInterface')) {
                $this->app->get('dispatcher')->addSubscriber($r->newInstance());
            }
        }

        foreach ($enabledPlugins as $plugin) {
            if (!in_array($plugin, $registered)) {
                throw new \Exception(
                        'Enabled plugin was not registered: ' . $plugin);
            }
        }
    }

     protected function registerOwnThemes()
     {
         // themes get actually registered in the TwigServiceProvider class
         // here we only tell the user what's being used

         $theme = ucfirst($this->app->edition('theme'));
         $edition = $this->app['publishing.edition'];
         $format = Toolkit::getCurrentFormat($this->app);

         $themesDir = toolkit::getCurrentThemeDir($this->app);
         if (!file_exists($themesDir)) {
             $this->output->writeLn(
                     sprintf(" > <bg=yellow;fg=black> WARNING </> ".
                             "Theme %s not found in themes directory, assuming default easybook theme", $theme));

             return;
         }

         $this->output->writeLn(sprintf(" > Using theme  %s from %s", $theme, $themesDir));
     }
}
