<?php
/*
 * This file is part of the trefoil application.
 *
 * (c) Miguel Angel Gabriel <magabriel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Trefoil\Plugins\Optional;

use Easybook\Events\BaseEvent;
use Easybook\Events\EasybookEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Process\Process;
use Trefoil\Plugins\BasePlugin;
/**
 * Plugin to check the generated epub ebook using the EpubCheck utility.
 *
 * @see https://github.com/IDPF/epubcheck
 *
 * For formats: Epub
 *
 * Options can be set in the book's config.yml:
 *
 *    easybook:
 *        parameters:
 *            epubcheck.path: '/path/to/epubcheck.jar'
 *            epubcheck.command_options: ''
 *
 */
class EpubCheckPlugin extends BasePlugin implements EventSubscriberInterface
{

    public static function getSubscribedEvents()
    {
        return array(
            // runs later but before renaming
            EasybookEvents::POST_PUBLISH => array('onPostPublish', -900)
        );
    }

    public function onPostPublish(BaseEvent $event)
    {
        $this->init($event);

        if ($this->format != 'Epub') {
            // not for this format
            return;
        }

        $this->bookCheck();
    }

    protected function bookCheck()
    {
        $epubcheck = $this->getConfigOption('easybook.parameters.epubcheck.path');
        $epubcheckOptions = $this->getConfigOption('easybook.parameters.epubcheck.command_options');

        if (!$epubcheck || !file_exists($epubcheck)) {
            $this->writeLn(
                 'The EpubCheck library needed to check EPUB books cannot be found. ' .
                 'Check that you have set your custom Epubcheck path in the book\'s config.yml file.',
                 'error'
            );

            return;
        }

        $epubFilePath = $this->app['publishing.dir.output'] . '/book.epub';

        $command = sprintf(
            "java -jar '%s' '%s' %s",
            $epubcheck,
            $epubFilePath,
            $epubcheckOptions
        );

        $this->writeLn('Running EpubCheck...');

        $process = new Process($command);
        $process->run();

        $outputText = $process->getOutput() . $process->getErrorOutput();

        if ($process->isSuccessful()) {
            $this->writeLn('No errors');
        } else {
            if (preg_match('/^ERROR:/Um', $outputText)) {
                $this->writeLn('Some errors detected.</error>', 'error');
            } else {
                $this->writeLn('Some warnings detected.', "warning");
            }
        }

        $reportFile = $this->app['publishing.dir.output'] . '/report-EpubCheckPlugin.txt';
        file_put_contents($reportFile, $outputText);
    }
}
