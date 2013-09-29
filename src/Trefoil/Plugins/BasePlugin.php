<?php
namespace Trefoil\Plugins;

use Trefoil\Util\Toolkit;
use Easybook\Events\BaseEvent;

abstract class BasePlugin
{
    protected $app;
    protected $output;
    protected $edition;
    protected $format;
    protected $theme;
    protected $item;

    public function init(BaseEvent $event)
    {
        $this->app = $event->app;
        $this->output = $this->app->get('console.output');
        $this->edition = $this->app['publishing.edition'];
        $this->format = Toolkit::getCurrentFormat($this->app);
        $this->theme = ucfirst($this->app->edition('theme'));
        $this->item = $event->getItem();
    }

    public function writeLn($message, $usePrefix = true)
    {
        $this->write($message, $usePrefix);
        $this->output->writeLn('');
    }

    public function write($message, $usePrefix = true)
    {
        $prefix = '';
        if ($usePrefix) {
            $class = join('',array_slice(explode('\\', get_called_class()), -1));
            $prefix = sprintf(' > %s: ', $class);
        }
        $this->output->write($prefix.$message);
    }
}
