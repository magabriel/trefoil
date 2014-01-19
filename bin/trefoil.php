<?php

if (!file_exists(__DIR__.'/../vendor/autoload.php')) {
    throw new \RuntimeException(
        "\n"
        ."[ERROR] trefoil depends on some external libraries and components.\n"
        ."It seems that those dependencies aren't properly installed.\n\n"
        ."Perhaps you forgot to execute 'php composer.phar install' before\n"
        ."using trefoil for the first time?\n\n"
        ."This command requires that you have previously installed Composer.\n"
        ."To do so, execute the following command:\n\n"
        ." $ curl -s http://getcomposer.org/installer | php"
        ."\n\n"
    );
}

require __DIR__.'/../vendor/autoload.php';

use Trefoil\DependencyInjection\Application;
use Trefoil\Console\ConsoleApplication;

$app = new Application();
$console = new ConsoleApplication($app);

$options = getopt('',array('vvv'));
if (isset($options['vvv'])) {
    $console->setCatchExceptions(false);
}

$console->run();
