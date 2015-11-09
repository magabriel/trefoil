<?php
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

class Application extends EasybookApplication
{
    const MY_VERSION = '1.0-DEV';

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
        $this['app.signature'] = substr($this['app.signature'], 0, -1) . "\n" . $signature . "\n";

        $this['app.debug'] = true;
        $this['app.debug.strict_variables'] = false;

        // -- global directories location -------------------------------------
        $this['trefoil.app.dir.base'] = realpath(__DIR__ . '/../../../');
        $this['app.dir.cache'] = $this['trefoil.app.dir.base'] . '/app/Cache';
        $this['app.dir.doc'] = $this['trefoil.app.dir.base'] . '/doc';
        $this['trefoil.app.dir.resources'] = $this['trefoil.app.dir.base'] . '/app/Resources';
        $this['trefoil.publishing.dir.themes'] = $this['trefoil.app.dir.resources'] . '/Themes';

        // -- own services -----------------------------------------------------
        $this->register(new PublisherServiceProvider());
        $this->register(new TwigServiceProvider());

        // -- console ---------------------------------------------------------
        $this['console.progress'] = null;
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
        $themeLabelsFile = Toolkit::getCurrentResource($this, 'Translations/' . $labelsFileName);

        if ($themeLabelsFile) {
            return $themeLabelsFile;
        }

        return null;
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
        $themeTitlesFile = Toolkit::getCurrentResource($this, 'Translations/' . $titlesFileName);

        if (file_exists($themeTitlesFile)) {
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
     *
     * @return string The generated slug
     */
    public function slugifyUniquely($string, $separator = null, $prefix = null)
    {
        if (is_numeric(substr($string, 0, 1))) {
            // epubcheck does not like ids starting with digit
            $string = 'tr_' . $string;
        }

        return parent::slugifyUniquely($string, $separator, $prefix);
    }
}
