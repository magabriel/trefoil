<?php

namespace Trefoil\Console;

use Easybook\Console\ConsoleApplication as EasybookConsoleApplication;
use Easybook\DependencyInjection\Application;
use Trefoil\Console\Command\TrefoilBookPublishCommand;
use Trefoil\Console\Command\TrefoilVersionCommand;

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
            . '<info>trefoil</info> extends <comment>easybook</comment> providing additional features.';

        return $help;
    }
}
