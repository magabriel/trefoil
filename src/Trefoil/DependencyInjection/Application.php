<?php

namespace Trefoil\DependencyInjection;

use Trefoil\Util\Toolkit;

use Easybook\DependencyInjection\Application as EasybookApplication;

class Application extends EasybookApplication
{
    const MY_VERSION = '0.1 DEV';

    public function __construct()
    {
        parent::__construct();

        // -- global generic parameters ---------------------------------------
        $this['app.signature'] = substr($this['app.signature'], 0, -1)
            ."  <comment>+ trefoil</comment>\n";

        // -- global directories location -------------------------------------
        $this['trefoil.app.dir.base']      = realpath(__DIR__.'/../../../');
        $this['app.dir.cache']             = $this['trefoil.app.dir.base'].'/app/Cache';
        $this['app.dir.doc']               = $this['trefoil.app.dir.base'].'/doc';
        $this['trefoil.app.dir.resources'] = $this['trefoil.app.dir.base'].'/app/Resources';
    }

    public final function getMyVersion()
    {
        return static::MY_VERSION;
    }

    /**
     * @see \Easybook\DependencyInjection\Application::getCustomLabelsFile()
     */
    public function getCustomLabelsFile()
    {
        $labelsFileName = 'labels.'.$this->book('language').'.yml';
        $labelsFile = parent::getCustomLabelsFile();

        // the file found has precedence
        if ($labelsFile) {
            return $labelsFile;
        }

        // look for a file inside the theme
        $themeLabelsDir = Toolkit::getCurrentResourcesDir($this, $this->edition('format')).'/Translations';
        $themeLabelsFile = $themeLabelsDir . '/' . $labelsFileName;

        if (file_exists($themeLabelsFile)) {
            return $themeLabelsFile;
        }

        return '';
    }

    /**
     * @see \Easybook\DependencyInjection\Application::getCustomTitlesFile()
     */
    public function getCustomTitlesFile()
    {
        $titlesFileName = 'titles.'.$this->book('language').'.yml';
        $titlesFile = parent::getCustomTitlesFile();

        // the file found has precedence
        if ($titlesFile) {
            return $titlesFile;
        }

        // look for a file inside the theme
        $themeTitlesDir = Toolkit::getCurrentResourcesDir($this, $this->edition('format')).'/Translations';
        $themeTitlesFile = $themeTitlesDir . '/' . $titlesFileName;

        if (file_exists($themeTitlesFile)) {
            return $themeTitlesFile;
        }

        return '';
    }

}
