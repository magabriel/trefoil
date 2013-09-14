<?php
namespace Trefoil\Plugins;

use Easybook\DependencyInjection\Application;

use Easybook\Events\BaseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Easybook\Events\EasybookEvents as Events;
use Trefoil\Events\TrefoilEvents;
use Easybook\Events\ParseEvent;
use Symfony\Component\DomCrawler\Crawler;


/**
 * This plugin extends Twig to allow configuration options in the book contents
 * and adds some functions/filters
 *
 */
class TwigExtensionPlugin implements EventSubscriberInterface
{
    protected $app;
    protected $item;

    public static function getSubscribedEvents()
    {
        return array(
                Events::PRE_PARSE => 'onItemPreParse',
                Events::POST_PARSE => array('onItemPostParse', -1010) // after ParserPlugin
        );
    }

    public function onItemPreParse(ParseEvent $event)
    {
        $this->app = $event->app;
        $this->item = $event->getItem();

        $content = $event->getOriginal();

        $content = str_replace('{#', '{@', $content);
        $content = $this->replaceBookOptions($content);

        $event->setOriginal($content);
    }

    public function onItemPostParse(ParseEvent $event)
    {
        $this->app = $event->app;
        $this->item = $event->getItem();

        $content = $event->getContent();

        $content = $this->replaceBookOptions($content);
        $content = str_replace('{@', '{#', $content);

        $event->setContent($content);
    }

    protected function replaceBookOptions($content)
    {
        // 'book' and 'edition' are already set. We only need to set 'item'.
        $vars = array('item' => $this->item);

        # avoid problems with markdown extra syntax for ids in headers
        //$content = str_replace('{#', '{@', $content);
        $content = $this->renderString($content, $vars);
        //$content = str_replace('{@', '{#', $content);

        return $content;
    }

    public function renderString($string, $variables = array())
    {
        $twig = new \Twig_Environment(new \Twig_Loader_String(), $this->app->get('twig.options'));

        // add easybook globlals
        $twig->addGlobal('app', $this->app);

        if (null != $this->app->get('publishing.book.config')['book']) {
            $twig->addGlobal('book', $this->app->get('publishing.book.config')['book']);

            $publishingEdition = $this->app->get('publishing.edition');
            $editions = $this->app->book('editions');
            $twig->addGlobal('edition', $editions[$publishingEdition]);
        }

        // here we add the extension (cumbersome, until Twig 1.12
        $twig->addExtension(new TwigContentExtension($twig, $this->app, $this->item, $variables));

        return $twig->render($string, $variables);
    }
}

/* just until Twig 1.12 */
class TwigContentExtension extends \Twig_Extension
{
    protected $twig;
    protected $app;
    protected $item;
    protected $variables;

    public function __construct(\Twig_Environment $twig, Application $app, $item, $variables)
    {
        $this->twig = $twig;
        $this->app = $app;
        $this->item = $item;
        $this->variables = $variables;
    }

    public function getFunctions()
    {
        return array(
                'file'       => new \Twig_Function_Method($this, 'fileFunction'),
                'itemtoc'    => new \Twig_Function_Method($this, 'itemTocFunction'),
                'itemtoc_internal' => new \Twig_Function_Method($this, 'itemTocInternalFunction'),
                );
    }

    public function fileFunction($filename, $variables = array(), $options = array())
    {
        $dir = $this->app['publishing.dir.contents'];
        $file = $dir.'/'.$filename;

        if (!file_exists($file)) {
            throw new \Exception(sprintf('Included content file "%s" not found in "%s"', $filename, $this->item['config']['content'] ));
        }

        $vars = array_merge($this->variables, $variables);
        $rendered = $this->twig->render(file_get_contents($file), $vars);

        $addPageBreak = !isset($options['nopagebreak']) || (isset($options['nopagebreak']) && !$options['nopagebreak']);
        if ($addPageBreak) {
            $rendered.= '<div class="page-break"></div>';
        }

        return $rendered;
    }

    public function itemTocFunction()
    {
        return '{{ itemtoc_internal() }}';
    }

    public function itemTocInternalFunction()
    {
        $template = 'itemtoc.twig';
        $variables = array('item' => $this->item);

        $rendered = $this->app->get('twig')->render($template , $variables);

        return $rendered;
    }

    public function getName()
    {
        return 'twig_content_extension';
    }
}