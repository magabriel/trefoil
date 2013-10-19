<?php
namespace Trefoil\Plugins;

use Trefoil\Util\Toolkit;
use Easybook\Events\BaseEvent;

/**
 * Base class for all plugins
 *
 */
abstract class BasePlugin
{
    protected $app;
    protected $output;
    protected $edition;
    protected $format;
    protected $theme;
    protected $item;
    protected $internalLinksMapper = array();

    /**
     * Do some initialization tasks.
     * Must be called explicitly for each plugin at the begining
     * of each event handler method.
     *
     * @param BaseEvent $event
     */
    public function init(BaseEvent $event)
    {
        $this->app = $event->app;
        $this->output = $this->app->get('console.output');
        $this->edition = $this->app['publishing.edition'];
        $this->format = Toolkit::getCurrentFormat($this->app);
        $this->theme = ucfirst($this->app->edition('theme'));
        $this->item = $event->getItem();
    }

    /**
     * Write an output message line
     *
     * @param string $message
     * @param string $usePrefix
     */
    public function writeLn($message, $usePrefix = true)
    {
        $this->write($message, $usePrefix);
        $this->output->writeLn('');
    }

    /**
     * Write an output message (w/o a line break)
     *
     * @param string $message
     * @param string $usePrefix
     */
    public function write($message, $usePrefix = true)
    {
        $prefix = '';
        if ($usePrefix) {
            $class = join('',array_slice(explode('\\', get_called_class()), -1));
            $prefix = sprintf('%s: ', $class);
        }
        $this->output->write(' > '.$prefix.$message);
    }

    /**
     * Save an internal link target (or "anchor") from the current item
     * in the internal link mappper, so later it can be fixed.
     *
     * @param string $id to save
     */
    protected function saveInternalLinkTarget($id)
    {
        $item = $this->item;

        $itemSlug = $item['config']['element'];
        if (array_key_exists('number', $item['config']) && $item['config']['number'] !== '') {
            $itemSlug = $item['config']['element'] . '-' . $item['config']['number'];
        }

        $relativeUrl = '#' . $id;
        $absoluteUrl = $itemSlug . '.html' . $relativeUrl;

        if (!isset( $this->internalLinksMapper[$relativeUrl])) {
            $this->internalLinksMapper[$relativeUrl] = $absoluteUrl;
        }
    }

    /**
     * Fix internal links ('#internal-link') with the correct internal url ('item.html#internal-link').
     * Also assign a CSS class to invalid links for easy spotting in the book, mainly for debugging
     * plugins that create internal links for advanced navigation (i.e. AutoGlossary).
     *
     * @param string $html to process (null => process item['content']
     * @return string
     */
    protected function fixInternalLinks($content = null)
    {
        $html = $content ?: $this->item['content'];

        $internalLinksMapper = $this->internalLinksMapper;

        $html = preg_replace_callback(
                '/<a (?<prev>.*)href="(?<uri>#.*)"(?<post>.*)<\/a>/Us',
                function($matches) use($internalLinksMapper) {

                    $uri = $matches['uri'];
                    $existing = false;

                    if (isset($internalLinksMapper[$matches['uri']])) {
                        $uri = $internalLinksMapper[$matches['uri']];
                        $existing = true;
                    } else {
                        // look if it is an already resolved internal url ('./chapter1.html#my-target')
                        $parts = split('#', $matches['uri']);
                        if (isset($parts[1])) {
                            // if already resolved it should be valid
                            $existing = true;
                        }
                    }

                    return sprintf(
                            '<a %sclass="internal%s" href="%s"%s</a>',
                            $matches['prev'],
                            $existing ? '' : ' invalid',
                            $uri,
                            $matches['post']
                    );
                },
                $html
        );

        if ($content) {
            return $html;
        }

        $this->item['content'] = $html;
        return $html;
    }

    /**
     * Retrieve the value of an edition option (from config.yml file)
     *
     * @param string $optionNane (as in 'one.two.three')
     * @param string $default
     * @return mixed
     */
    protected function getEditionOption($optionNane, $default = null)
    {
        $editionOptions = $this->app->book('editions')[$this->edition];

        $keys = explode('.', $optionNane);

        $option = $editionOptions;

        foreach ($keys as $key) {
            if (array_key_exists($key, $option)) {
                $option = $option[$key];
            } else {
                return $default;
            }
        }

        return $option;
    }
}
