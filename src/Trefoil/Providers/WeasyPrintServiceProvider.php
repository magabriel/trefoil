<?php

/*
 * This file is part of the trefoil application.
 *
 * (c) Miguel Angel Gabriel <magabriel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Trefoil\Providers;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Easybook\Util\WeasyPrint;
// TODO: Creeate Easybook\Util\WeasyPrint like the one in Easybook\Util\Wkhtmltopdf
// TODO: Include https://github.com/pontedilana/php-weasyprint via composer 

class WeasyPrintServiceProvider implements ServiceProviderInterface
{
    public function register(Container $app)
    {
        $app['weasyprint.path'] = null;

        // the common installation dirs for WeasyPrint in several OS
        $app['weasyprint.default_paths'] = array(
            '/usr/local/bin/weasyprint',                         # Mac OS X
            '/usr/bin/weasyprint',                               # Linux
            'C:\Program Files\WeasyPrint\weasyprint.exe',  # Windows
        );

        $app['weasyprint'] = function () use ($app) {
            $weasyPrintPath = $app['weasyprint.path'] ?: $app->findWeasyPrintExecutable();
            // ask the user about the location of the executable
            if (null === $weasyPrintPath) {
                $weasyPrintPath = $app->askForWeasyPrintExecutablePath();

                if (!file_exists($weasyPrintPath)) {
                    throw new \RuntimeException(sprintf(
                        "We couldn't find the WeasyPrint executable in the given directory (%s)",
                        $weasyPrintPath
                    ));
                }
            }

            $weasyPrint = new WeasyPrint($weasyPrintPath);
            $weasyPrint->setHtml(true);

            return $weasyPrint;
        };
    }
}
