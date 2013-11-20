<?php
namespace Trefoil\Bridge\Easybook;

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

        $files = $this->app->get('finder')->files()->name('*Plugin.php')
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
        $loader = $this->app['twig.loader'];

        $theme = ucfirst($this->app->edition('theme'));
        $edition = $this->app['publishing.edition'];
        $format = Toolkit::getCurrentFormat($this->app);

        $themesDir = toolkit::getCurrentThemeDir($this->app);
        if (!file_exists($themesDir)) {

            $this->output->writeLn(
                    sprintf(" > <error>Theme %s not found in themes directory, using default easybook theme</error>", $theme));

            return;
        }

        $this->output->writeLn(sprintf(" > Using theme  %s from %s", $theme, $themesDir));

        // Theme common (common styles per edition theme)
        // <themes-dir>/Common/Templates/<template-name>.twig
        $baseThemeDir = sprintf('%s/Common/Templates', $themesDir);
        $loader->addPath($baseThemeDir);
        $loader->addPath($baseThemeDir, 'theme');
        $loader->addPath($baseThemeDir, 'theme_common');

        // Register template paths
        $ownTemplatePaths = array(
                // <themes-dir>/<template-name>.twig
                $themesDir,
                // <themes-dir>/<edition-type>/Templates/<template-name>.twig
                sprintf('%s/%s/Templates', $themesDir, $format));

        foreach ($ownTemplatePaths as $path) {
            if (file_exists($path)) {
                $loader->prependPath($path);
                $loader->prependPath($path, 'theme');
            }
        }

        /* need to register again the user template paths to ensure they
         * are still picked with greater precedence than our own templates,
         * so the user can still override them.
         */
        $userTemplatePaths = array(
                // <book-dir>/Resources/Templates/<template-name>.twig
                $this->app['publishing.dir.templates'],
                // <book-dir>/Resources/Templates/<edition-type>/<template-name>.twig
                sprintf('%s/%s', $this->app['publishing.dir.templates'], strtolower($format)),
                // <book-dir>/Resources/Templates/<edition-name>/<template-name>.twig
                sprintf('%s/%s', $this->app['publishing.dir.templates'], $this->app['publishing.edition']),
        );

        foreach ($userTemplatePaths as $path) {
            if (file_exists($path)) {
                $loader->prependPath($path);
            }
        }

        // Register content paths
        $ownContentPaths = array(
        // <themes-dir>/<edition-type>/Contents/<template-name>.twig
        sprintf('%s/%s/Contents', $themesDir, $format));

        foreach ($ownContentPaths as $path) {
            if (file_exists($path)) {
                $loader->prependPath($path, 'content');
            }
        }
    }
}
