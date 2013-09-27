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
    protected function configure()
    {
        parent::configure();

        $this->addOption(
                'themes_dir', '', InputOption::VALUE_OPTIONAL, "<info>(*)</info> Path of the custom themes directory"
                );

        $oldHelp = $this->getHelp();

        $newHelp = "NOTE: Items marked with <info>(*)</info> are <comment>trefoil</comment> extensions."."\n\n" .
                   $oldHelp . "\n\n" .
                   file_get_contents(__DIR__.'/Resources/TrefoilBookPublishCommandHelp.txt');

        $this->setHelp($newHelp);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // get and save the themes_dir option
        $themesDir = $input->getOption('themes_dir' ?: $this->app['trefoil.app.dir.resources'].'/Themes');
        $this->app['trefoil.publishing.dir.themes'] = $themesDir;

        // register our resources
        $this->app['dispatcher']->addSubscriber(new RegisterResources());

        // and go!
        parent::execute($input, $output);
    }
}
