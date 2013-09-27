<?php
namespace Trefoil\Util;

use Easybook\Util\Toolkit as EasybookToolkit;
use Easybook\DependencyInjection\Application;

class Toolkit extends EasybookToolkit
{
    public static function getCurrentThemeDir(Application $app)
    {
        $theme = ucfirst($app->edition('theme'));
        $themeDir = realpath($app['trefoil.publishing.dir.themes']) .'/'.$theme;
        return $themeDir;
    }

    public static function getCurrentResourcesDir(Application $app, $format)
    {
        $theme = ucfirst($app->edition('theme'));
        $edition = $app['publishing.edition'];
        $format = Toolkit::getCurrentFormat($app);

        $resourcesDir = realpath($app['trefoil.publishing.dir.themes']) .'/'.$theme.'/'.$format.'/Resources';
        return $resourcesDir;
    }

    public static function getCurrentFormat(Application $app)
    {
        $format = Toolkit::camelize($app->edition('format'), true);

        // TODO: fix the following hack
        if ('Epub' == $format) {
            $format = 'Epub2';
        }

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
}