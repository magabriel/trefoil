<?php

namespace Trefoil\DependencyInjection;

use Easybook\DependencyInjection\Application as EasybookApplication;
use Trefoil\Providers\PublisherServiceProvider;
use Trefoil\Providers\TwigServiceProvider;
use Trefoil\Util\Toolkit;

class Application extends EasybookApplication
{
    const MY_VERSION = '0.2 DEV';

    public function __construct()
    {
        parent::__construct();

        // -- global generic parameters ---------------------------------------
        $this['app.signature'] = substr($this['app.signature'], 0, -1) . "\n" .
            "     _     _            __     _ _ \n" .
            "   _| |_  | |_ _ _ ___ / _|___(_) |\n" .
            "  |_   _| |  _| '_/ -_)  _/ _ \ | |\n" .
            "    |_|    \__|_| \___|_| \___/_|_|\n";

        // -- global directories location -------------------------------------
        $this['trefoil.app.dir.base'] = realpath(__DIR__ . '/../../../');
        $this['app.dir.cache'] = $this['trefoil.app.dir.base'] . '/app/Cache';
        $this['app.dir.doc'] = $this['trefoil.app.dir.base'] . '/doc';
        $this['trefoil.app.dir.resources'] = $this['trefoil.app.dir.base'] . '/app/Resources';
        $this['trefoil.publishing.dir.themes'] = $this['trefoil.app.dir.resources'] . '/Themes';

        // -- own services -----------------------------------------------------
        $this->register(new PublisherServiceProvider());
        $this->register(new TwigServiceProvider());
    }

    final public function getMyVersion()
    {
        return static::MY_VERSION;
    }

    /**
     * @see Application::getCustomLabelsFile()
     */
    public function getCustomLabelsFile()
    {
        $labelsFileName = 'labels.' . $this->book('language') . '.yml';
        $labelsFile = parent::getCustomLabelsFile();

        // the file found has precedence
        if ($labelsFile) {
            return $labelsFile;
        }

        // look for a file inside the theme
        $themeLabelsDir = Toolkit::getCurrentResourcesDir($this, $this->edition('format')) . '/Translations';
        $themeLabelsFile = $themeLabelsDir . '/' . $labelsFileName;

        if (file_exists($themeLabelsFile)) {
            return $themeLabelsFile;
        }

        return '';
    }

    /**
     * @see Application::getCustomTitlesFile()
     */
    public function getCustomTitlesFile()
    {
        $titlesFileName = 'titles.' . $this->book('language') . '.yml';
        $titlesFile = parent::getCustomTitlesFile();

        // the file found has precedence
        if ($titlesFile) {
            return $titlesFile;
        }

        // look for a file inside the theme
        $themeTitlesDir = Toolkit::getCurrentResourcesDir($this, $this->edition('format')) . '/Translations';
        $themeTitlesFile = $themeTitlesDir . '/' . $titlesFileName;

        if (file_exists($themeTitlesFile)) {
            return $themeTitlesFile;
        }

        return '';
    }

}
