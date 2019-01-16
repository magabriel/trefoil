<?php
declare(strict_types=1);
/*
 * This file is part of the trefoil application.
 *
 * (c) Miguel Angel Gabriel <magabriel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Trefoil\Console\Command;

use Easybook\Console\Command\BookPublishCommand as EasybookBookPublishCommand;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Trefoil\Bridge\Easybook\RegisterResources;

/**
 * Class TrefoilBookPublishCommand
 *
 * @package Trefoil\Console\Command
 */
class TrefoilBookPublishCommand extends EasybookBookPublishCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this->addOption(
            'themes_dir',
            '',
            InputOption::VALUE_OPTIONAL,
            '<info>(*)</info> Path of the custom themes directory'
        );

        $oldHelp = $this->getHelp();

        $newHelp = 'NOTE: Items marked with <info>(*)</info> are <comment>trefoil</comment> extensions.'."\n\n".
            $oldHelp."\n\n".
            file_get_contents(__DIR__.'/Resources/TrefoilBookPublishCommandHelp.txt');

        $this->setHelp($newHelp);
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        // get and save the themes_dir option
        $themesDir = $input->getOption('themes_dir');
        if ($themesDir) {
            $this->app['trefoil.publishing.dir.themes'] = $themesDir;
        }

        // register our resources
        $this->app['dispatcher']->addSubscriber(new RegisterResources());
        $this->app['console.progress'] = $this->getHelperSet()->get('progress');

        // and go!
        parent::execute($input, $output);
    }
}
