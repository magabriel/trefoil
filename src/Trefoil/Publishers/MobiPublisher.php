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

namespace Trefoil\Publishers;

use Symfony\Component\Process\Process;

/**
 * It publishes the book as a MOBI file. All the internal links are transformed
 * into clickable cross-section book links.
 */
class MobiPublisher extends Epub2Publisher
{

    // Kindle Publishing Guidelines rule that ebooks
    // should contain an HTML TOC, so it cannot be excluded
    protected $excludedElements = [
        'cover',
        'lot',
        'lof'
    ];

    public function assembleBook(): void
    {
        parent::assembleBook();

        $epubFilePath = $this->app['publishing.dir.output'] . '/book.epub';

        if (!file_exists($this->app['kindlegen.path'])) {
            $this->app['console.output']->write("\n\n" .
                sprintf('Kindlegen executable not found in %s', $this->app['kindlegen.path']) . "\n\n");
        } else {
            $command = sprintf(
                '%s %s -o book.mobi %s',
                $this->app['kindlegen.path'],
                $this->app['kindlegen.command_options'],
                $epubFilePath
            );

            $process = new Process($command);
            $process->run();

            $this->app['console.output']->write("\n\n" . $process->getOutput() . "\n\n");
        }

        // remove the book.epub file used to generate the book.mobi file
        $this->app['filesystem']->remove($epubFilePath);
    }

}
