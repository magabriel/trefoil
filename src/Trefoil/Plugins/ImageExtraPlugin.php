<?php
namespace Trefoil\Plugins;

use Trefoil\Util\Toolkit;

use Symfony\Component\Finder\Finder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Easybook\Events\EasybookEvents;
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
 */
class ImageExtraPlugin extends BasePlugin implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
                EasybookEvents::PRE_PARSE => 'onItemPreParse',
                EasybookEvents::POST_PARSE => 'onItemPostParse'
        );
    }

    public function onItemPreParse(ParseEvent $event)
    {
        $this->init($event);

        $content = $event->getOriginal();

        $content = $this->preProcessImages($content);

        $event->setOriginal($content);
    }

    public function onItemPostParse(BaseEvent $event)
    {
        $this->init($event);

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

                        /* all spaces must be replaced by "_" for this to work
                         * (this is because of the way Markdown parses the image specification)
                         */
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
                      $image = Toolkit::parseHTMLAttributes($matches['image']);
                      $image = $this->processExtraImage($image);
                      $html = Toolkit::renderHTMLTag('img', null, $image);

                      return $html;
                      },
                      $content);

        return $content;
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
            // replace back all '_' to spaces
            $args['style'] = str_replace('_', ' ', $args['style']);
            $image['style'] = isset($image['style']) ? $image['style'].';'.$args['style'] : $args['style'];
            unset($args['style']);
        }

        $image = array_merge($image, $args);

        return $image;
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

