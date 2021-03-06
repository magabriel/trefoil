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

namespace Trefoil\Console;

use Easybook\Console\ConsoleApplication as EasybookConsoleApplication;
use Trefoil\Console\Command\TrefoilAboutCommand;
use Trefoil\Console\Command\TrefoilBookPublishCommand;
use Trefoil\Console\Command\TrefoilTestUpdateExpectedResultsCommand;
use Trefoil\Console\Command\TrefoilVersionCommand;
use Trefoil\DependencyInjection\Application;

/**
 * Class ConsoleApplication
 *
 * @package Trefoil\Console
 */
class ConsoleApplication extends EasybookConsoleApplication
{
    /**
     * @var Application
     * Needed because it is private in base class.
     */
    private $app;

    /**
     * ConsoleApplication constructor.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        parent::__construct($app);

        $this->app = $app;

        $this->setName('trefoil');

        $this->add(new TrefoilVersionCommand());
        $this->add(new TrefoilBookPublishCommand());
        $this->add(new TrefoilAboutCommand($this->app->getMyVersion(), $this->app->getVersion()));

        $this->add(new TrefoilTestUpdateExpectedResultsCommand());
    }

    /**
     * Gets the help message.
     *
     * @return string A help message.
     */
    public function getHelp(): string
    {
        $help = [
            $this->app['app.signature'],
            '<info>trefoil</info> extends <comment>easybook</comment> providing additional features.',
        ];

        return implode("\n", $help);

    }
}
