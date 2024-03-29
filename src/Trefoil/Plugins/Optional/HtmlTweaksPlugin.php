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
use Symfony\Component\Yaml\Yaml;
use Trefoil\Events\TrefoilEvents;
use Trefoil\Plugins\BasePlugin;
use Trefoil\Util\Toolkit;

/**
 * Several tweaks to the final HTML of the ebook.
 * It allows modifying the HTML produced by the Markdown processor or by the
 * templates to follow certain rules or comply with certain requirements.
 * Examples:
 *     - Surround all '<pre>...</pre>' tags with '<div class="box">...</div>'
 *     - Append '<hr>' tag to each '<h2>' tag.
 * The changes are read from a 'html-tweaks.yml' file, that could be located:
 *     - In the book /Contents directory
 *     - In the theme <format>/Config directory
 *     - In the theme Common/Config directory
 * The first one found will be used.
 * Expected definition:
 *     tweaks:
 *       onPreParse:        # replacements to be made at onPreParse time
 *         tweak-name-free:                                 # just a name
 *           tag: 'div'                                     # tag name to find
 *           class: 'class-name'                            # OPTIONAL class name assigned to tag
 *           # either one of 'insert', 'surround' or 'replace' operations
 *           insert:         # insert HTML inside the tag (surrounding its contents)
 *              open:  '<span class="my-class">===</span>'  # opening text
 *              close: '<span class="my-class">####</span>' # closing text
 *           surround:       # surround the tag (not only its contents) with HTML code
 *              open:  '<div class="other-class">'          # opening text
 *              close: '</div>'                             # closing text
 *           replace:        # replace tag with another one
 *              tag:  'h2'                                  # replacement tag
 *       onPostParse:        # replacements to be made at onPostParse time
 *         another-tweak-name:
 *           tag: 'span'
 *           ----
 *  The 'onPreParse' tweaks will be made before the Markdown parser has processed the item
 *  (so they can easyly pick any raw HTML embedded into the Markdown text), while the 'onPostParse'
 *  tweaks will work on the HTML produced by the Markdown processor.
 */
