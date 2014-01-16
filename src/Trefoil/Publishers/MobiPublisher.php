<?php

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
    protected $excludedElements = array(
        'cover',
        'lot',
        'lof');

    public function assembleBook()
    {
        parent::assembleBook();

        $epubFilePath = $this->app['publishing.dir.output'] . '/book.epub';

        $command = sprintf("%s %s -o book.mobi %s", $this->app['kindlegen.path'], $this->app['kindlegen.command_options'], $epubFilePath
        );

        $process = new Process($command);
        $process->run();

        $this->app['console.output']->write("\n\n" . $process->getOutput() . "\n\n");

        // remove the book.epub file used to generate the book.mobi file
        $this->app['filesystem']->remove($epubFilePath);
    }

}
