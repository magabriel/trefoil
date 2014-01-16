<?php
namespace Trefoil\Console\Command;

use Easybook\Console\Command\EasybookVersionCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Trefoil\DependencyInjection\Application;

class TrefoilVersionCommand extends EasybookVersionCommand
{
    protected function configure()
    {
        $this->setDefinition(array())
             ->setName('version')
             ->setDescription('Shows installed easybook + trefoil version')
             ->setHelp('The <info>version</info> command shows you the installed version of <info>easybook</info> and <info>trefoil</info>');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /* @var Application $app (fake cast) */
        $app = $this->app;

        $output->writeln(array(
            '',
            $app['app.signature'],
            ' <info>easybook</info> installed version: '
            .'<comment>'.$app->getVersion().'</comment>',
            ' <info>trefoil</info>  installed version: '
            .'<comment>'.$app->getMyVersion().'</comment>',
            '',
        ));
    }
}
