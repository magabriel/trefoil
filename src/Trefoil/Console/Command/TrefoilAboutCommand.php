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

use Easybook\Console\Command\AboutCommand as EasybookAboutCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class TrefoilAboutCommand
 *
 * @package Trefoil\Console\Command
 */
class TrefoilAboutCommand extends EasybookAboutCommand
{
    private $appVersion;

    /**
     * TrefoilAboutCommand constructor.
     *
     * @param string $trefoilVersion
     * @param string $easybookVersion
     */
    public function __construct(string $trefoilVersion, string $easybookVersion)
    {
        $this->appVersion = $trefoilVersion;
        
        parent::__construct($easybookVersion);
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $commandHelp = <<<COMMAND_HELP

 trefoil (%s)
 %s
 
 <info>trefoil</info> extends <comment>easybook</comment> providing additional features.
COMMAND_HELP;

        $output->writeln(
            sprintf(
                $commandHelp,
                $this->appVersion,
                str_repeat('=', 11 + strlen($this->appVersion))
            )
        );
        
        parent::execute($input, $output);
    }
}
