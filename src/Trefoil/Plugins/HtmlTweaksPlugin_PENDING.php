<?php
namespace Trefoil\Plugins;

use Trefoil\Util\Toolkit;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Easybook\Events\EasybookEvents as Events;
use Easybook\Events\BaseEvent;
use Easybook\Events\ParseEvent;
use Trefoil\Events\TrefoilEvents;

/**
 * Several tweaks to make the ebook compatible with Kindle MOBI format
 *
 * @author miguelangel
 *
 */
class KindleTweaksPlugin implements EventSubscriberInterface
{
    protected $app;
    protected $output;
    protected $item;

    protected $tweaks = array();

    public static function getSubscribedEvents()
    {
        return array(
                TrefoilEvents::PRE_PUBLISH_AND_READY => 'onPrePublishAndReady',
                Events::PRE_PARSE => array('onItemPreParse', -1000),
                Events::POST_PARSE => array('onItemPostParse', -1000)
                );
    }

    public function onPrePublishAndReady(BaseEvent $event)
    {
        $this->app = $event->app;
        $this->output = $this->app->get('console.output');

        // read tweaks defined in file
        $this->readTweaksFile();
    }

    public function onItemPreParse(BaseEvent $event)
    {
        $this->app = $event->app;
        $this->output = $this->app->get('console.output');
        $edition = $this->app['publishing.edition'];
        $this->item = $event->getItem();

        $this->app['book.logger']->debug('onItemPreParse:begin', get_class(), $this->item['config']['content']);

        $content =  $event->getOriginal();

        if (isset($this->tweaks['tags']['onPreParse'])) {
            $content = $this->replaceTags($content, $this->tweaks['tags']['onPreParse']);
        }

        $event->setOriginal($content);

        $this->app['book.logger']->debug('onItemPreParse:end', get_class(), $this->item['config']['content']);
    }

    public function onItemPostParse(BaseEvent $event)
    {
        $this->app = $event->app;
        $this->output = $this->app->get('console.output');
        $edition = $this->app['publishing.edition'];
        $this->item = $event->getItem();

        $this->app['book.logger']->debug('onItemPostParse:begin', get_class(), $this->item['config']['content']);

        $content = $event->getContent();

        if (isset($this->tweaks['tags']['onPostParse'])) {
            $content = $this->replaceTags($content, $this->tweaks['tags']['onPostParse']);
        }

        $content = $this->paragraphsInsideLists($content);
        $content = $this->tableCellsAlignment($content);

        $event->setContent($content);

        $this->app['book.logger']->debug('onItemPostParse:end', get_class(), $this->item['config']['content']);
    }

    /**
     * Read the Yml file with the tweaks' options
     *
     * @return boolean
     */
    protected function readTweaksFile()
    {
        // first path is the book Contents dir
        $bookDir = $this->app->get('publishing.dir.book');
        $contentsDir = $bookDir . '/Contents';

        // second path is the theme /Config dir
        $edition = $this->app['publishing.edition'];
        $theme = ucfirst($this->app->edition('theme'));
        $format = Toolkit::getCurrentFormat($this->app);

        // get the source dir (inside theme)
        $themeDir = realpath(__DIR__ . '/../../Themes/' . $theme);

        $configDir = sprintf('%s/%s/Config', $themeDir, $format);

        // look for either one
        $dirs = array($contentsDir, $configDir);
        $file = $this->app->getFirstExistingFile('kindle-tweaks.yml', $dirs);

        if (!$file) {
            $this->output
            ->write(
                    sprintf("<comment>No Kindle tweaks file found in %s</comment>\n",
                            "\n - ".implode("\n - ", $dirs)));
            return false;
        }

        $this->tweaks = Yaml::parse($file);
    }

