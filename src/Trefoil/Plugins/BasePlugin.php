<?php
namespace Trefoil\Plugins;

use Easybook\Events\BaseEvent;

abstract class BasePlugin
{
    protected $app;
    protected $output;
    protected $edition;

    public function init(BaseEvent $event)
    {
        $this->app = $event->app;
        $this->output = $this->app->get('console.output');
        $this->edition = $this->app['publishing.edition'];
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
