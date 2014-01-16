<?php
namespace Trefoil\Plugins;

use Easybook\Events\BaseEvent;
use Easybook\Events\EasybookEvents;
use Easybook\Events\ParseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Trefoil\Util\Toolkit;

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
    /**
     * String to replace spaces into images specifications
     * @var string
     */
    const SPACE_REPLACEMENT = '¬|{^';

    public static function getSubscribedEvents()
    {
        return array(
                EasybookEvents::PRE_PARSE => array('onItemPreParse', -100), // after TwigExtensionPlugin
                EasybookEvents::POST_PARSE => 'onItemPostParse'
        );
    }

    public function onItemPreParse(ParseEvent $event)
    {
        $this->init($event);

        $content = $event->getItemProperty('original');

        $content = $this->preProcessImages($content);

        $event->setItemProperty('original', $content);
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

                        /* replace all spaces for this to work
                         * (this is because of the way Markdown parses the image specification)
                         */
                        if (isset($args['class'])) {
                            $args['class'] = str_replace(' ', self::SPACE_REPLACEMENT, $args['class']);
                        }

                        if (isset($args['style'])) {
                            $args['style'] = str_replace(' ', self::SPACE_REPLACEMENT, $args['style']);
                        }

                        $arguments = $me->renderArguments($args);
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
                      $image = $me->processExtraImage($image);
                      $html = Toolkit::renderHTMLTag('img', null, $image);

                      return $html;
                      },
                      $content);

        // ensure there is no space replacements left (it can happen if
        // some of the image tags were not rendered because they were
        // into '<pre>' or '<code>' tags)
        $content = str_replace(self::SPACE_REPLACEMENT, ' ', $content);

        return $content;
    }

    protected function processExtraImage(array $image)
    {
        // allow images to include 'images/' as path, for compatibility
        // with Markdown editors like MdCharm
        $image['src'] = str_replace('images/', '', $image['src']);

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
            $args['class'] = str_replace(self::SPACE_REPLACEMENT, ' ', $args['class']);
            $image['class'] = isset($image['class']) ? $image['class'].' '.$args['class'] : $args['class'];
            unset($args['class']);
        }

        if (isset($args['style'])) {
            // replace back all spaces
            $args['style'] = str_replace(self::SPACE_REPLACEMENT, ' ', $args['style']);
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

