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

        // $content = $this->latexPreProcess($content);
        $content = $this->preserveLatex($content);

        // print_r($content);

        $event->setItemProperty('original', $content);
    }

    /**
     * @param ParseEvent $event
     */
    public function onItemPostParse(ParseEvent $event)
    {
        $this->init($event);

        $content = $this->item['content'];

        $content = $this->restoreLatex($content);

        $content = $this->latexPostProcess($content);

        $this->item['content'] = $content;
        $event->setItem($this->item);
    }

    /**
     * @param string $content
     * @return string
     */
    protected function preserveLatex($content): string
    {
        echo "\n================== before preserveLatex ==================\n";
        print_r($content);
        echo "\n====================================================\n";


        // Comment out latex equations to avoid processing
        $regExp = '/';
        $regExp .= '(?<opening>\\\[\(\[])'; // opening delimiter (or "\[" or "\(")
        $regExp .= '(?<equation>.*)';
        $regExp .= '(?<closing>\\\[\)\]])'; // closing delimiter (or "\]" or "\)")
        $regExp .= '/Umsu'; // Ungreedy, multiline, dotall, unicode <= PLEASE NOTE UNICODE FLAG

        print_r($regExp);
        $content = preg_replace_callback(
            $regExp,
            function ($matches) {
                echo "\n================== MATCHES ==================\n";
                print_r($matches);
                return sprintf(
                    '<!--LATEXEQ %s%s%s -->',
                    $matches['opening'],
                    $matches['equation'],
                    $matches['closing']
                );
            },
            $content
        );

        echo "\n================== after preserveLatex ==================\n";
        print_r($content);
        echo "\n====================================================\n";


        return $content;
    }
    /**
     * @param string $content
     * @return string
     */
    protected function restoreLatex($content): string
    {
        echo "\n================== before restoreLatex ==================\n";
        print_r($content);
        echo "\n====================================================\n";


        // Comment out latex equations to avoid processing
        $regExp = '/';
        $regExp .= '<!--LATEXEQ'; // opening comment
        $regExp .= '(?<eqtext>.*)';
        $regExp .= '-->'; // closing comment
        $regExp .= '/Umsu'; // Ungreedy, multiline, dotall, unicode <= PLEASE NOTE UNICODE FLAG

        print_r($regExp);
        $content = preg_replace_callback(
            $regExp,
            function ($matches) {
                echo "\n================== MATCHES ==================\n";
                print_r($matches);
                return $matches['eqtext'];
            },
            $content
        );

        echo "\n================== after restoreLatex ==================\n";
        print_r($content);
        echo "\n====================================================\n";


        return $content;
    }

    /**
     *
     * @param string $content
     * @return string
     */
    protected function latexPreProcess($content): string
    {
        // Replace delimiters with the required HTML tags
        $content = str_replace(
            [
                '\(', // inline opening delimiter
                '\)', // inline closing delimiter
                '\[', // block opening delimiter
                '\]' // block closing delimiter
            ],
            [
                '<eq env="inlinemath">',
                '</eq>',
                '<span style="display:block; text-align:center;"><eq env="displaymath">',
                '</eq></span>'
            ],
            $content
        );

        return $content;
    }

    /**
     * @param $content
     * @return mixed
     */
    protected function latexPostProcess($content)
    {
        // Replace delimiters with the required HTML tags
        // $content = str_replace(
        //     [
        //         '&#92;(', // inline opening delimiter
        //         '&#92;)', // inline closing delimiter
        //         '&#92;[', // block opening delimiter
        //         '&#92;]' // block closing delimiter
        //     ],
        //     [
        //         '<eq env="inlinemath">',
        //         '</eq>',
        //         '<span style="display:block; text-align:center;"><eq env="displaymath">',
        //         '</eq></span>'
        //     ],
        //     $content
        // );

        // Replace delimiters with the required HTML tags
        $content = str_replace(
            [
                '\(', // inline opening delimiter
                '\)', // inline closing delimiter
                '\[', // block opening delimiter
                '\]' // block closing delimiter
            ],
            [
                '<eq env="inlinemath">',
                '</eq>',
                '<span class="displaymath"><eq env="displaymath">',
                '</eq></span>'
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

        $absolutePath = $this->app['app.dir.cache'] . '/latex_images/' . $this->app['publishing.book.slug'];
        return $filesystem->makePathRelative($absolutePath, getcwd());
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

        $fontSize = $this->getEditionOption('plugins.options.Latex.font_size', 12);

        echo "\n================== before command ==================\n";
        echo $content;
        echo "\n====================================================\n";

        // Run it
        $command = sprintf(
            '"%s" -o - --png -d "%s" -f %d %s -p "\usepackage{amsmath}" -',
            $gladtex,
            $imagesOutputDir,
            $fontSize,
            $gladtexOptions
        );

        $this->writeLn(sprintf('Running GladTeX with command: %s', $command));

        $process = new Process($command);
        $process->setInput($content);
        $process->run();
        $outputText = $process->getOutput();
        $errorText = $process->getErrorOutput();

        if (!$process->isSuccessful()) {
            $this->writeLn(sprintf('GladTeX errors detected: %s %s', $outputText, $errorText), 'error');
            return $content;
        }

        echo "\n================== after command ==================\n";
        echo $outputText;
        echo "\n====================================================\n";

        // Replace the generated image paths with "images/" (because the images will be copied there 
        // later in the publishing phase)
        $outputText = str_replace(
            $imagesOutputDir,
            'images/',
            $outputText
        );

        // Create the images directory in the contents directory if it does not exist
        $imagesContentLatexDir = $this->app['publishing.dir.contents'] . '/images/latex';
        if (!$filesystem->exists($imagesContentLatexDir)) {
            $filesystem->mkdir($imagesContentLatexDir);
        }

        // Copy the generated images to the contents images directory

        /** @var \Symfony\Component\Finder\Finder */
        $finder = $this->app['finder'];
        // Delete the latex content images first
        $contentLatexImages = $finder->files()->in($imagesContentLatexDir)->name(['eqn*.png', 'gladtex.cache']);
        $filesystem->remove($contentLatexImages);
        // Copy all the files
        $filesystem->mirror($imagesOutputDir, $imagesContentLatexDir);
        // Remove the gladtex cache file if it was copied over
        $gladtexCacheFile = $imagesContentLatexDir . '/gladtex.cache';
        if ($filesystem->exists($gladtexCacheFile)) {
            $filesystem->remove($gladtexCacheFile);
        }

        return $outputText;
    }

}
