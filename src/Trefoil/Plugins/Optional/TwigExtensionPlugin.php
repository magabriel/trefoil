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

namespace Trefoil\Plugins\Optional;

use Easybook\Events\EasybookEvents;
use Easybook\Events\ParseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Trefoil\Plugins\BasePlugin;
use Trefoil\Util\Toolkit;

/**
 * This plugin extends Twig to provide some useful functionalities:
 * <li>
 * <b>Use configuration options in book contents</b> (instead of only in templates).
 * For example, in "chapter1.md" you can write "The title is {{ book.title }}".
 * This is mainly useful with user-defined configuration options.
 * <li>
 * <b>itemtoc()</b> function automatically generates the Table of Contents of the current item.
 * The deep of the itemtoc can be tweaked by the configuration option <i>edition.itemtoc.deep</i>,
 * which defaults to one less than the main TOC deep <i>edition.toc.deep</i>.
 * It uses de <i>itemtoc.twig</i> template that must be available either as local or global template.
 * <li>
 * <b>file()</b> function (deprecated, use fragment()) works like PHP's <i>include()</i>,
 * allowing the inclusion of another file into the current item.
 * The syntax is <i>file(filename, variables, options)</i> where "variables" and "options" are
 * optional hash tables where you can pass variables {'variable': 'value'} or
 * options {'nopagebreak': true} to the included file.
 * <li>
 * <b>fragment()</b> function works like PHP's <i>include()</i>, allowing the inclusion of
 * another file into the current item.
 * The syntax is <i>fragment(filename, variables, options)</i> where "variables" and "options" are
 * optional hash tables where you can pass variables {'variable': 'value'} or
 * options {'pagebreak': true} to cause inserting a page break after the included fragment.
 */
