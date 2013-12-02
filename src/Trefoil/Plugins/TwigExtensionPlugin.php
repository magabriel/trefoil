<?php
namespace Trefoil\Plugins;

use Easybook\DependencyInjection\Application;
use Easybook\Events\BaseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Easybook\Events\EasybookEvents;
use Trefoil\Events\TrefoilEvents;
use Easybook\Events\ParseEvent;
use Symfony\Component\DomCrawler\Crawler;


/**
 * This plugin extends Twig to provide some useful functionalities:
 *
 * <li>
 * <b>Use configuration options in book contents</b> (instead of only in templates).
 * For example, in "chapter1.md" you can write "The title is {{ book.title }}".
 * This is mainly useful with user-defined configuration options.
 *
 * <li>
 * <b>itemtoc()</b> function automatically generates the Table of Contents of the current item.
 * The deep of the itemtoc can be tweaked by the configuration option <i>edition.itemtoc.deep</i>,
 * which defaults to one less than the main TOC deep <i>edition.toc.deep</i>.
 * It uses de <i>itemtoc.twig</i> template that must be available either as local or global template.
 *
 * <li>
 * <b>file()</b> function works like PHP's <i>include()</i>, allowing the inclusion of another file
 * into the current item.
 * The syntax is <i>file(filename, variables, options)</i> where "variables" and "options" are
 * optional hash tables where you can pass variables {'variable': 'value'} or
 * options {'nopagebreak': true} to the included file.
 *
 */
class TwigExtensionPlugin extends BasePlugin implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
                EasybookEvents::PRE_PARSE => 'onItemPreParse',
                EasybookEvents::POST_PARSE => array('onItemPostParse', -1010)
        );
    }

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

    public function onItemPostParse(ParseEvent $event)
    {
        $this->init($event);

        $content = $event->getItemProperty('content');

        // replace "{#" to avoid problems with markdown extra syntax for ids in headers
        $content = str_replace('{#', '@%@&', $content);

        // replace also in PostParse to process the second pass of "itemtoc()" ("itemtoc_internal()")
        $content = $this->renderString($content);

        # replace back "{#"
        $content = str_replace('@%@&', '{#', $content);

        $event->setItemProperty('content', $content);
    }

    protected function renderString($string, $variables = array())
    {
        // we need a new Twig String Renderer environment
        $twig = new \Twig_Environment(new \Twig_Loader_String(), $this->app->get('twig.options'));

        $this->addTwigGlobals($twig);
        $this->registerExtensions($twig);

        return $twig->render($string, $variables);
    }

    protected function addTwigGlobals(\Twig_Environment $twig)
    {
        $twig->addGlobal('app', $this->app);

        if (null != $this->app->get('publishing.book.config')['book']) {
            $twig->addGlobal('book', $this->app->get('publishing.book.config')['book']);

            $publishingEdition = $this->edition;
            $editions = $this->app->book('editions');
            $twig->addGlobal('edition', $editions[$publishingEdition]);
        }

        $twig->addGlobal('item', $this->item);
    }

    /**
     * Register all the extensions here.
     */
    protected function registerExtensions(\Twig_Environment $twig)
    {
        // file()
        $twig->addFunction(new \Twig_SimpleFunction('file', array($this, 'fileFunction')));

        // itemtoc() and its internal counterpart
        $twig->addFunction(new \Twig_SimpleFunction('itemtoc', array($this, 'itemTocFunction')));
        $twig->addFunction(
                new \Twig_SimpleFunction('_itemtoc_internal', array($this, '_itemTocInternalFunction')));
    }

    /**
     * Twig function: <b>file(filename, variables, options)</b>
     * @param string $filename to be included (relative to book Contents dir)
     * @param array $variables to be passed to the template
     * @param array $options (default: 'nopagebreak: true' => do not add a page break after the included text)
     * @return string included text with all the replacements done.
     */
    public function fileFunction($filename, $variables = array(), $options = array())
    {
        $dir = $this->app['publishing.dir.contents'];
        $file = $dir.'/'.$filename;

        if (!file_exists($file)) {
            $this->writeLn(
                    sprintf('Included content file "%s" not found in "%s"', $filename, $this->item['config']['content'] ),
                    'error');
            return $fileName;
        }

        $rendered = $this->renderString(file_get_contents($file), $variables);

        // pagebreak is added by default
        $addPageBreak = !isset($options['nopagebreak']) || (isset($options['nopagebreak']) && !$options['nopagebreak']);
        if ($addPageBreak) {
            $rendered.= '<div class="page-break"></div>';
        }

        return $rendered;
    }

    /**
     * Twig function: <b>itemtoc()</b>
     * @return string The _itemtoc_internal() function call
     *
     * Generating the item toc requires two phases to ensure that included files got parsed after being
     * included (with file()). The second phase does the actual rendering of the itemtoc
     * template.
     */
    public function itemTocFunction()
    {
        return '{{ _itemtoc_internal() }}';
    }

    /**
     * Twig function: <b>_itemtoc_internal()</b>
     * <b>This is an internal function so is not to be used directly in content files.</b>
     * It will be invoked on itemPostParse, after all the file() functions have been resolved and all
     * the included item contents have been parsed.

     * @uses Configuration option <i>edition.plugins.TwigExtension.itemtoc.deep</i>
     *       (default: <i>edition.toc.deep + 1 </i>
     *
     * @return string The item toc rendered
     * @internal
     */
    public function _itemTocInternalFunction()
    {
        $template = 'itemtoc.twig';

        // note that we need to use the normal Twig template renderer, not our Twig string renderer
        $twig = $this->app->get('twig');
        $this->addTwigGlobals($twig);

        $itemtoc_deep = $this->getEditionOption('plugins.options.TwigExtension.itemtoc.deep',
                        $this->getEditionOption('toc.deep') + 1);

        $rendered = $twig->render($template, array('itemtoc_deep' => $itemtoc_deep));

        return $rendered;
    }
}
