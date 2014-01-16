<?php
namespace Trefoil\Tests;

use Easybook\Tests\TestCase;
use Easybook\Util\Toolkit;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Yaml\Yaml;
use Trefoil\Console\ConsoleApplication;
use Trefoil\DependencyInjection\Application;

abstract class BookPublishingAllTestCase extends TestCase
{
    protected $tmpDirBase;
    protected $tmpDir;
    protected $fixturesDir;
    protected $app;
    protected $filesystem;
    protected $console;
    protected $isDebug;

    public function __construct($name = NULL, array $data = array(), $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        $this->app = new Application();
        $this->filesystem = new Filesystem();
        $this->console = new ConsoleApplication($this->app);

        $this->isDebug = array_key_exists('debug', getopt('', array('debug')));

        $className = basename(str_replace('\\', '/', get_called_class()));
        $this->tmpDirBase = $this->app['app.dir.cache'].'/'.'phpunit_debug/'.$className;

        if ($this->filesystem->exists($this->tmpDirBase)) {
            $this->filesystem->remove($this->tmpDirBase);
        }
    }

    public function setUp()
    {
        parent::setUp();
    }

    public function tearDown()
    {
        $delete = true;

        if ($this->hasFailed()) {
            if ($this->isDebug) {
                echo ">>> Actual and expected results not deleted: ".$this->tmpDir;
                $delete = false;
            }
        }

        if ($delete) {
            $this->filesystem->remove($this->tmpDir);
        }

        parent::tearDown();
    }

    /**
     * @return array
     */
    public function bookProvider()
    {
        if (empty($this->fixturesDir) || !file_exists($this->fixturesDir )) {
            throw new \Exception('[ERROR] Please provide a value for $this->fixturesDir');
        }

        if ($this->isDebug) {
            echo '> Using fixtures from '.$this->fixturesDir."\n";
        }

        // find the test books
        $fixtures = Finder::create()
                    ->directories()
                    ->name('book*')
                    ->depth(0)
                    ->sortByName()
                    ->in($this->fixturesDir)
                    ;

        $books = array();

        // look if only one fixture should be tested
        global $argv;
        $fixtureName = end($argv);
        $fixtureName = substr($fixtureName,0,1) == ':' ? substr($fixtureName,1) : '';

        if ($fixtureName) {
            echo sprintf('> Testing only fixture "%s"', $fixtureName)."\n";
        }

        foreach ($fixtures as $fixture) {
            $slug = $fixture->getFileName();

            if ($fixtureName && $slug != $fixtureName) {
                continue;
            }

            // look for and publish all the book editions
            $bookConfigFile = $this->fixturesDir.'/'.$slug.'/input/config.yml';

            $bookConfig = Yaml::parse($bookConfigFile);
            $editions = $bookConfig['book']['editions'];

            foreach ($editions as $editionName => $editionConfig) {
                $books[]= array(
                        $slug,
                        $editionName
                        );
            }
        }

        return $books;
    }

    /**
     * @dataProvider bookProvider
     */
    public function testBookPublish($bookName, $editionName)
    {
        $slug = $bookName;

        $this->tmpDir = $this->tmpDirBase.'/'.$slug;

        if ($this->isDebug) {
            echo sprintf("\n".'- Processing test "%s"'."\n", $slug);
        }

        $thisBookDir = $this->fixturesDir.$slug;

        // mirror test book contents in temp dir
        $this->filesystem->mirror(
                $thisBookDir.'/input',
                $this->tmpDir
        );

        // look for and publish the book edition
        $bookConfigFile = $this->tmpDir.'/config.yml';

        // publish the book edition
        $input = new ArrayInput(array(
                'command' => 'publish',
                'slug'    => $slug,
                'edition' => $editionName,
                '--dir'   => $this->tmpDirBase,
                '--themes_dir' => $this->fixturesDir.'Themes'
        ));

        $output = new NullOutput();
        if ($this->isDebug) {
            // we want the full output in debug mode
            $output = new ConsoleOutput(OutputInterface::VERBOSITY_NORMAL, true);
        }

        $this->console->find('publish')->run($input, $output);

        // look for config.yml modification
        $expectedBookConfigFile = $thisBookDir.'/expected/config.yml';
        if (file_exists($expectedBookConfigFile)) {
            $this->assertFileEquals($expectedBookConfigFile,
                                    $bookConfigFile,
                                    'Book config.yml not modified correctly');
        }

        // assert that generated files are exactly the same as expected
        $generatedFiles = Finder::create()
            ->files()
            ->notName('.gitignore')
            ->in($this->tmpDir.'/Output/'.$editionName)
            ;

        foreach ($generatedFiles as $file) {
            /* @var $file SplFileInfo */

            if ('epub' == $file->getExtension()) {
                // unzip both files to compare its contents
                $workDir = $this->tmpDir.'/unzip/'.$editionName;
                $generated = $workDir.'/generated';
                $expected = $workDir.'/expected';

                Toolkit::unzip($file->getRealPath(), $generated);
                Toolkit::unzip($thisBookDir.'/expected/'.
                        $editionName.'/'.$file->getRelativePathname(), $expected);

                // assert that generated files insize EPUB are exactly the same as expected
                $this->checkGeneratedFiles($expected, $generated, $file->getPathName());

                // assert that all required files inside EPUB are generated
                $this->checkForMissingFiles($expected, $generated);

            } elseif ('mobi' == $file->getExtension()) {
                // mobi files cannot be compared to expected results
                // because kindlegen does funny things with the contents
                // so do nothing
                
            } else {
                $this->assertFileEquals(
                        $thisBookDir.'/expected/'.$editionName.'/'.$file->getRelativePathname(),
                        $file->getPathname(),
                        sprintf("'%s' file not properly generated", $file->getPathname())
                );
            }

            // assert that all required files for this edition are generated
            $this->checkForMissingFiles(
                    $thisBookDir.'/expected/'.$editionName,
                    $this->tmpDir.'/Output/'.$editionName);

            // assert that book publication took less than 5 seconds
            $this->assertLessThan(
                    5,
                    $this->app['app.timer.finish'] - $this->app['app.timer.start'],
                    sprintf("Publication of '%s' edition for '%s' book took more than 5 seconds", $editionName, $slug)
            );
        }
    }

    /**
     * Assert that all generated files have the expected contents
     *
     * @param string $dirExpected
     * @param string $dirGenerated
     * @param $zipName
     * @apram string $zipName
     */
    protected function checkGeneratedFiles($dirExpected, $dirGenerated, $zipName)
    {
        $genFiles = Finder::create()
                    ->files()
                    ->notName('.gitignore')
                    ->in($dirGenerated);

        foreach ($genFiles as $genFile) {
            $this->assertFileEquals(
                    $dirExpected.'/'.$genFile->getRelativePathname(),
                    $genFile->getPathname(),
                    sprintf("'%s' file (into ZIP file '%s') not properly generated",
                            $genFile->getRelativePathname(), $zipName)
            );
        }
    }

    /**
     * Assert that all expected files were generated
     *
     * @param string $dirExpected
     * @param string $dirGenerated
     */
    protected function checkForMissingFiles($dirExpected, $dirGenerated)
    {
        $expectedFiles = Finder::create()
                        ->files()
                        ->notName('.gitignore')
                        ->in($dirExpected);

        foreach ($expectedFiles as $file) {
            $this->assertFileExists(
                    $dirGenerated.'/'.$file->getRelativePathname(),
                    sprintf("'%s' file has not been generated", $file->getPathname())
            );
        }
    }
}

