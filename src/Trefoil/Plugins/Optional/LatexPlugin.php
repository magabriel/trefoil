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
use FilesystemIterator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Trefoil\Events\TrefoilEvents;
use Trefoil\Plugins\BasePlugin;
use Symfony\Component\Process\Process;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 */
class LatexPlugin extends BasePlugin implements EventSubscriberInterface
{
    protected Filesystem $filesystem;
    protected Finder $finder;

    public function init(BaseEvent $event): void
    {
        parent::init($event);
        $this->filesystem = $this->app['filesystem'];
        $this->finder = $this->app['finder'];
    }

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

    public function onPrePublish(BaseEvent $event): void
    {
        $this->init($event);

        // Check if we have a path for gladtex
        $gladtex = $this->getConfigOption('easybook.parameters.gladtex.path');

        if (!$gladtex || !file_exists($gladtex)) {
            throw new \RuntimeException(
                'The GladTeX utility needed to process LaTeX in documents cannot be found. ' .
                'Check that you have set your custom GladTeX path in the book\'s config.yml file.'
            );
        }

        // Delete the image cache directory if it exists
        $imagesOutputDir = $this->getLatexCacheDir();

        // Remove the cache directory if it exists
        if ($this->filesystem->exists($imagesOutputDir)) {
            $this->filesystem->remove($imagesOutputDir);
        }
    }

    /**
     * @param ParseEvent $event
     */
    public function onItemPreParse(ParseEvent $event): void
    {
        $this->init($event);

        $content = $event->getItemProperty('original');

        $content = $this->preserveLatex($content);

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

        $content = $this->latexProcess($content);

        $this->item['content'] = $content;
        $event->setItem($this->item);
    }

    /**
     * Preserve LaTeX equations by commenting them out.
     */
    protected function preserveLatex(string $content): string
    {
        $regExp = '/';
        $regExp .= '(?<opening>\\\[\(\[])'; // opening delimiter ("\[" or "\(")
        $regExp .= '(?<equation>.*)';
        $regExp .= '(?<closing>\\\[\)\]])'; // closing delimiter ("\]" or "\)")
        $regExp .= '/Umsu'; // Ungreedy, multiline, dotall, unicode <= PLEASE NOTE UNICODE FLAG

        $content = preg_replace_callback(
            $regExp,
            function ($matches) {
                return sprintf(
                    '<!--LATEXEQ %s%s%s -->',
                    $matches['opening'],
                    $matches['equation'],
                    $matches['closing']
                );
            },
            $content
        );

        return $content;
    }
    /**
     * Restore LaTeX equations by removing the comments.
     */
    protected function restoreLatex(string $content): string
    {
        $regExp = '/';
        $regExp .= '<!--LATEXEQ'; // comment opening
        $regExp .= '(?<eqtext>.*)';
        $regExp .= '-->'; // comment closing
        $regExp .= '/Umsu'; // Ungreedy, multiline, dotall, unicode <= PLEASE NOTE UNICODE FLAG

        $content = preg_replace_callback(
            $regExp,
            function ($matches) {
                return $matches['eqtext'];
            },
            $content
        );

        return $content;
    }

    /**
     * Transform LaTeX equations into HTML.
     */
    protected function latexProcess(string $content): string
    {
        // Replace delimiters with the required HTML tags for gladtex
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

        // And now process the LaTeX equations
        $content = $this->processLatexFormulas($content);

        return $content;
    }
    /**
     * Return the path to the LaTeX cache directory for this book.
     */
    protected function getLatexCacheDir(): string
    {
        // gdlatex needs an images dir path relative to the current working directory

        $absolutePath = $this->app['app.dir.cache'] . '/latex_images/' . $this->app['publishing.book.slug'];
        return $this->filesystem->makePathRelative($absolutePath, getcwd());
    }
    /**
     * Process the LaTeX formulas in the content using GladTeX.
     */
    protected function processLatexFormulas(string $content): string
    {
        // Get the path for gladtex
        $gladtex = $this->getConfigOption('easybook.parameters.gladtex.path');
        $gladtexOptions = $this->getConfigOption('easybook.parameters.gladtex.command_options');

        // gdlatex needs an images dir path relative to the current working directory
        $imagesOutputDir = $this->getLatexCacheDir();

        $fontSize = $this->getEditionOption('plugins.options.Latex.font_size', 12);

        // Run it
        $command = sprintf(
            '"%s" -o - --png -R -d "%s" -f %d -p "\usepackage{amsmath}" %s -',
            $gladtex,
            $imagesOutputDir,
            $fontSize,
            $gladtexOptions
        );

        // $this->writeLn(sprintf('Running GladTeX with command: %s', $command));

        $process = new Process($command);
        $process->setInput($content);
        $process->run();
        $outputText = $process->getOutput();
        $errorText = trim($process->getErrorOutput());

        // echo "\n=== output ========================\n";
        // print_r($outputText);
        // echo "\n=== error ========================\n";
        // print ($errorText);
        // echo "\n=== end ==========================\n";

        $errorText = $this->filterGladtexErrors($errorText);

        if (!$process->isSuccessful() || !empty($errorText)) {
            $this->writeLn(sprintf("GladTeX errors detected: \n%s", $errorText), 'error');
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
        $imagesContentLatexDir = $this->app['publishing.dir.contents'] . '/images';
        if (!$this->filesystem->exists($imagesContentLatexDir)) {
            $this->filesystem->mkdir($imagesContentLatexDir);
        }

        // Copy the generated images to the contents images directory

        // Delete the latex content images first
        $contentLatexImages = $this->finder->files()->in($imagesContentLatexDir)->name('eqn*.png');
        $this->filesystem->remove($contentLatexImages);

        // Copy all the files
        if ($this->filesystem->exists($imagesOutputDir)) {
            $this->filesystem->mirror($imagesOutputDir, $imagesContentLatexDir);
        }

        // Remove gladtext-generated files that could have been copied over
        $this->filesystem->remove([
            $imagesContentLatexDir . '/gladtex.cache',
            $imagesContentLatexDir . '/outsourced-descriptions.html'
        ]);

        return $outputText;
    }

    /**
     * Filter out bug output lines
     */
    protected function filterGladtexErrors(string $errorText): string
    {
        $lines = explode(PHP_EOL, $errorText);

        // GladText bug: we need to detect a possible ConversionException message
        $line = preg_grep('/gleetex\.cachedconverter\.ConversionException/', $lines);
        if (is_array($line) && count($line) > 0) {
            $errorText = implode(PHP_EOL, $line) . PHP_EOL;
            return $errorText;
        }

        $errorText = '';
        // Python bug: we need to filter out some warnings
        foreach ($lines as $line) {
            if (
                trim($line) !== '' &&
                !str_contains($line, 'RuntimeWarning') &&
                !str_contains($line, 'bufsize')
            ) {
                $errorText .= $line . PHP_EOL;
            }
        }

        return $errorText;
    }
}