class TwigExtensionPlugin extends BasePlugin implements EventSubscriberInterface
{
    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EasybookEvents::PRE_PARSE  => 'onItemPreParse',
            EasybookEvents::POST_PARSE => ['onItemPostParse', -1010],
        ];
    }

    /**
     * @param ParseEvent $event
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function onItemPreParse(ParseEvent $event)
    {
        $this->init($event);

        $content = $event->getItemProperty('original');

        // replace "{#" to avoid problems with markdown extra syntax for ids in headers
        $content = str_replace('{#', '@%@&', $content);

        // replace configuration options on PreParse to take care of normal replacements
        // and the first pass of "itemtoc()"
        $content = $this->renderString($content);

        # replace back "{#"
        $content = str_replace('@%@&', '{#', $content);

        $event->setItemProperty('original', $content);
    }

    /**
     * @param ParseEvent $event
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function onItemPostParse(ParseEvent $event)
    {
        $this->init($event);

        $content = $event->getItemProperty('content');

        // ensure the Twig function call is not enclosed into a '<p>..</p>' tag
        // as it will result in epub checking erros
        $content = preg_replace('/<p>\s*{{/', '{{', $content);
        $content = preg_replace('/}}\s*<\/p>/', '}}', $content);

        // replace "{#" to avoid problems with markdown extra syntax for ids in headers
        $content = str_replace('{#', '@%@&', $content);

        // replace also in PostParse to process the second pass of "itemtoc()" ("itemtoc_internal()")
        $content = $this->renderString($content);

        # replace back "{#"
        $content = str_replace('@%@&', '{#', $content);

        $event->setItemProperty('content', $content);
    }

    /**
     * @param       $string
     * @param array $variables
     * @return string
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    protected function renderString($string,
                                    $variables = []): string
    {
        // we need a new Twig String Renderer environment
        $twig = new \Twig_Environment(new \Twig_Loader_String(), $this->app['twig.options']);

        $this->addTwigGlobals($twig);
        $this->registerExtensions($twig);

        return $twig->render($string, $variables);
    }

    /**
     * @param \Twig_Environment $twig
     */
    protected function addTwigGlobals(\Twig_Environment $twig)
    {
        $twig->addGlobal('app', $this->app);

        if (null !== $this->app['publishing.book.config']['book']) {
            $twig->addGlobal('book', $this->app['publishing.book.config']['book']);

            $publishingEdition = $this->edition;
            $editions = $this->app->book('editions');
            $twig->addGlobal('edition', $editions[$publishingEdition]);
        }

        $twig->addGlobal('item', $this->item);
    }

    /**
     * Register all the extensions here.
     *
     * @param \Twig_Environment $twig
     */
    protected function registerExtensions(\Twig_Environment $twig)
    {
        // file()
        $twig->addFunction(new \Twig_SimpleFunction('file', [$this, 'fileFunction']));

        // fragment()
        $twig->addFunction(new \Twig_SimpleFunction('fragment', [$this, 'fragmentFunction']));

        // itemtoc() and its internal counterpart
        $twig->addFunction(new \Twig_SimpleFunction('itemtoc', [$this, 'itemTocFunction']));
        $twig->addFunction(
            new \Twig_SimpleFunction('_itemtoc_internal', [$this, 'itemTocInternalFunction']));
    }

    /**
     * Twig function: <b>file(filename, variables, options)</b>
     *
     * @deprecated
     * @param string $filename  to be included (relative to book Contents dir)
     * @param array  $variables to be passed to the template
     * @param array  $options   (default: 'nopagebreak: false' => add a page break after the included text)
     * @return string included text with all the replacements done.
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function fileFunction($filename,
                                 $variables = [],
                                 $options = []): string
    {
        $dir = $this->app['publishing.dir.contents'];
        $file = $dir.'/'.$filename;

        if (!file_exists($file)) {
            $this->writeLn(
                sprintf('Included content file "%s" not found in "%s"', $filename, $this->item['config']['content']),
                'error');

            return $filename;
        }

        $rendered = $this->renderString(file_get_contents($file), $variables);

        // pagebreak is added by default
        $addPageBreak = !isset($options['nopagebreak']) || (isset($options['nopagebreak']) && !$options['nopagebreak']);
        if ($addPageBreak) {
            $rendered .= '<div class="page-break"></div>';
        }

        return $rendered;
    }

    /**
     * Twig function: <b>fragment(filename)</b>
     *
     * @param string $filename  to be included (relative to book Contents dir)
     * @param array  $variables to be passed to the template (example: {'name': 'John'} )
     * @param array  $options   (example {'pagebreak' => true} will add a page break after the included text)
     * @return string included text with all the replacements done.
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function fragmentFunction($filename,
                                     $variables = [],
                                     $options = []): string
    {
        $dir = $this->app['publishing.dir.contents'];
        $file = $dir.'/'.$filename;

        if (!file_exists($file)) {
            $this->writeLn(
                sprintf('Fragment file "%s" not found in "%s"', $filename, $this->item['config']['content']),
                'error');

            return $filename;
        }

        $rendered = $this->renderString(file_get_contents($file), $variables);

        // surround contents with the (optional) tag and class
        $tag = $options['tag'] ?? '';
        $attributes = ['markdown' => 1];
        $attributes['class'] = $options['class'] ?? '';
        if (empty($tag) && $attributes['class']) {
            $tag = 'div';
        }

        if ($tag) {
            $attributes['class'] = 'fragment '.$attributes['class'];
            $rendered = Toolkit::renderHTMLTag($tag, $rendered, $attributes);
        }

        // add pagebreak if asked
        if (isset($options['pagebreak']) && $options['pagebreak']) {
            $rendered .= '<div class="page-break"></div>';
        }

        return $rendered;
    }

    /**
     * Twig function: <b>itemtoc()</b>
     *
     * @return string The _itemtoc_internal() function call
     * Generating the item toc requires two phases to ensure that included files got parsed after being
     * included (with file()). The second phase does the actual rendering of the itemtoc
     * template.
     */
    public function itemTocFunction(): string
    {
        return '{{ _itemtoc_internal() }}';
    }

    /**
     * Twig function: <b>_itemtoc_internal()</b>
     * <b>This is an internal function so is not to be used directly in content files.</b>
     * It will be invoked on itemPostParse, after all the file() functions have been resolved and all
     * the included item contents have been parsed.
     *
     * @uses Configuration option <i>edition.plugins.TwigExtension.itemtoc.deep</i>
     *       (default: <i>edition.toc.deep + 1 </i>
     * @return string The item toc rendered
     */
    public function itemTocInternalFunction(): string
    {
        $template = 'itemtoc.twig';

        // note that we need to use the normal Twig template renderer, not our Twig string renderer
        $twig = $this->app['twig'];
        $this->addTwigGlobals($twig);

        $itemtoc_deep = $this->getEditionOption(
            'plugins.options.TwigExtension.itemtoc.deep',
            $this->getEditionOption('toc.deep') + 1);

        return $twig->render($template, ['itemtoc_deep' => $itemtoc_deep]);
    }
}
