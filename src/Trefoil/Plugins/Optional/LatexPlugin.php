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

namespace Trefoil\Plugins\Optional;

use Easybook\Events\BaseEvent;
use Easybook\Events\EasybookEvents;
use Easybook\Events\ParseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Trefoil\Events\TrefoilEvents;
use Trefoil\Plugins\BasePlugin;
use Symfony\Component\Process\Process;
use Symfony\Component\Filesystem\Filesystem;

/**
 */
class LatexPlugin extends BasePlugin implements EventSubscriberInterface
{

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            TrefoilEvents::PRE_PUBLISH_AND_READY => 'onPrePublish',
            EasybookEvents::PRE_PARSE => 'onItemPreParse',
            EasybookEvents::POST_PARSE => ['onItemPostParse', -600], // after ImageExtraPlugin
        ];
    }

    public function onPrePublish(BaseEvent $event)
    {
        $this->init($event);

        // Check if we have a path for gladtex
        $gladtex = $this->getConfigOption('easybook.parameters.gladtex.path');
        $gladtexOptions = $this->getConfigOption('easybook.parameters.gladtex.command_options');

        if (!$gladtex || !file_exists($gladtex)) {
            throw new \RuntimeException(
                'The GladTeX library needed to process LaTeX in documents cannot be found. ' .
                'Check that you have set your custom GladTeX path in the book\'s config.yml file.'
            );
        }

        // Delete the image cache directory if it exists
        /** @var Filesystem */
        $filesystem = $this->app['filesystem'];
        $imagesOutputDir = $this->getLatexCacheDir();
        echo "\n================== imagesOutputDir ==================\n";
        print_r($imagesOutputDir);
        echo "\n================== /imagesOutputDir ==================\n";
        // $this->writeLn(sprintf('About to delete images output directory: %s', $imagesOutputDir));
        echo sprintf('About to delete images output directory: %s', $imagesOutputDir);

        // Remove the cache directory if it exists
        if ($filesystem->exists($imagesOutputDir)) {
            $filesystem->remove($imagesOutputDir);
        }
    }

    /**
     * @param ParseEvent $event
     */
    public function onItemPreParse(ParseEvent $event)
    {
        $this->init($event);

        $content = $event->getItemProperty('original');

        $content = $this->latexPreProcess($content);

        $event->setItemProperty('original', $content);
    }

    /**
     * @param ParseEvent $event
     */
    public function onItemPostParse(ParseEvent $event)
    {
        $this->init($event);

        $content = $this->item['content'];

        $content = $this->latexPostProcess($content);

        $this->item['content'] = $content;
        $event->setItem($this->item);
    }

    /**
     *
     * @param string $content
     * @return string
     */
    protected function latexPreProcess($content): string
    {
        // Nothing to do here, we will process the content in the post parse event
        return $content;
    }

    /**
     * @param $content
     * @return mixed
     */
    protected function latexPostProcess($content)
    {
        // Replace delimiters with the required HTML tags
        $content = str_replace(
            [
                '&#92;(', // inline opening delimiter
                '&#92;)', // inline closing delimiter
                '&#92;[', // block opening delimiter
                '&#92;]' // block closing delimiter
            ],
            [
                '<eq env="inlinemath">',
                '</eq>',
                '<div class="center"><eq env="inlinemath">',
                '</eq></div>'
            ],
            $content
        );

        $content = $this->processLatexFormulas($content);

        return $content;
    }

    protected function getLatexCacheDir()
    {
        // gdlatex needs an images dir path relative to the current working directory
        /** @var Filesystem */
        $filesystem = $this->app['filesystem'];
        return $filesystem->makePathRelative($this->app['app.dir.cache'] . '/latex_images/' . $this->app['publishing.book.slug'], getcwd());
    }

    protected function processLatexFormulas($content)
    {
        // Get the path for gladtex
        $gladtex = $this->getConfigOption('easybook.parameters.gladtex.path');
        $gladtexOptions = $this->getConfigOption('easybook.parameters.gladtex.command_options');

        // gdlatex needs an images dir path relative to the current working directory
        /** @var Filesystem */
        $filesystem = $this->app['filesystem'];
        $imagesOutputDir = $this->getLatexCacheDir();

        // Run it
        $command = sprintf(
            // '"%s" -o - --png -d "%s" -f "" -r 300  -',
            '"%s" -o - --png -d "%s" -f 12 %s -', // TODO: Font size as config option
            $gladtex,
            $imagesOutputDir,
            $gladtexOptions
        );

        $this->writeLn(sprintf('Running GladTeX with command: %s', $command));

        $process = new Process($command);
        $process->setInput($content);
        $process->run();
        $outputText = $process->getOutput();

        if (!$process->isSuccessful()) {
            $this->writeLn(sprintf('GladTeX errors detected: %s', $outputText), 'error');
            return $content;
        }

        // Replace the generated image paths with "images/" (because the images will be copied there 
        // later in the publishing phase)
        $outputText = str_replace(
            $imagesOutputDir,
            'images/',
            $outputText
        );

        // Create the images directory in the contents directory if it does not exist
        $imagesLatexDir = $this->app['publishing.dir.contents'] . '/images/latex';
        if (!$filesystem->exists($imagesLatexDir)) {
            $filesystem->mkdir($imagesLatexDir);
        }

        // Copy the generated images to the directory
        $filesystem->mirror($imagesOutputDir, $imagesLatexDir);

        return $outputText;
    }

}
