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
 * PHPUnit test case for trefoil helpers.
 * To use it, extend from this class and set the fixtures directory in the
 * constructor.
 * Usage:
 *  "phpunit my/path/to/MyTest.php [arguments]"
 * where:
 * - "MyTest" is a class that extends this.
 * - [arguments] are optional:
 *      - "--debug" will show verbose messages and leave the test output
 *        files for inspection after execution (such as current results).
 * Example:
 *  "phpunit --debug src/Trefoil/Tests/Helpers/WordFillInText.php"
 */
abstract class HelpersTestCase extends TestCase {

    protected $tmpDirBase;
    protected $app;
    protected $filesystem;
    protected $isDebug;

    public function __construct($name = null,
            array $data = [],
            $dataName = '') {
        parent::__construct($name, $data, $dataName);

        $this->app = new Application();
        $this->filesystem = new Filesystem();

        $this->isDebug = array_key_exists('debug', getopt('', ['debug']));

        $className = basename(str_replace('\\', '/', static::class));
        $this->tmpDirBase = $this->app['app.dir.cache'] . '/' . 'phpunit_debug/' . $className;

        if ($this->filesystem->exists($this->tmpDirBase)) {
            $this->filesystem->remove($this->tmpDirBase);
        }
    }

    protected function saveTestData(string $testName, string $expected, string $actual) {

        $tmpDir = $this->tmpDirBase . '/' . $testName;
        $this->filesystem->mkdir($this->tmpDirBase);
        
        file_put_contents($tmpDir.'_expected.txt', $expected);
        file_put_contents($tmpDir.'_actual.txt', $actual);
    }

}
