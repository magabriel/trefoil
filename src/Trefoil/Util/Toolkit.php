<?php
namespace Trefoil\Util;

use Easybook\DependencyInjection\Application as EasybookApplication;
use Easybook\Util\Toolkit as EasybookToolkit;

class Toolkit extends EasybookToolkit
{
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

    public static function getCurrentResourcesDir(EasybookApplication $app, $format = null)
    {
        $theme = ucfirst($app->edition('theme'));

        if (!$format) {
            $format = Toolkit::getCurrentFormat($app);
        }

        // the custom theme set for this book publishing
        $localResourcesDir = realpath($app['trefoil.publishing.dir.themes']) . '/' . $theme . '/' . $format;

        // the default trefoil themes for the format
        $defaultResourcesDir = $app['trefoil.app.dir.resources'] . '/Themes' . '/' . $theme . '/' . $format;

        // the Common format into the default trefoil themes
        $defaultCommonResourcesDir = $app['trefoil.app.dir.resources'] . '/Themes' . '/' . $theme . '/Common';

        $paths = array(
            $localResourcesDir,
            $defaultResourcesDir,
            $defaultCommonResourcesDir
        );

        $existing = $app->getFirstExistingFile('Resources', $paths);

        return $existing;
    }

    public static function getCurrentFormat(EasybookApplication $app)
    {
        $format = Toolkit::camelize($app->edition('format'), true);

        return $format;
    }

    public static function sprintfUTF8($format)
    {
        $args = func_get_args();

        for ($i = 1; $i < count($args); $i++) {
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

}
