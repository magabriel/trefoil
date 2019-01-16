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

use Easybook\Console\Command\BaseCommand;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Class TrefoilTestUpdateExpectedResultsCommand
 *
 * @package Trefoil\Console\Command
 */
class TrefoilTestUpdateExpectedResultsCommand extends BaseCommand
{
    protected $userConfirmed = false;

    /** @var  OutputInterface */
    protected $output;

    /** @var DialogHelper $dialog */
    protected $dialog;

    protected function configure(): void
    {
        $this->setDefinition([])
             ->setName('test:update-expected-results')
             ->setDescription('Update expected results from debug output');

        $help = <<<HELP
The <info>test:update-expected-results</info> command updates the expected results for tests 
that generate a "book" as result. This kind of test can be easily affected when
the theme they use is modified by an unrelated code change, needing a tedious 
manual refresh of the fixture for each test expected results. 

Instructions: 

1. Run the tests in <info>debug</info> mode. This will leave the generated output of each test 
   in the <info>app/Cache</info> directory.
2. Manually review and check the actual results to ensure tests are failing as a 
   collateral effect of some other unrelated change and is OK to update them.
3. Run this command and confirm its execution. This will copy each test's actual
   results as the new expected results for future runs.

HELP;

        $this->setHelp(
            $help
        );
    }

    /**
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $this->dialog = $this->getHelperSet()->get('dialog');

        $this->userConfirmed =
            $this->dialog->askConfirmation(
                $output,
                '<question>Are you sure you want to continue?</question> [yN] ',
                false
            );
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $this->output = $output;

        if (!$this->userConfirmed) {
            $output->writeln('Not executed');

            return;
        }

        $this->processTestGroup('Plugins');
        $this->processTestGroup('Functional');
    }

    /**
     * @param string $group
     */
    protected function processTestGroup(string $group): void
    {
        $cacheDir = $this->app['app.dir.cache'].'/'.'phpunit_debug/';
        $groupTest = $group.'Test';
        $groupTestDir = $cacheDir.$groupTest;
        $fixturesDir = $this->app['trefoil.app.dir.base'].'/src/Trefoil/Tests/'.$group.'/fixtures';

        $this->output->writeln('');

        $this->output->writeln(
            sprintf('Processing Test Group <bg=green;fg=black>%s</>.', $group)
        );

        $this->output->writeln('');

        if (!is_dir($fixturesDir)) {
            $this->output->writeln(
                sprintf('<error> ERROR </error> No fixtures test directory found "%s".', $fixturesDir)
            );

            return;
        }

        if (!is_dir($groupTestDir)) {
            $this->output->writeln(sprintf('<error> ERROR </error> No tests output in %s directory.', $groupTestDir));

            return;
        }

        /** @var Filesystem $filesystem */
        $filesystem = $this->app['filesystem'];

        /** @var Finder $finder */
        $finder = $this->app['finder'];

        $tests = $finder->directories()
                        ->depth(0)
                        ->in($groupTestDir);

        if ($tests->count() === 0) {
            $this->output->writeln(sprintf('<error> ERROR </error> No %s Tests output found.', $groupTest));

            return;
        }

        $this->output->writeln(sprintf('Found the following %s Tests:', $group));

        /** @var SplFileInfo $test */
        foreach ($tests as $test) {
            $this->output->writeln('- '.$test->getBasename());
        }

        if (!$this->dialog->askConfirmation(
            $this->output,
            sprintf('<question>Update these %s Tests?</question> [yN] ', $group),
            false
        )
        ) {
            $this->output->writeln(sprintf('<comment> OK </comment> %s Tests not updated', $group));

            return;
        }

        /** @var SplFileInfo $test */
        foreach ($tests as $test) {
            $this->output->writeln('- '.$test->getBasename());

            $actualResultsDir = $test->getRealPath().'/Output';
            $expectedResultsDir = $fixturesDir.'/'.$test->getBasename().'/expected';

            $filesystem->mirror($actualResultsDir, $expectedResultsDir);
        }

        $this->output->writeln(sprintf('<info> OK </info> %s Tests updated', $group));
    }
}
