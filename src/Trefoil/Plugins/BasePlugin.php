<?php
namespace Trefoil\Plugins;

use Trefoil\Util\Toolkit;
use Easybook\Events\BaseEvent;

/**
 * Base class for all plugins
 *
 */
abstract class BasePlugin
{
    protected $app;
    protected $output;
    protected $edition;
    protected $format;
    protected $theme;
    protected $item;
    protected $internalLinksMapper = array();

    /**
     * Do some initialization tasks.
     * Must be called explicitly for each plugin at the begining
     * of each event handler method.
     *
     * @param BaseEvent $event
     */
    public function init(BaseEvent $event)
    {
        $this->event = $event;
        $this->app = $event->app;
        $this->output = $this->app->get('console.output');
        $this->edition = $this->app['publishing.edition'];
        $this->format = Toolkit::getCurrentFormat($this->app);
        $this->theme = ucfirst($this->app->edition('theme'));
        $this->item = $event->getItem();
    }

    /**
     * Write an output message line
     *
     * @param string $message
     * @param string $usePrefix
     */
    public function writeLn($message, $usePrefix = true)
    {
        $this->write($message, $usePrefix);
        $this->output->writeLn('');
    }

    /**
     * Write an output message (w/o a line break)
     *
     * @param string $message
     * @param string $usePrefix
     */
    public function write($message, $usePrefix = true)
    {
        $prefix = '';
        if ($usePrefix) {
            $class = join('',array_slice(explode('\\', get_called_class()), -1));
            $prefix = sprintf('%s: ', $class);
        }
        $this->output->write(' > '.$prefix.$message);
    }

    /**
     * Retrieve the value of an edition option (from config.yml file)
     *
     * @param string $optionNane (as in 'one.two.three')
     * @param string $default
     * @return mixed
     */
    protected function getEditionOption($optionNane, $default = null)
    {
        $editionOptions = $this->app->book('editions')[$this->edition];

        $keys = explode('.', $optionNane);

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
     * @param string $optionNane (as in 'one.two.three')
     * @param string $default
     * @return mixed
     */
    protected function getConfigOption($optionNane, $default = null)
    {
        $configOptions = $this->app['publishing.book.config'];

        $keys = explode('.', $optionNane);

        $option = $configOptions;

        foreach ($keys as $index=>$key) {
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
