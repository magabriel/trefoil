<?php
/*
 * This file is part of the trefoil application.
 *
 * (c) Miguel Angel Gabriel <magabriel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Trefoil\Util;

use Easybook\DependencyInjection\Application as EasybookApplication;
use Easybook\Util\Toolkit as EasybookToolkit;

class Toolkit extends EasybookToolkit
{
    /**
     * @param array|EasybookApplication $app
     *
     * @return null|string
     */
    public static function getCurrentThemeDir(EasybookApplication $app)
    {
        $theme = ucfirst($app->edition('theme'));

        // the custom theme set for this book publishing
        $localThemesDir = realpath($app['trefoil.publishing.dir.themes']);

        // the default trefoil themes
        $defaultThemesDir = $app['trefoil.app.dir.resources'] . '/Themes';

        $paths = array(
            $localThemesDir,
            $defaultThemesDir
        );

        $existing = $app->getFirstExistingFile($theme, $paths);

        return $existing;
    }

    /**
     * Return the current resources dir.
     * 
     * @param EasybookApplication $app
     * @param null                $format
     *
     * @return null|string
     */
    public static function getCurrentResourcesDir(EasybookApplication $app, $format = null)
    {
        $paths = self::getCurrentResourceDirs($app, $format);
        
        $existing = $app->getFirstExistingFile('', $paths);
        $existing = (substr($existing, -1) === '/') ? substr($existing, 0, -1) : $existing;
        
        return $existing;
    }

    /**
     * Return first existing resource by name.
     * 
     * @param EasybookApplication $app
     * @param                     $resourceName
     * @param null                $format
     *
     * @return null|string
     */
    public static function getCurrentResource(EasybookApplication $app, $resourceName, $format = null)
    {
        $paths = self::getCurrentResourceDirs($app, $format);

        $existing = $app->getFirstExistingFile($resourceName, $paths);

        return $existing;
    }

    /**
     * Return the list of current resource directories, ordered by precedence.
     *
     * @param EasybookApplication $app
     * @param null                $format
     *
     * @return array
     */
    public static function getCurrentResourceDirs(EasybookApplication $app, $format = null)
    {
        $theme = ucfirst($app->edition('theme'));

        if (!$format) {
            $format = self::getCurrentFormat($app);
        }
        
        // the custom theme set for this book and format 
        $localResourcesDir = realpath($app['trefoil.publishing.dir.themes']) . '/' . $theme . '/' . $format;

        // the custom theme set for this book and the Common format
        $localCommonResourcesDir = realpath($app['trefoil.publishing.dir.themes']) . '/' . $theme . '/Common';

        // the default trefoil themes for the format
        $defaultResourcesDir = $app['trefoil.app.dir.resources'] . '/Themes' . '/' . $theme . '/' . $format;

        // the Common format into the default trefoil themes
        $defaultCommonResourcesDir = $app['trefoil.app.dir.resources'] . '/Themes' . '/' . $theme . '/Common';

        $paths = array(
            $localResourcesDir . '/Resources',
            $localCommonResourcesDir . '/Resources',
            $defaultResourcesDir . '/Resources',
            $defaultCommonResourcesDir . '/Resources'
        );
        
        return $paths;
    }

    public static function getCurrentFormat(EasybookApplication $app)
    {
        $format = Toolkit::camelize($app->edition('format'), true);

        return $format;
    }

    public static function sprintfUTF8($format)
    {
        $args = func_get_args();

        $count = count($args);
        for ($i = 1; $i < $count; $i++) {
            $args[$i] = iconv('UTF-8', 'ISO-8859-15', $args[$i]);
        }

        return iconv('ISO-8859-15', 'UTF-8', call_user_func_array('sprintf', $args));
    }

    /**
     * Extract the attributes of an HTML tag
     *
     * @param string $string
     *
     * @return array of attributes
     */
    public static function parseHTMLAttributes($string)
    {
        $regExp = '/(?<attr>.*)="(?<value>.*)"/Us';
        preg_match_all($regExp, $string, $attrMatches, PREG_SET_ORDER);

        $attributes = array();
        foreach ($attrMatches as $attrMatch) {
            $attributes[trim($attrMatch['attr'])] = $attrMatch['value'];
        }

        return $attributes;
    }

    /**
     * Render the attributes of an HTML tag
     *
     * @param array $attributes
     *
     * @return string
     */
    public static function renderHTMLAttributes(array $attributes)
    {
        $html = '';

        foreach ($attributes as $name => $value) {
            $html .= sprintf('%s="%s" ', $name, $value);
        }

        return trim($html);
    }

    /**
     * Render the HTML tag
     *
     * @param string $tag
     * @param string $contents
     * @param array  $attributes
     *
     * @return string
     */
    public static function renderHTMLTag($tag, $contents = '', array $attributes = array())
    {
        $strAttributes = static::renderHTMLAttributes($attributes);
        $strAttributes = $strAttributes ? ' ' . $strAttributes : '';

        if ($contents) {
            return sprintf('<%s%s>%s</%s>', $tag, $strAttributes, $contents, $tag);
        }

        return sprintf('<%s%s />', $tag, $strAttributes);
    }

    /**
     * Return true if the $haystack starts with the $needle,
     *
     * @param $haystack
     * @param $needle
     * @return bool
     */
    public static function stringStartsWith($haystack, $needle) {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }

}
