<?php
namespace Trefoil\Tests;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tests\Output\ConsoleOutputTest;
use Symfony\Component\Console\Output\ConsoleOutput;
use Trefoil\Console\ConsoleApplication;
use Trefoil\DependencyInjection\Application;
use Easybook\Tests\TestCase;
use Easybook\Tests\Publishers\PublisherTest;
use Easybook\Util\Toolkit;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Finder\SplFileInfo;

abstract class BookPublishingTestCase extends TestCase
{
    protected $app;
    protected $filesystem;
    protected $tmpDir;
    protected $fixturesDir;

    public function __construct()
    {
        // default, to be overriden by instantiator
        $this->fixturesDir = __DIR__.'/fixtures/';
    }

    public function setUp()
    {
        $this->app = new Application();

        $this->filesystem = new Filesystem();

        // setup temp dir for generated files
        if (getopt('', array('debug'))) {
            // reuse the temp dir
            $className = basename(str_replace('\\', '/', get_called_class()));
            $this->tmpDir = $this->app['app.dir.cache'].'/'.'phpunit_debug/'.$className;
            if ($this->filesystem->exists($this->tmpDir)) {
                $this->filesystem->remove($this->tmpDir);
            }
        } else {
            // create new temp dir
            $this->tmpDir = $this->app['app.dir.cache'].'/'.uniqid('phpunit_', true);
            $this->filesystem->mkdir($this->tmpDir);
        }

        parent::setUp();
    }

    public function tearDown()
    {
        $delete = true;
        if ($this->hasFailed()) {
            if (getopt('', array('debug'))) {
                echo ">>> Actual and expected results not deleted: ".$this->tmpDir;
                $delete = false;
            }
        }

        if ($delete) {
            $this->filesystem->remove($this->tmpDir);
        }

        parent::tearDown();
    }

    public function testBookPublish()
    {
        $console = new ConsoleApplication($this->app);

        $booksDir = $this->fixturesDir;
        $themesDir = $this->fixturesDir.'Themes';

        // find the test books
        $books = $this->app->get('finder')
                    ->directories()
                    ->name('book*')
                    ->depth(0)
                    ->sortByName()
                    ->in($booksDir)
                    ;

        foreach ($books as $book) {
            $slug = $book->getFileName();

            if (getopt('', array('debug'))) {
                echo sprintf("\n".'- Processing test "%s"'."\n", basename($book));
            }

            $thisBookDir = $this->fixturesDir.$slug;

            // mirror test book contents in temp dir
            $this->filesystem->mirror(
                    $thisBookDir.'/input',
                    $this->tmpDir.'/'.$slug
            );

            // look for and publish all the book editions
            $bookConfigFile = $this->tmpDir.'/'.$slug.'/config.yml';
            $bookConfig = Yaml::parse($bookConfigFile);
            $editions = $bookConfig['book']['editions'];
            foreach ($editions as $editionName => $editionConfig) {

                // publish each book edition
                $input = new ArrayInput(array(
                        'command' => 'publish',
                        'slug'    => $slug,
                        'edition' => $editionName,
                        '--dir'   => $this->tmpDir,
                        '--themes_dir' => $themesDir
                ));

                $output = new NullOutput();
                if (getopt('', array('debug'))) {
                    // we want the full output in debug mode
                    $output = new ConsoleOutput(OutputInterface::VERBOSITY_NORMAL, true);
                }

                $console->find('publish')->run($input, $output);

                // look for config.yml modification
                $expectedBookConfigFile = $thisBookDir.'/expected/config.yml';
                if (file_exists($expectedBookConfigFile)) {
                    $expectedBookConfig = Yaml::parse($expectedBookConfigFile);
                    $this->assertFileEquals($expectedBookConfigFile,
                                            $bookConfigFile,
                                            'Book config.yml not modified correctly');
                }

                // assert that generated files are exactly the same as expected
                $generatedFiles = $this->app->get('finder')
                    ->files()
                    ->notName('.gitignore')
                    ->in($this->tmpDir.'/'.$slug.'/Output/'.$editionName)
                    ;
                foreach ($generatedFiles as $file) {
                    /* @var $file SplFileInfo */
                    if ('epub' == $file->getExtension()) {
                        // unzip both files to compare its contents
                        $workDir = $this->tmpDir.'/'.$slug.'/unzip/'.$editionName;
                        $generated = $workDir.'/generated';
                        $expected = $workDir.'/expected';

                        Toolkit::unzip($file->getRealPath(), $generated);
                        Toolkit::unzip($thisBookDir.'/expected/'.
                                $editionName.'/'.$file->getRelativePathname(), $expected);

                        // assert that generated files are exactly the same as expected
                        $genFiles = $this->app->get('finder')
                            ->files()
                            ->notName('.gitignore')
                            ->in($generated);

                        foreach ($genFiles as $genFile) {
                            $this->assertFileEquals(
                                    $expected.'/'.$genFile->getRelativePathname(),
                                    $genFile->getPathname(),
                                    sprintf("'%s' file (into ZIP file '%s') not properly generated",
                                            $genFile->getRelativePathname(), $file->getPathName())
                            );
                        }

                        // assert that all required files are generated
                        $this->checkForMissingFiles($expected,$generated);

                    } else {
                        $this->assertFileEquals(
                                $thisBookDir.'/expected/'.$editionName.'/'.$file->getRelativePathname(),
                                $file->getPathname(),
                                sprintf("'%s' file not properly generated", $file->getPathname())
                        );
                    }
                }

                // assert that all required files are generated
                $this->checkForMissingFiles(
                        $thisBookDir.'/expected/'.$editionName,
                        $this->tmpDir.'/'.$slug.'/Output/'.$editionName);

                // assert than book publication took less than 5 seconds
                $this->assertLessThan(
                        5,
                        $this->app['app.timer.finish'] - $this->app['app.timer.start'],
                        sprintf("Publication of '%s' edition for '%s' book took more than 5 seconds", $editionName, $slug)
                );

                // reset app state before the next publishing
                $this->app = new Application();
                $console = new ConsoleApplication($this->app);
            }
        }
    }

    /*
     * Assert that all expected files were generated
    */
    protected function checkForMissingFiles($dirExpected, $dirGenerated)
    {
        $expectedFiles = $this->app->get('finder')
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