    /**
     * Replace some tags into the content with Kindle-adapted text
     *
     * Expected definitions:
     *
     * tags:
     *   onPreParse:
     *     tag-name-free:                                  # just a name
     *       tag: 'div'                                    # tag name to find
     *       class: 'class-name'                           # OPTIONAL class name assigned to tag
     *       # either one of 'insert', 'surround' or 'replace' items
     *       insert:
     *          open:  '<span class="mobi">===</span>'     # opening text
     *          close: '<span class="mobi">####</span>'    # closing text
     *       surround:
     *          open:  '<div class="other-class">'         # opening text
     *          close: '</div>'                            # closing text
     *       replace:
     *          tag:  'h2'                                 # replacement tag
     *
     *   onPostParse:
     *     another-free-name:
     *         tag: 'span'
     *         ----
     *
     *
     * @param unknown $content
     * @return string|mixed
     */
    protected function replaceTags($content, $tags)
    {

        foreach ($tags as $item) {
            $noclose = false;
            if ('hr' == $item['tag']) {
                $noclose = true;

                $pattern = '/';
                $pattern.= '<'.$item['tag'].' *(?<attrs>.*) *\/>';
                $pattern.= '/Ums';
            } else {
                $pattern = '/';
                $pattern.= '<'.$item['tag'].' *(?<attrs>.*)>';
                $pattern.= '(?<inner>.*)';
                $pattern.= '<\/'.$item['tag'].'>';
                $pattern.= '/Ums';
            }

            $content = preg_replace_callback($pattern,
                    function ($matches) use ($item, $noclose)
                    {
                        $found = true;
                        if (isset($item['class'])) {
                            if (preg_match('/class="(?<classes>.*)"/Ums', $matches['attrs'], $matches2)) {
                                $classes = explode(' ', $matches2['classes']);
                                if (!in_array($item['class'], $classes)) {
                                    $found = false;
                                }
                            } else {
                                $found = false;
                            }
                        }

                        $newTag = sprintf('<%s %s>%s</%s>',
                                            $item['tag'],
                                            $matches['attrs'],
                                            $matches['inner'],
                                            $item['tag']);

                        if ($found) {
                            if (isset($item['insert'])) {
                                $newTag = sprintf('<%s %s>%s%s%s</%s>',
                                            $item['tag'],
                                            $matches['attrs'],
                                            $item['insert']['open'],
                                            $matches['inner'],
                                            $item['insert']['close'],
                                            $item['tag']);

                            } elseif (isset($item['surround'])) {
                                $newTag = sprintf('%s<%s %s>%s</%s>%s',
                                        $item['surround']['open'],
                                        $item['tag'],
                                        $matches['attrs'],
                                        $matches['inner'],
                                        $item['tag'],
                                        $item['surround']['close']);

                            } elseif (isset($item['replace'])) {
                                if ($noclose) {
                                    $newTag = sprintf('<%s %s />',
                                            $item['replace']['tag'],
                                            $matches['attrs']);
                                } else {
                                    $newTag = sprintf('<%s %s>%s</%s>',
                                            $item['replace']['tag'],
                                            $matches['attrs'],
                                            $matches['inner'],
                                            $item['replace']['tag']);
                                }
                            }
                        }

                        return $newTag;
                    }, $content);
        }

        return $content;

    }

    /**
     * Convert paragraphs inside list elements to line breaks
     *
     * @param unknown $content
     * @return string|mixed
     */
    protected function paragraphsInsideLists($content)
    {
        $content = preg_replace_callback('/<li>(?<li>.*)<\/li>/Ums',
                function ($matches)
                {
                   $li = preg_replace_callback('/<p>(?<ptext>.*)<\/p>/Ums',
                           function ($matches2)
                           {
                               return sprintf('%s<br/>', $matches2['ptext']);
                           }, $matches['li']);

                   return sprintf('<li>%s</li>', $li);
                }, $content);

        return $content;
    }

    /**
     * Assing explicit alignment via style to table cells
     *
     * @param unknown $content
     * @return string|mixed
     */
    protected function tableCellsAlignment($content)
    {
        $content = preg_replace_callback('/<(?<tag>th|td) align="(?<align>.*)">/Ums',
                function ($matches)
                {
                    $tag = $matches['tag'];
                    $align = $matches['align'];
                    return sprintf('<%s align="%s" style="text-align:%s">', $tag, $align, $align);
                }, $content);

        return $content;
    }
}

