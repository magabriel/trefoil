<?php
namespace Trefoil\Console\Command;

use Trefoil\Bridge\Easybook\RegisterResources;
use Easybook\Console\Command\BookPublishCommand as EasybookBookPublishCommand;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TrefoilBookPublishCommand extends EasybookBookPublishCommand
{
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->app['dispatcher']->addSubscriber(new RegisterResources());

        parent::execute($input, $output);
    }
}
