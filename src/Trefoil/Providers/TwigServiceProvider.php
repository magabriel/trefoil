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

namespace Trefoil\Providers;

use Easybook\DependencyInjection\Application;
use Easybook\Util\TwigCssExtension;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Trefoil\Util\Toolkit;

/**
 * Class TwigServiceProvider
 *
 * @package Trefoil\Providers
 */
class TwigServiceProvider implements ServiceProviderInterface
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * @param Container $app
     */
    public function register(Container $app): void
    {
        $app['twig.options'] = [
            'autoescape'       => false,
            // 'cache'         => $app['app.dir.cache'].'/Twig',
            'charset'          => $app['app.charset'],
            'debug'            => $app['app.debug'],
            'strict_variables' => $app['app.debug.strict_variables'],
        ];

        $app['twig.loader'] =
            function () use ($app) {

                /** @var Application $app */
                $theme = ucfirst($app->edition('theme'));
                $format = Toolkit::camelize($app->edition('format'), true);

                $loader = new \Twig_Loader_Filesystem($app['app.dir.themes']);

                // easybook base configuration:
                // Base theme (common styles per edition type)
                // <easybook>/app/Resources/Themes/Base/<edition-type>/Templates/<template-name>.twig
                $baseThemeDir = sprintf('%s/Base/%s/Templates', $app['app.dir.themes'], $format);
                $loader->addPath($baseThemeDir);
                $loader->addPath($baseThemeDir, 'theme');
                $loader->addPath($baseThemeDir, 'theme_base');

                // easybook base configuration:
                // Book theme (configured per edition in 'config.yml')
                // <easybook>/app/Resources/Themes/<theme>/<edition-type>/Templates/<template-name>.twig
                $bookThemeDir =
                    sprintf('%s/%s/%s/Templates', $app['app.dir.themes'], $theme, $format);
                if (file_exists($bookThemeDir)) {
                    $loader->prependPath($bookThemeDir);
                    $loader->prependPath($bookThemeDir, 'theme');
                }

                // look if we have a custom theme set
                $themesDir = Toolkit::getCurrentThemeDir($app);
                if ($themesDir !== null && file_exists($themesDir)) {
                    // Theme common (common styles per edition theme)
                    // <themes-dir>/Common/Templates/<template-name>.twig
                    $baseThemeDir = sprintf('%s/Common/Templates', $themesDir);
                    $loader->prependPath($baseThemeDir);
                    $loader->prependPath($baseThemeDir, 'theme');
                    $loader->prependPath($baseThemeDir, 'theme_common');

                    // Register template paths
                    $ownTemplatePaths = [
                        // <themes-dir>/<edition-type>/Templates/<template-name>.twig
                        sprintf('%s/%s/Templates', $themesDir, $format),
                        // <themes-dir>/<edition-name>/Templates/<template-name>.twig
                        sprintf('%s/%s/Templates', $themesDir, $app['publishing.edition']),
                    ];
                    
                    foreach ($ownTemplatePaths as $path) {
                        if (file_exists($path)) {
                            $loader->prependPath($path);
                            $loader->prependPath($path, 'theme');
                        }
                    }
                }

                $userTemplatePaths = [
                    // <book-dir>/Resources/Templates/<template-name>.twig
                    $app['publishing.dir.templates'],
                    // <book-dir>/Resources/Templates/<edition-type>/<template-name>.twig
                    sprintf('%s/%s', $app['publishing.dir.templates'], $format),
                    // <book-dir>/Resources/Templates/<edition-name>/<template-name>.twig
                    sprintf(
                        '%s/%s',
                        $app['publishing.dir.templates'],
                        $app['publishing.edition']
                    ),
                ];

                foreach ($userTemplatePaths as $path) {
                    if (file_exists($path)) {
                        $loader->prependPath($path);
                    }
                }

                // Register content paths
                if ($themesDir !== null && file_exists($themesDir)) {
                    $ownContentPaths = [
                        // <themes-dir>/Common/Contents/<template-name>.md
                        sprintf('%s/Common/Contents', $themesDir),
                        // <themes-dir>/<edition-type>/Contents/<template-name>.md
                        sprintf('%s/%s/Contents', $themesDir, $format)
                    ];

                    foreach ($ownContentPaths as $path) {
                        if (file_exists($path)) {
                            $loader->prependPath($path, 'content');
                        }
                    }
                }

                return $loader;
            };

        $app['twig'] =
            function () use ($app) {
                /* @var Application $app */

                $twig = new \Twig_Environment($app['twig.loader'], $app['twig.options']);
                $twig->addExtension(new TwigCssExtension());
                $twig->addExtension(new \Twig_Extension_Debug());

                $twig->addGlobal('app', $app);

                if (null !== $bookConfig = $app['publishing.book.config']) {
                    $twig->addGlobal('book', $bookConfig['book']);

                    $publishingEdition = $app['publishing.edition'];
                    $editions = $app->book('editions');
                    $twig->addGlobal('edition', $editions[$publishingEdition]);
                }

                return $twig;
            };
    }
}
