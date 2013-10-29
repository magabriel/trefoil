<?php

namespace Trefoil\DependencyInjection;

use Easybook\DependencyInjection\Application as EasybookApplication;
use Trefoil\Util\Toolkit;
use Trefoil\Publishers\Epub2Publisher;
use Trefoil\Publishers\MobiPublisher;

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

        // -- own publisher ----------------------------------------------------
        $this['publisher'] = $this->share(function ($app) {
            $outputFormat = $app->edition('format');

            switch (strtolower($outputFormat)) {

                case 'epub':
                case 'epub2':
                    // use our epub2 publisher
                    $publisher = new Epub2Publisher($app);
                    break;

                case 'mobi':
                    // use our mobi publisher
                    $publisher = new MobiPublisher($app);
                    break;

                default:
                    // use the default publisher
                    $publisher = $app['publisher'];
            }

            $publisher->checkIfThisPublisherIsSupported();

            return $publisher;
        });
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
