<?php

namespace Trefoil\Console;

use Trefoil\Console\Command\TrefoilVersionCommand;
use Trefoil\Console\Command\TrefoilBookPublishCommand;

use Easybook\DependencyInjection\Application;
use Easybook\Console\ConsoleApplication as EasybookConsoleApplication;

class ConsoleApplication extends EasybookConsoleApplication
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
        
        $this->setName('trefoil');
        $this->setVersion($app->getMyVersion());
        
        $this->add(new TrefoilVersionCommand());
        $this->add(new TrefoilBookPublishCommand());
    }

    public function getHelp()
    {
        $help = parent::getHelp();

        $help .= "\n"
            . '<info>trefoil</info> extends <comment>easybook</comment> providing additional features.'
        ;

        return $help;
    }
}
