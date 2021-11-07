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

namespace Trefoil\Tests;

use Easybook\Util\Toolkit;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Trefoil\Console\ConsoleApplication;
use Trefoil\DependencyInjection\Application;

/**
 * PHPUnit test case for trefoil books.
 * To use it, extend from this class and set the fixtures directory in the
 * constructor.
 * Usage:
 *  "phpunit my/path/to/MyTest.php [arguments]"
 * where:
 * - "MyTest" is a class that extends this.
 * - [arguments] are optional:
 *      - "--debug" will show verbose messages and leave the test output
 *        files for inspection after execution (such as current results).
 *      - ":fixture-name" will execute only one fixture instead of all.
 * Example:
 *  "phpunit --debug src/Trefoil/Tests/Plugins/PluginsTest.php :book-test-LinkCheckPlugin"
 */
abstract class BookPublishingAllTestCase extends TestCase
{
    protected static $currentBook;
    protected static $lastBook;
    protected static $someEditionOfSameBookHasErrors;
    protected $tmpDirBase;
    protected $tmpDir;
    protected $fixturesDir;
    protected $app;
    protected $filesystem;
    protected $console;
    protected $isDebug;

    public function __construct($name = null,
                                array $data = [],
                                $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        $this->app = new Application();
        $this->filesystem = new Filesystem();
        $this->console = new ConsoleApplication($this->app);

        $this->isDebug = array_key_exists('debug', getopt('', ['debug']));

        $className = basename(str_replace('\\', '/', static::class));
        $this->tmpDirBase = $this->app['app.dir.cache'].'/'.'phpunit_debug/'.$className;

        if ($this->filesystem->exists($this->tmpDirBase)) {
            $this->filesystem->remove($this->tmpDirBase);
        }
    }

    public function tearDown(): void
    {
        $delete = true;

        if ($this->hasFailed()) {

            if (static::$currentBook === static::$lastBook) {
                static::$someEditionOfSameBookHasErrors = true;
            }

            if ($this->isDebug) {
                echo '>>> Actual and expected results not deleted: '.$this->tmpDir;
                $delete = false;
            }
        }

        if ($delete && !static::$someEditionOfSameBookHasErrors) {
            $this->filesystem->remove($this->tmpDir);
        }

        parent::tearDown();
    }

    /**
     * Fixtures provider.
     *
     * @return array
     * @throws \Exception
     */
    public function bookProvider(): array
    {
        if (empty($this->fixturesDir) || !file_exists($this->fixturesDir)) {
            throw new \RuntimeException('[ERROR] Please provide a value for $this->fixturesDir');
        }

        if ($this->isDebug) {
            echo '> Using fixtures from '.$this->fixturesDir."\n";
        }

        // find the test books
        $fixtures = Finder::create()->directories()->name('book*')->depth(0)->sortByName()->in($this->fixturesDir);

        $books = [];

        // look if only one fixture should be tested
        global $argv;
        $fixtureName = end($argv);
        $fixtureName = substr($fixtureName, 0, 1) === ':' ? substr($fixtureName, 1) : '';

        if ($fixtureName) {
            echo sprintf('> Testing only fixture "%s"', $fixtureName)."\n";
        }

        foreach ($fixtures as $fixture) {
            $slug = $fixture->getFileName();

            if ($fixtureName && $slug !== $fixtureName) {
                continue;
            }

            // look for and publish all the book editions
            $bookConfigFile = $this->fixturesDir.'/'.$slug.'/input/config.yml';
            $bookConfig = Yaml::parse(file_get_contents($bookConfigFile));

            /** @var string[] $editions */
            $editions = $bookConfig['book']['editions'];

            foreach ($editions as $editionName => $editionConfig) {
                $books[$slug.' '. $editionName] = [
                    $slug,
                    $editionName,
                ];
            }
        }

        return $books;
    }

