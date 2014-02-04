<?php
/*
 * This file is part of the trefoil application.
 *
 * (c) Miguel Angel Gabriel <magabriel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
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
             ->setHelp(
             'The <info>version</info> command shows you the installed version of <info>easybook</info> and <info>trefoil</info>'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var Application $app */
        $app = $this->app;

        $output->writeln(
               array(
                   '',
                   $app['app.signature'],
                   ' <info>easybook</info> installed version: '
                   . '<comment>' . $app->getVersion() . '</comment>',
                   ' <info>trefoil</info>  installed version: '
                   . '<comment>' . $app->getMyVersion() . '</comment>',
                   '',
               )
        );
    }
}
