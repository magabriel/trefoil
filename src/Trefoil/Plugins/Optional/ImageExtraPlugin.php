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

use Easybook\Events\BaseEvent;
use Easybook\Events\EasybookEvents;
use Easybook\Events\ParseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Trefoil\Plugins\BasePlugin;
use Trefoil\Util\Toolkit;

/**
 * This plugin brings support to extended image syntax:
 * - Explicit image path to provode compatibility with editors like MdCharm:
 * ![caption](my/images/image.name]
 * - Extended image syntax with optional CSS class and style:
 * ![this is an image](image.jpg?class="my-image-class"&style="border:_1px_solid_blue;_padding:_10px;")
 * - Support for extended image styles (specifiable as classes) in themes as predefined
 *   classes: *narrower*, *narrow**, *half*, and *wide*
 * ![a narrow image](image.jpg?class="narrow")
 */
class ImageExtraPlugin extends BasePlugin implements EventSubscriberInterface
{
    /**
     * String to replace spaces into images specifications
     *
     * @var string
     */
    public const SPACE_REPLACEMENT = '¬|{^';

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EasybookEvents::PRE_PARSE     => ['onItemPreParse', -100], // after TwigExtensionPlugin
            EasybookEvents::POST_PARSE    => 'onItemPostParse',    // after content has been parsed
            EasybookEvents::POST_DECORATE => 'onItemPostDecorate' // after templates have been rendered
        ];
    }

    /**
     * @param ParseEvent $event
     */
    public function onItemPreParse(ParseEvent $event)
    {
        $this->init($event);

        $content = $event->getItemProperty('original');

        $content = $this->preProcessImages($content);

        $event->setItemProperty('original', $content);
    }

    /**
     * @param BaseEvent $event
     */
    public function onItemPostParse(BaseEvent $event)
    {
        $this->init($event);

        $this->item['content'] = $this->processImages($this->item['content']);

        $event->setItem($this->item);
    }

    /**
     * @param BaseEvent $event
     */
    public function onItemPostDecorate(BaseEvent $event)
    {
        $this->init($event);

        $this->item['content'] = $this->processImages($this->item['content']);

        $event->setItem($this->item);
    }

    /**
     * @param $content
     * @return string|string[]|null
     */
    public function preProcessImages($content)
    {
        $regExp = '/';
        $regExp .= '!\[(?<alt>.*)\]'; // the optional alt text
        $regExp .= '\((?<image>.*)\)'; // the image specification
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $content = preg_replace_callback(
            $regExp,
            function ($matches) {
                $image = $matches['image'];
                $arguments = '';

                // get the query
                $parts = explode('?', html_entity_decode($matches['image']));
                if (isset($parts[1])) {
                    // no query, nothing to do

                    // the real image
                    $image = $parts[0];

                    // allow images to include 'images/' as path, for compatibility
                    // with Markdown editors like MdCharm
                    $image = str_replace('images/', '', $image);

                    // get the arguments
                    parse_str($parts[1], $args);
                    $args = str_replace('"', '', $args);

                    /* replace all spaces for this to work
                     * (this is because of the way Markdown parses the image specification)
                     */
                    if (isset($args['class'])) {
                        $args['class'] = str_replace(' ', $this::SPACE_REPLACEMENT, $args['class']);
                    }

                    if (isset($args['style'])) {
                        $args['style'] = str_replace(' ', $this::SPACE_REPLACEMENT, $args['style']);
                    }

                    $arguments = $this->internalRenderArguments($args);
                }

                return sprintf('![%s](%s%s)', $matches['alt'], $image, ($arguments ? '?'.$arguments : ''));
            },
            $content);

        return $content;
    }

    /**
     * @param $content
     * @return mixed|string|string[]|null
     */
    public function processImages($content)
    {
        $regExp = '/';
        $regExp .= '<img +(?<image>.*)>';
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $content = preg_replace_callback(
            $regExp,
            function ($matches) {
                $image = Toolkit::parseHTMLAttributes($matches['image']);
                $image = $this->internalProcessExtraImage($image);

                return Toolkit::renderHTMLTag('img', null, $image);
            },
            $content);

        // ensure there is no space replacements left (it can happen if
        // some of the image tags were not rendered because they were
        // into '<pre>' or '<code>' tags)
        $content = str_replace(self::SPACE_REPLACEMENT, ' ', $content);

        return $content;
    }

    /**
     * @param array $image
     * @return array
     */
    protected function internalProcessExtraImage(array $image): array
    {
        // replace typographic quotes (just in case, may be set by SmartyPants)
        $src = str_replace(['&#8221;', '&#8217;'], '"', $image['src']);

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

    /**
     * @param array $arguments
     * @return string
     */
    protected function internalRenderArguments(array $arguments): string
    {
        $argArray = [];

        foreach ($arguments as $name => $value) {
            $argArray[] = sprintf('%s="%s"', $name, $value);
        }

        return implode('&', $argArray);
    }
}