class HtmlTweaksPlugin extends BasePlugin implements EventSubscriberInterface
{
    protected $tweaks = [];

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            TrefoilEvents::PRE_PUBLISH_AND_READY => 'onPrePublishAndReady',
            EasybookEvents::PRE_PARSE            => ['onItemPreParse', -1000],
            EasybookEvents::POST_PARSE           => ['onItemPostParse', -1000],
        ];
    }

    /**
     * @param BaseEvent $event
     */
    public function onPrePublishAndReady(BaseEvent $event)
    {
        $this->init($event);

        // read tweaks defined in file
        $this->readTweaksFile();
    }

    /**
     * @param ParseEvent $event
     */
    public function onItemPreParse(ParseEvent $event)
    {
        $this->init($event);

        $content = $event->getItemProperty('original');

        if (isset($this->tweaks['tweaks']['onPreParse'])) {
            $content = $this->processTweaks($content, $this->tweaks['tweaks']['onPreParse']);
        }

        $event->setItemProperty('original', $content);
    }

    /**
     * @param ParseEvent $event
     */
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
        $bookDir = $this->app['publishing.dir.book'];
        $themeDir = Toolkit::getCurrentThemeDir($this->app);

        // first path is the book Contents dir
        $contentsDir = $bookDir.'/Contents';

        // second path is the theme "<format>/Config" dir
        $configFormatDir = sprintf('%s/%s/Config', $themeDir, $this->format);

        // third path is the theme "Common/Config" dir
        $configCommonDir = sprintf('%s/Common/Config', $themeDir);

        // look for either one
        $dirs = [
            $contentsDir,
            $configFormatDir,
            $configCommonDir,
        ];

        $file = $this->app->getFirstExistingFile('html-tweaks.yml', $dirs);

        if (!$file) {
            $this->writeLn('No html-tweaks.yml file found. Looked up directories:', 'error');
            foreach ($dirs as $dir) {
                $this->writeLn('- '.$dir);
            }

            return;
        }

        $this->tweaks = Yaml::parse(file_get_contents($file));
    }

    /**
     * Process all tweaks
     *
     * @param string $content
     * @param array  $tweaks
     * @return string
     */
    protected function processTweaks($content,
                                     array $tweaks): string
    {
        foreach ($tweaks as $tweakName => $tweak) {

            $tweak['tweak-name'] = $tweakName;

            if (isset($tweak['tag'])) {
                $content = $this->processTweakTag($content, $tweak);
            } elseif (isset($tweak['regex'])) {
                $content = $this->processTweakRegex($content, $tweak);
            }
        }

        return $content;
    }

    /**
     * Process one tweak
     *
     * @param string $content
     * @param array  $tweak
     * @return string
     */
    protected function processTweakTag($content,
                                       $tweak): string
    {
        $noclose = false;

        if ('hr' === $tweak['tag']) {

            // <hr> tags are special (no closing tag)
            $noclose = true;

            $pattern = '/';
            $pattern .= '<'.$tweak['tag'].' *(?<attrs>.*) *\/>';
            $pattern .= '/Ums';
        } else {
            $pattern = '/';
            $pattern .= '<'.$tweak['tag'].' *(?<attrs>.*)>';
            $pattern .= '(?<inner>.*)';
            $pattern .= '<\/'.$tweak['tag'].'>';
            $pattern .= '/Ums';
        }

        $content = preg_replace_callback(
            $pattern,
            function ($matches) use
            (
                $tweak,
                $noclose
            ) {
                $found = true;

                // lookup with or without class?
                if (isset($tweak['class'])) {
                    if (preg_match('/class="(?<classes>.*)"/Ums', $matches['attrs'], $matches2)) {
                        $classes = explode(' ', $matches2['classes']);
                        if (!in_array($tweak['class'], $classes, true)) {
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
                    $tweak['tag']);

                if ($found) {
                    if (isset($tweak['insert'])) {
                        $newTag = sprintf(
                            '<%s %s>%s%s%s</%s>',
                            $tweak['tag'],
                            $matches['attrs'],
                            str_replace('\n', "\n", $tweak['insert']['open']),
                            $matches['inner'],
                            str_replace('\n', "\n", $tweak['insert']['close']),
                            $tweak['tag']);

                    } elseif (isset($tweak['surround'])) {
                        $newTag = sprintf(
                            '%s<%s %s>%s</%s>%s',
                            str_replace('\n', "\n", $tweak['surround']['open']),
                            $tweak['tag'],
                            $matches['attrs'],
                            $matches['inner'],
                            $tweak['tag'],
                            str_replace('\n', "\n", $tweak['surround']['close']));

                    } elseif (isset($tweak['replace'])) {
                        if ($noclose) {
                            $newTag = sprintf(
                                '<%s %s />',
                                str_replace('\n', "\n", $tweak['replace']['tag']),
                                $matches['attrs']);
                        } else {
                            $newTag = sprintf(
                                '<%s %s>%s</%s>',
                                str_replace('\n', "\n", $tweak['replace']['tag']),
                                $matches['attrs'],
                                $matches['inner'],
                                str_replace('\n', "\n", $tweak['replace']['tag']));
                        }
                    }
                }

                return $newTag;
            },
            $content);

        return $content;
    }

    /**
     * Process a regular expression tweak
     *
     * @param $content
     * @param $tweak
     * @return string
     */
    protected function processTweakRegex($content,
                                         $tweak): string
    {
        if (!isset($tweak['replace'])) {

            $this->writeLn(sprintf('Tweak "%s": missing "replace" expression.', $tweak['tweak-name']), 'error');

            return $content;
        }

        $content = preg_replace($tweak['regex'], $tweak['replace'], $content);

        return $content;
    }
}