    /**
     * Publish and test one book.
     *
     * @dataProvider bookProvider
     */
    public function testBookPublish($bookName,
                                    $editionName): void
    {
        $slug = $bookName;

        static::$lastBook = static::$currentBook ?: $bookName;
        static::$currentBook = $bookName;
        if (static::$currentBook !== static::$lastBook) {
            static::$someEditionOfSameBookHasErrors = false;
        }

        $this->tmpDir = $this->tmpDirBase.'/'.$slug;

        if ($this->isDebug) {
            echo sprintf("\n".'- Processing test "%s"'."\n", $slug);
        }

        $thisBookDir = $this->fixturesDir.$slug;

        // mirror test book contents in temp dir
        $this->filesystem->mirror(
            $thisBookDir.'/input',
            $this->tmpDir);

        // look for and publish the book edition
        $bookConfigFile = $this->tmpDir.'/config.yml';

        // publish the book edition
        $input = new ArrayInput(
            [
                'command'      => 'publish',
                'slug'         => $slug,
                'edition'      => $editionName,
                '--dir'        => $this->tmpDirBase,
                '--themes_dir' => $this->fixturesDir.'Themes',
            ]);

        $output = new NullOutput();
        if ($this->isDebug) {
            // we want the full output in debug mode
            $output = new ConsoleOutput(OutputInterface::VERBOSITY_NORMAL, true);
        }

//        $this->console->find('publish')->run($input, $output);
        $command = $this->console->find('publish');
        $command->run($input, $output);

        // look for config.yml modification
        $expectedBookConfigFile = $thisBookDir.'/expected/config.yml';
        if (file_exists($expectedBookConfigFile)) {
            static::assertFileEquivalent(
                $expectedBookConfigFile,
                $bookConfigFile,
                'Book config.yml not modified correctly');
        }

        // assert that generated files are exactly the same as expected
        $generatedFiles = Finder::create()->files()->notName('.gitignore')->in($this->tmpDir.'/Output/'.$editionName);

        foreach ($generatedFiles as $file) {
            /* @var $file SplFileInfo */

            if ('epub' === $file->getExtension()) {
                // unzip both files to compare its contents
                $workDir = $this->tmpDir.'/unzip/'.$editionName;
                $generated = $workDir.'/generated';
                $expected = $workDir.'/expected';

                Toolkit::unzip($file->getRealPath(), $generated);
                Toolkit::unzip(
                    $thisBookDir.'/expected/'.$editionName.'/'.$file->getRelativePathname(),
                    $expected);

                // assert that generated files insize EPUB are exactly the same as expected
                $this->checkGeneratedFiles($expected, $generated, $file->getPathname());

                // assert that all required files inside EPUB are generated
                $this->checkForMissingFiles($expected, $generated);

            } elseif ('mobi' === $file->getExtension()) {
                // mobi files cannot be compared to expected results
                // because kindlegen does funny things with the contents
                // so do nothing

            } else {
                static::assertFileEquivalent(
                    $thisBookDir.'/expected/'.$editionName.'/'.$file->getRelativePathname(),
                    $file->getPathname(),
                    sprintf("'%s' file not properly generated", $file->getPathname()));
            }

            // assert that all required files for this edition are generated
            $this->checkForMissingFiles(
                $thisBookDir.'/expected/'.$editionName,
                $this->tmpDir.'/Output/'.$editionName);

            // assert that book publication took less than 5 seconds
            static::assertLessThan(
                10,
                $this->app['app.timer.finish'] - $this->app['app.timer.start'],
                sprintf("Publication of '%s' edition for '%s' book took more than 10 seconds", $editionName, $slug));
        }
    }

    static function assertFileEquivalent(string $expected,
                                         string $actual,
                                         string $message = '',
                                         bool $canonicalize = false,
                                         bool $ignoreCase = false): void
    {
        if (pathinfo($expected, PATHINFO_EXTENSION) === 'html') {
            static::assertFileExists($expected, $message);
            static::assertFileExists($actual, $message);

            $options = [
                'drop-empty-elements' => false,
                'drop-empty-paras'    => false,
                'escape-scripts'      => false,
                'fix-backslash'       => false,
                'fix-style-tags'      => false,
                'fix-uri'             => false,
                'lower-literals'      => false,
                'skip-nested'         => false,
            ];

            $expectedContents = tidy_repair_string(file_get_contents($expected));
            $actualContents = tidy_repair_string(file_get_contents($actual));

            static::assertEquals($expectedContents, $actualContents);

            return;
        }

        self::assertFileEquals($expected, $actual, $message, $canonicalize, $ignoreCase);
    }

    /**
     * Assert that all generated files have the expected contents.
     *
     * @param string $dirExpected
     * @param string $dirGenerated
     * @param string $zipName
     * @apram string $zipName
     */
    protected
    function checkGeneratedFiles($dirExpected,
                                 $dirGenerated,
                                 $zipName): void
    {
        $genFiles = Finder::create()->files()->notName('.gitignore')->in($dirGenerated);

        foreach ($genFiles as $genFile) {
            static::assertFileEquivalent(
                $dirExpected.'/'.$genFile->getRelativePathname(),
                $genFile->getPathname(),
                sprintf(
                    "'%s' file (into ZIP file '%s') not properly generated",
                    $genFile->getRelativePathname(),
                    $zipName));
        }
    }

    /**
     * Assert that all expected files were generated.
     *
     * @param string $dirExpected
     * @param string $dirGenerated
     */
    protected
    function checkForMissingFiles($dirExpected,
                                  $dirGenerated): void
    {
        $expectedFiles = Finder::create()->files()->notName('.gitignore')->in($dirExpected);

        foreach ($expectedFiles as $file) {
            static::assertFileExists(
                $dirGenerated.'/'.$file->getRelativePathname(),
                sprintf("'%s' file has not been generated", $file->getPathname()));
        }
    }
}

