<?php
/*
 * This file is part of the trefoil application.
 *
 * (c) Miguel Angel Gabriel <magabriel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Trefoil\Plugins;

use Easybook\Events\BaseEvent;
use Easybook\Events\EasybookEvents;
use Easybook\Events\ParseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Yaml\Yaml;
use Trefoil\Events\TrefoilEvents;
use Trefoil\Util\Toolkit;

/**
 * Several tweaks to the final HTML of the ebook.
 * It allows modifying the HTML produced by the Markdown processor or by the
 * templates to follow certain rules or comply with certain requirements.
 *
 * Examples:
 *     - Surround all '<pre>...</pre>' tags with '<div class="box">...</div>'
 *     - Append '<hr>' tag to each '<h2>' tag.
 *
 * The changes are read from a 'html-tweaks.yml' file, that could be located:
 *     - In the book /Contents directory
 *     - In the theme /Config/<format> directory
 * The first one found will be used.
 *
 * Expected definition:
 *
 *     tweaks:
 *       onPreParse:        # replacements to be made at onPreParse time
 *         tweak-name-free:                                 # just a name
 *           tag: 'div'                                     # tag name to find
 *           class: 'class-name'                            # OPTIONAL class name assigned to tag
 *
 *           # either one of 'insert', 'surround' or 'replace' operations
 *
 *           insert:         # insert HTML inside the tag (surrounding its contents)
 *              open:  '<span class="my-class">===</span>'  # opening text
 *              close: '<span class="my-class">####</span>' # closing text
 *
 *           surround:       # surround the tag (not only its contents) with HTML code
 *              open:  '<div class="other-class">'          # opening text
 *              close: '</div>'                             # closing text
 *
 *           replace:        # replace tag with another one
 *              tag:  'h2'                                  # replacement tag
 *
 *       onPostParse:        # replacements to be made at onPostParse time
 *         another-tweak-name:
 *           tag: 'span'
 *           ----
 *
 *  The 'onPreParse' tweaks will be made before the Markdown parser has processed the item
 *  (so they can easyly pick any raw HTML embedded into the Markdown text), while the 'onPostParse'
 *  tweaks will work on the HTML produced by the Markdown processor.
 */
class HtmlTweaksPlugin extends BasePlugin implements EventSubscriberInterface
{
    protected $tweaks = array();

    public static function getSubscribedEvents()
    {
        return array(
            TrefoilEvents::PRE_PUBLISH_AND_READY => 'onPrePublishAndReady',
            EasybookEvents::PRE_PARSE            => array('onItemPreParse', -1000),
            EasybookEvents::POST_PARSE           => array('onItemPostParse', -1000)
        );
    }

    public function onPrePublishAndReady(BaseEvent $event)
    {
        $this->init($event);

        // read tweaks defined in file
        $this->readTweaksFile();
    }

    public function onItemPreParse(BaseEvent $event)
    {
        $this->init($event);

        $content = $event->getItemProperty('original');

        if (isset($this->tweaks['tweaks']['onPreParse'])) {
            $content = $this->processTweaks($content, $this->tweaks['tweaks']['onPreParse']);
        }

        $event->setItemProperty('original', $content);
    }

    public function onItemPostParse(ParseEvent $event)
    {
        $this->init($event);

        $content = $event->getItemProperty('content');

        if (isset($this->tweaks['tweaks']['onPostParse'])) {
            $content = $this->processTweaks($content, $this->tweaks['tweaks']['onPostParse']);
        }

        $event->setItemProperty('content', $content);
    }

    /**
     * Read the Yml file with the tweaks' options
     */
    protected function readTweaksFile()
    {
        // first path is the book Contents dir
        $bookDir = $this->app['publishing.dir.book'];
        $contentsDir = $bookDir . '/Contents';

        // second path is the theme /Config dir
        $themeDir = Toolkit::getCurrentThemeDir($this->app);
        $configDir = sprintf('%s/%s/Config', $themeDir, $this->format);

        // look for either one
        $dirs = array($contentsDir, $configDir);
        $file = $this->app->getFirstExistingFile('html-tweaks.yml', $dirs);

        if (!$file) {
            $this->writeLn('No html-tweaks.yml file found. Looked up directories:', 'error');
            foreach ($dirs as $dir) {
                $this->writeLn('- ' . $dir);
            }

            return;
        }

        $this->tweaks = Yaml::parse($file);
    }

    /**
     * Process all tweaks
     *
     * @param string $content
     * @param array  $tweaks
     *
     * @return string
     */
    protected function processTweaks($content, array $tweaks)
    {
        foreach ($tweaks as $tweak) {
            $content = $this->processTweak($content, $tweak);
        }

        return $content;
    }

    /**
     * Process one tweak
     *
     * @param string $content
     * @param array  $tweak
     *
     * @return string
     */
    protected function processTweak($content, $tweak)
    {

        $noclose = false;

        if ('hr' == $tweak['tag']) {

            // <hr> tags are special (no closing tag)
            $noclose = true;

            $pattern = '/';
            $pattern .= '<' . $tweak['tag'] . ' *(?<attrs>.*) *\/>';
            $pattern .= '/Ums';
        } else {
            $pattern = '/';
            $pattern .= '<' . $tweak['tag'] . ' *(?<attrs>.*)>';
            $pattern .= '(?<inner>.*)';
            $pattern .= '<\/' . $tweak['tag'] . '>';
            $pattern .= '/Ums';
        }

        $content = preg_replace_callback(
            $pattern,
            function ($matches) use ($tweak, $noclose) {
                $found = true;

                // lookup with or without class?
                if (isset($tweak['class'])) {
                    if (preg_match('/class="(?<classes>.*)"/Ums', $matches['attrs'], $matches2)) {
                        $classes = explode(' ', $matches2['classes']);
                        if (!in_array($tweak['class'], $classes)) {
                            $found = false;
                        }
                    } else {
                        $found = false;
                    }
                }

                $newTag = sprintf(
                    '<%s %s>%s</%s>',
                    $tweak['tag'],
                    $matches['attrs'],
                    $matches['inner'],
                    $tweak['tag']
                );

                if ($found) {
                    if (isset($tweak['insert'])) {
                        $newTag = sprintf(
                            '<%s %s>%s%s%s</%s>',
                            $tweak['tag'],
                            $matches['attrs'],
                            str_replace('\n', "\n", $tweak['insert']['open']),
                            $matches['inner'],
                            str_replace('\n', "\n", $tweak['insert']['close']),
                            $tweak['tag']
                        );

                    } elseif (isset($tweak['surround'])) {
                        $newTag = sprintf(
                            '%s<%s %s>%s</%s>%s',
                            str_replace('\n', "\n", $tweak['surround']['open']),
                            $tweak['tag'],
                            $matches['attrs'],
                            $matches['inner'],
                            $tweak['tag'],
                            str_replace('\n', "\n", $tweak['surround']['close'])
                        );

                    } elseif (isset($tweak['replace'])) {
                        if ($noclose) {
                            $newTag = sprintf(
                                '<%s %s />',
                                str_replace('\n', "\n", $tweak['replace']['tag']),
                                $matches['attrs']
                            );
                        } else {
                            $newTag = sprintf(
                                '<%s %s>%s</%s>',
                                str_replace('\n', "\n", $tweak['replace']['tag']),
                                $matches['attrs'],
                                $matches['inner'],
                                str_replace('\n', "\n", $tweak['replace']['tag'])
                            );
                        }
                    }
                }

                return $newTag;
            },
            $content
        );

        return $content;
    }
}
