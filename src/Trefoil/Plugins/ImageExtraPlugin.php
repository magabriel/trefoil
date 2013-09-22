<?php
namespace Trefoil\Plugins;

use Symfony\Component\Finder\Finder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Easybook\Events\EasybookEvents as Events;
use Easybook\Events\BaseEvent;
use Easybook\Events\ParseEvent;

/**
 * This plugin brings support to extended image syntax:
 *
 * ![caption](image.name?class="myclass"&style="any_css_style_specification")
 *
 * Example:
 *
 * ![this is an image](image.jpg?class="my-image-class"&style="border:_1px_solid_blue;_padding:_10px;")
 *
 * In the style specification all spaces must be replaced by "_" in order to work (this is because of
 * the way Markdown parses the image specification)
 */
class ImageExtraPlugin implements EventSubscriberInterface
{
    protected $app;
    protected $item;

    public static function getSubscribedEvents()
    {
        return array(
                Events::PRE_PARSE => 'onItemPreParse',
                Events::POST_PARSE => 'onItemPostParse'
        );
    }

    public function onItemPreParse(ParseEvent $event)
    {
        $this->app = $event->app;
        $this->item = $event->getItem();
        $content = $event->getOriginal();

        $content = $this->preProcessImages($content);

        $event->setOriginal($content);
    }

    public function onItemPostParse(BaseEvent $event)
    {
        $this->app = $event->app;
        $this->output = $this->app->get('console.output');
        $edition = $this->app['publishing.edition'];
        $this->item = $event->getItem();
        $content = $this->item['content'];

        $content = $this->processImages($content);

        $this->item['content'] = $content;
        $event->setItem($this->item);
    }

    public function preProcessImages($content)
    {
        $regExp = '/';
        $regExp .= '!\[(?<alt>.*)\]'; // the optional alt text
        $regExp .= '\((?<image>.*)\)'; // the image specification
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $me = $this;
        $content = preg_replace_callback($regExp,
                function ($matches) use ($me)
                {
                    $image = $matches['image'];
                    $arguments = '';

                    // get the query
                    $parts = explode('?', html_entity_decode($matches['image']));
                    if (isset($parts[1])) {
                        // no query, nothing to do

                        // the real image
                        $image = $parts[0];

                        // get the arguments
                        parse_str($parts[1], $args);
                        $args = str_replace('"', '', $args);

                        if (isset($args['style'])) {
                            $args['style'] = str_replace(' ', '_', $args['style']);
                        }

                        $arguments = $this->renderArguments($args);
                    }

                    return sprintf('![%s](%s%s)', $matches['alt'], $image, ($arguments ? '?'.$arguments : ''));
                }, $content);

        return $content;
    }

    public function processImages($content)
    {
        $regExp = '/';
        $regExp .= '<img +(?<image>.*)>';
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $me = $this;
        $content = preg_replace_callback(
            $regExp,
            function ($matches) use ($me)
                      {
                      $image = $this->parseImage($matches['image']);
                      $image = $this->processExtraImage($image);
                      $html = $this->renderImage($image);

                      return $html;
                      },
                      $content);

        return $content;
    }

    protected function parseImage($imageHtml)
    {
        $image = $this->extractAttributes($imageHtml);

        return $image;
    }

    protected function processExtraImage(array $image)
    {
        // replace typographic quotes (just in case, may be set by SmartyPants)
        $src = str_replace(array('&#8221;', '&#8217;'), '"', $image['src']);

        // get the query
        $parts = explode('?', html_entity_decode($src));
        if (!isset($parts[1])) {
            // no query, nothing to do
            return $image;
        }

        // the real image
        $image['src'] = $parts[0];

        // get the arguments
        parse_str($parts[1], $args);
        $args = str_replace('"', '', $args);

        // assign them
        if (isset($args['class'])) {
            $image['class'] = isset($image['class']) ? $image['class'].' '.$args['class'] : $args['class'];
            unset($args['class']);
        }

        if (isset($args['style'])) {
            $args['style'] = str_replace('_', ' ', $args['style']);
            $image['style'] = isset($image['style']) ? $image['style'].';'.$args['style'] : $args['style'];
            unset($args['style']);
        }

        $image = array_merge($image, $args);

        return $image;
    }

    protected function renderImage(array $image)
    {
        $html = '';

        $attributes = $this->renderAttributes($image);
        $html .= sprintf('<img %s/>', $attributes);

        return $html;
    }

    /**
     * @param string $string
     * @return array of attribures
     */
    protected function extractAttributes($string)
    {
        $attributes = array();

        $regExp = '/(?<attr>.*)="(?<value>.*)"/Us';
        preg_match_all($regExp, $string, $attrMatches, PREG_SET_ORDER);

        $attributes = array();
        foreach ($attrMatches as $attrMatch) {
            $attributes[trim($attrMatch['attr'])] = $attrMatch['value'];
        }

        return $attributes;
    }

    protected function renderAttributes(array $attributes)
    {
        $html = '';

        foreach ($attributes as $name => $value) {
            $html .= sprintf('%s="%s" ', $name, $value);
        }

        return $html;
    }

    protected function renderArguments(array $arguments)
    {
        $argArray = array();

        foreach ($arguments as $name => $value) {
            $argArray[] = sprintf('%s="%s"', $name, $value);
        }

        return implode('&', $argArray);
    }
}

