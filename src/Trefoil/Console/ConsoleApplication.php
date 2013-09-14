<?php

namespace Trefoil\Console;

use Symfony\Component\Console\Application as SymfonyConsoleApplication;
use Trefoil\Console\Command\TestCommand;

use Easybook\DependencyInjection\Application;

class ConsoleApplication extends SymfonyConsoleApplication
{
    private $app;

    public function getApp()
    {
        return $this->app;
    }

    public function __construct(Application $app)
    {
        $this->app = $app;

        parent::__construct('trefoil', $this->app->getVersion());

        $this->add(new TestCommand());
    }

    public function getHelp()
    {
        $help = array(
            $this->app['app.signature'],
            '<info>trefoil</info> help text.'
        );

        return implode("\n", $help);
    }
}
