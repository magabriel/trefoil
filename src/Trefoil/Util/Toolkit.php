<?php
namespace Trefoil\Util;

use Easybook\Util\Toolkit as EasybookToolkit;
use Easybook\DependencyInjection\Application;

class Toolkit extends EasybookToolkit
{
    public static function getCurrentThemeDir(Application $app)
    {
        $theme = ucfirst($app->edition('theme'));       
        $themeDir = realpath(__DIR__ . '/../../../app/Resources/Themes/' . $theme);
        
        return $themeDir;
    }
}