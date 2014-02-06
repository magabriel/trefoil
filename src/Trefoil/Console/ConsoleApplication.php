<?php
/*
 * This file is part of the trefoil application.
 *
 * (c) Miguel Angel Gabriel <magabriel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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

        /** @var $myApp \Trefoil\DependencyInjection\Application */
        $myApp = $app;

        $this->setName('trefoil');
        $this->setVersion($myApp->getMyVersion());

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
