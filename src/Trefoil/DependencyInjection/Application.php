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

namespace Trefoil\DependencyInjection;

use Easybook\DependencyInjection\Application as EasybookApplication;
use Trefoil\Providers\PublisherServiceProvider;
use Trefoil\Providers\TwigServiceProvider;
use Trefoil\Util\Toolkit;

/**
 * Class Application
 *
 * @package Trefoil\DependencyInjection
 */
class Application extends EasybookApplication
{
    private const MY_VERSION = '1.0';

    public function __construct()
    {
        parent::__construct();

        // -- global generic parameters ---------------------------------------
        $signature = <<<SIGNATURE
     _     _            __     _ _ 
   _| |_  | |_ _ _ ___ / _|___(_) |
  |_   _| |  _| '_/ -_)  _/ _ \ | |
    |_|    \__|_| \___|_| \___/_|_|
SIGNATURE;
        $this['app.signature'] = substr($this['app.signature'], 0, -1)."\n".$signature."\n";

        $this['app.debug'] = true;
        $this['app.debug.strict_variables'] = false;

        // -- global directories location -------------------------------------
        /** @noinspection RealpathInStreamContextInspection */
        $this['trefoil.app.dir.base'] = realpath(__DIR__.'/../../../');

        $this['app.dir.cache'] = '/tmp/trefoil';
        // Used to be: $this['app.dir.cache'] = $this['trefoil.app.dir.base'] . '/app/Cache';

        $this['app.dir.doc'] = $this['trefoil.app.dir.base'].'/doc';
        $this['trefoil.app.dir.resources'] = $this['trefoil.app.dir.base'].'/app/Resources';
        $this['trefoil.publishing.dir.themes'] = $this['trefoil.app.dir.resources'].'/Themes';

        // -- own services -----------------------------------------------------
        $this->register(new PublisherServiceProvider());
        $this->register(new TwigServiceProvider());

        // -- console ---------------------------------------------------------
        $this['console.progress'] = null;
    }

    /**
     * @return string
     */
    final public function getMyVersion(): string
    {
        return static::MY_VERSION;
    }

    /**
     * @see Application::getCustomLabelsFile()
     */
    public function getCustomLabelsFile(): ?string
    {
        $labelsFileName = 'labels.'.$this->book('language').'.yml';
        $labelsFile = parent::getCustomLabelsFile();

        // the file found has precedence
        if ($labelsFile) {
            return $labelsFile;
        }

        // look for a file inside the theme
        $themeLabelsFile = Toolkit::getCurrentResource($this, 'Translations/'.$labelsFileName);

        if ($themeLabelsFile) {
            return $themeLabelsFile;
        }

        return null;
    }

    /**
     * @see Application::getCustomTitlesFile()
     */
    public function getCustomTitlesFile(): ?string
    {
        $titlesFileName = 'titles.'.$this->book('language').'.yml';
        $titlesFile = parent::getCustomTitlesFile();

        // the file found has precedence
        if ($titlesFile) {
            return $titlesFile;
        }

        // look for a file inside the theme
        $themeTitlesFile = Toolkit::getCurrentResource($this, 'Translations/'.$titlesFileName);

        if ($themeTitlesFile !== null && file_exists($themeTitlesFile)) {
            return $themeTitlesFile;
        }

        return null;
    }

    /**
     * Transforms the original string into a web-safe slug. It also ensures that
     * the generated slug is unique for the entire book (to do so, it stores
     * every slug generated since the beginning of the script execution).
     *
     * @param string $string    The string to slug
     * @param string $separator Used between words and to replace illegal characters
     * @param string $prefix    Prefix to be appended at the beginning of the slug
     * @return string The generated slug
     */
    public function slugifyUniquely($string, $separator = null, $prefix = null): string
    {
        if (is_numeric($string[0])) {
            // epubcheck does not like ids starting with digit
            $string = 'tr_'.$string;
        }

        return parent::slugifyUniquely($string, $separator, $prefix);
    }

    /**
     * It loads the full book configuration by combining all the different sources
     * (config.yml file, console command option and default values). It also loads
     * the edition configuration and resolves the edition inheritance (if used).
     *
     * @param string $configurationViaCommand The configuration options provided via the console command
     */
    public function loadBookConfiguration($configurationViaCommand = ''): void
    {
        $config = $this['configurator']->loadBookConfiguration($this['publishing.dir.book'], $configurationViaCommand);
        $this['publishing.book.config'] = $config;

        // NEW in trefoil: treat "import" key as a list of directories where to look for config files
        if (isset($config['import'])) {
            $config = $this->loadImportedConfiguration($config['import']);
            // Note this is a "merge" and not a "replace"
            $this['publishing.book.config'] = array_merge_recursive($this['publishing.book.config'], $config);
        }

        $this['publishing.edition'] = $this['validator']->validatePublishingEdition($this['publishing.edition']);

        $config = $this['configurator']->loadEditionConfiguration();
        $this['publishing.book.config'] = $config;

        $config = $this['configurator']->processConfigurationValues();
        $this['publishing.book.config'] = $config;
    }

    /**
     * It loads the (optional) easybook configuration parameters defined by the book.
     */
    public function loadEasybookConfiguration(): void
    {
        $bookFileConfig = $this['configurator']->loadBookFileConfiguration($this['publishing.dir.book']);

        // NEW in trefoil: treat "import" key as a list of directories where to look for config files
        if (isset($bookFileConfig['import'])) {
            $config = $this->loadImportedConfiguration($bookFileConfig['import']);
            $bookFileConfig = array_replace_recursive($bookFileConfig, $config);
        }

        if (!isset($bookFileConfig['easybook'])) {
            return;
        }

        /** @var array[][] $bookFileConfig */
        /** @var string $option */
        foreach ($bookFileConfig['easybook']['parameters'] as $option => $value) {
            if (is_array($value)) {
                $previousArray = $this->offsetExists($option) ? $this[$option] : [];
                $newArray = array_merge($previousArray, $value);
                $this[$option] = $newArray;
            } else {
                $this[$option] = $value;
            }
        }
    }

    /**
     * Treat "import" key as a list of directories where to look for config files
     *
     * @param $directories
     * @return array
     */
    protected function loadImportedConfiguration($directories): array
    {
        $config = [];

        /** @var string[] $directories */
        foreach ($directories as $dir) {
            $importedConfigOne = $this['configurator']->loadBookFileConfiguration($dir);
            /** @noinspection SlowArrayOperationsInLoopInspection */
            $config = array_merge_recursive($config, $importedConfigOne);
        }

        return $config;
    }
}
