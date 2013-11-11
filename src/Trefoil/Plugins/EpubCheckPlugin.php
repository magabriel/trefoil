<?php
namespace Trefoil\Plugins;
use Symfony\Component\Process\Process;

use Symfony\Component\Finder\Finder;

use Easybook\Publishers\Epub2Publisher;

use Easybook\Events\EasybookEvents;

use Easybook\Util\Toolkit;

use Easybook\Events\BaseEvent;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Easybook\Events\EasybookEvents as Events;
use Easybook\Events\ParseEvent;

/**
 * Plugin to check the generated epub ebook using the EpubCheck utility.
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
                EasybookEvents::POST_PUBLISH => array('onPostPublish',-900)
        );
    }

    public function onPostPublish(BaseEvent $event)
    {
        $this->init($event);

        if ($this->format != 'Epub2') {
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
            $this->writeLn('<error>The EpubCheck library needed to check EPUB books cannot be found. '.
                    'Check that you have set your custom Epubcheck path in the book\'s config.yml file.</error>');
            return;
        }

        $epubFilePath = $this->app['publishing.dir.output'].'/book.epub';

        $command = sprintf("java -jar '%s' '%s' %s",
                $epubcheck,
                $epubFilePath,
                $epubcheckOptions
        );

        $this->write(' Running EpubCheck...');

        $process = new Process($command);
        $process->run();

        $outputText = $process->getOutput() . $process->getErrorOutput();

        if ($process->isSuccessful()) {
            $this->writeLn('No errors', false);
        } else {
            if (preg_match('/^ERROR:/Um', $outputText)) {
                $this->writeLn('<error>Some errors detected.</error>'."\n", false);
            } else {
                $this->writeLn('<comment>Some warnings detected.</comment>'."\n", false);
            }
        }



        $reportFile = $this->app['publishing.dir.output'] . '/report-EpubCheckPlugin.txt';
        file_put_contents($reportFile, $outputText);
    }
}
