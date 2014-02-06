<?php
/*
 * This file is part of the trefoil application.
 *
 * (c) Miguel Angel Gabriel <magabriel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Trefoil\Plugins;

use Easybook\Events\BaseEvent;
use Symfony\Component\Console\Output\Output;
use Trefoil\DependencyInjection\Application;
use Trefoil\Util\Toolkit;

/**
 * Base class for all plugins
 *
 */
abstract class BasePlugin
{
    /**
     * @var array|Application
     */
    protected $app;

    /**
     * @var Output
     */
    protected $output;
    protected $edition;
    protected $format;
    protected $theme;
    protected $item;
    protected $event;

    /**
     * Do some initialization tasks.
     * Must be called explicitly for each plugin at the beginning
     * of each event handler method.
     *
     * @param BaseEvent $event
     */
    public function init(BaseEvent $event)
    {
        $this->event = $event;
        $this->app = $event->app;
        $this->output = $this->app['console.output'];
        $this->edition = $this->app['publishing.edition'];
        $this->format = Toolkit::getCurrentFormat($this->app);
        $this->theme = ucfirst($this->app->edition('theme'));
        $this->item = $event->getItem();
    }

    /**
     * Write an output message line
     *
     * @param string $message
     * @param string $type    of message ('error', 'warning', 'info')
     */
    public function writeLn($message, $type = 'info')
    {
        $this->write($message, $type);
        $this->output->writeLn('');
    }

    /**
     * Write an output message (w/o a line break)
     *
     * @param string $message
     * @param string $type    of message ('error', 'warning', 'info')
     */
    public function write($message, $type = 'info')
    {
        $class = join('', array_slice(explode('\\', get_called_class()), -1));
        $prefix = sprintf('%s: ', $class);

        $msgType = '';

        switch (strtolower($type)) {
            case 'warning':
                $msgType = '<bg=yellow;fg=black> WARNING </> ';
                break;

            case 'error':
                $msgType = '<bg=red;fg=white> ERROR </> ';
                break;
        }

        $this->output->write(' > ' . $prefix . $msgType . $message);
    }

    protected function progressStart($limit)
    {
        $this->app['console.progress']->start($this->output, $limit);
    }

    protected function progressAdvance()
    {
        $this->app['console.progress']->advance();
    }

    protected function progressFinish()
    {
        $this->app['console.progress']->finish();
    }

    /**
     * Retrieve the value of an edition option (from config.yml file)
     *
     * @param string $optionName (as in 'one.two.three')
     * @param mixed  $default
     *
     * @return mixed
     */
    protected function getEditionOption($optionName, $default = null)
    {
        $editions = $this->app->book('editions');
        $editionOptions = $editions[$this->edition];

        $keys = explode('.', $optionName);

        $option = $editionOptions;

        foreach ($keys as $key) {
            if (array_key_exists($key, $option)) {
                $option = $option[$key];
            } else {
                return $default;
            }
        }

        return $option;
    }

    /**
     * Retrieve the value of a book option (from config.yml file)
     *
     * @param string $optionName (as in 'one.two.three')
     * @param mixed  $default
     *
     * @return mixed
     */
    protected function getConfigOption($optionName, $default = null)
    {
        $configOptions = $this->app['publishing.book.config'];

        $keys = explode('.', $optionName);

        $option = $configOptions;

        foreach ($keys as $index => $key) {
            if (array_key_exists($key, $option)) {
                $option = $option[$key];
            } else {

                $joinKeys = implode('.', array_slice($keys, $index));

                if (array_key_exists($joinKeys, $option)) {
                    return $option[$joinKeys];
                } else {
                    return $default;
                }
            }
        }

        return $option;
    }
}
