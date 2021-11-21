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

namespace Trefoil\Util;

use Easybook\DependencyInjection\Application as EasybookApplication;
use Easybook\Util\Toolkit as EasybookToolkit;

/**
 * Class Toolkit
 *
 * @package Trefoil\Util
 */
class Toolkit extends EasybookToolkit
{
    /**
     * @param array|EasybookApplication $app
     * @return null|string
     */
    public static function getCurrentThemeDir(EasybookApplication $app): ?string
    {
        $theme = ucfirst($app->edition('theme'));

        // the custom theme set for this book publishing
        $localThemesDir = realpath($app['trefoil.publishing.dir.themes']);

        // the default trefoil themes
        $defaultThemesDir = $app['trefoil.app.dir.resources'].'/Themes';

        $paths = [
            $localThemesDir,
            $defaultThemesDir,
        ];

        return $app->getFirstExistingFile($theme, $paths);
    }

    /**
     * Return the current resources dir.
     *
     * @param EasybookApplication $app
     * @param null                $format
     * @return null|string
     */
    public static function getCurrentResourcesDir(EasybookApplication $app,
                                                                      $format = null): ?string
    {
        $paths = self::getCurrentResourceDirs($app, $format);

        $existing = $app->getFirstExistingFile('', $paths);
        if ($existing !== null) {
            $existing = (substr($existing, -1) === '/') ? substr($existing, 0, -1) : $existing;
        }

        return $existing;
    }

    /**
     * Return the list of current resource directories, ordered by precedence.
     *
     * @param EasybookApplication $app
     * @param null                $format
     * @return array
     */
    public static function getCurrentResourceDirs(EasybookApplication $app,
                                                                      $format = null): array
    {
        $theme = ucfirst($app->edition('theme'));

        if (!$format) {
            $format = self::getCurrentFormat($app);
        }

        // the custom theme set for this book and format
        $localResourcesDir = realpath($app['trefoil.publishing.dir.themes']).'/'.$theme.'/'.$format;

        // the custom theme set for this book and the Common format
        $localCommonResourcesDir = realpath($app['trefoil.publishing.dir.themes']).'/'.$theme.'/Common';

        // the default trefoil themes for the format
        $defaultResourcesDir = $app['trefoil.app.dir.resources'].'/Themes'.'/'.$theme.'/'.$format;

        // the Common format into the default trefoil themes
        $defaultCommonResourcesDir = $app['trefoil.app.dir.resources'].'/Themes'.'/'.$theme.'/Common';

        $paths = [
            $localResourcesDir.'/Resources',
            $localCommonResourcesDir.'/Resources',
            $defaultResourcesDir.'/Resources',
            $defaultCommonResourcesDir.'/Resources',
        ];

        return $paths;
    }

    /**
     * @param EasybookApplication $app
     * @return string
     */
    public static function getCurrentFormat(EasybookApplication $app): string
    {
        return self::camelize($app->edition('format'), true);
    }

    /**
     * Return first existing resource by name.
     *
     * @param EasybookApplication $app
     * @param                     $resourceName
     * @param null                $format
     * @return null|string
     */
    public static function getCurrentResource(EasybookApplication $app,
                                                                  $resourceName,
                                                                  $format = null): ?string
    {
        $paths = self::getCurrentResourceDirs($app, $format);

        return $app->getFirstExistingFile($resourceName, $paths);
    }

    /**
     * @param $format
     * @return string
     */
    public static function sprintfUTF8($format): string
    {
        $args = func_get_args();

        $count = count($args);
        for ($i = 1; $i < $count; $i++) {
            $args[$i] = iconv('UTF-8', 'ISO-8859-15', $args[$i]);
        }

        return iconv('ISO-8859-15', 'UTF-8', sprintf(...$args));
    }

    /**
     * Extract the attributes of an HTML tag
     *
     * @param string $string
     * @return array of attributes
     */
    public static function parseHTMLAttributes($string): array
    {
        $regExp = '/(?<attr>.*)="(?<value>.*)"/Us';
        /** @var string[][] $attrMatches */
        preg_match_all($regExp, $string, $attrMatches, PREG_SET_ORDER);

        $attributes = [];
        foreach ($attrMatches as $attrMatch) {
            $attributes[trim($attrMatch['attr'])] = $attrMatch['value'];
        }

        return $attributes;
    }

    /**
     * Render the HTML tag
     *
     * @param string $tag
     * @param string $contents
     * @param array  $attributes
     * @return string
     */
    public static function renderHTMLTag($tag,
        $contents = '',
                                         array $attributes = []): string
    {
        $strAttributes = static::renderHTMLAttributes($attributes);
        $strAttributes = $strAttributes ? ' '.$strAttributes : '';

        if ($contents) {
            return sprintf('<%s%s>%s</%s>', $tag, $strAttributes, $contents, $tag);
        }

        return sprintf('<%s%s />', $tag, $strAttributes);
    }

    /**
     * Render the attributes of an HTML tag
     *
     * @param array $attributes
     * @return string
     */
    public static function renderHTMLAttributes(array $attributes): string
    {
        $html = '';

        foreach ($attributes as $name => $value) {
            $html .= sprintf('%s="%s" ', $name, $value);
        }

        return trim($html);
    }

    /**
     * Return true if the $haystack starts with the $needle,
     *
     * @param $haystack
     * @param $needle
     * @return bool
     */
    public static function stringStartsWith($haystack,
                                            $needle): bool
    {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }

    /**
     * Multi-byte sttrev.
     *
     * @param string $str
     * @return string
     */
    public static function mb_strrev(string $str): string
    {
        $r = '';
        for ($i = mb_strlen($str); $i >= 0; $i--) {
            $r .= mb_substr($str, $i, 1);
        }

        return $r;
    }

}
