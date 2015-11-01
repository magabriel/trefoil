<?php
/*
 * This file is part of the trefoil application.
 *
 * (c) Miguel Angel Gabriel <magabriel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Trefoil\Plugins\Optional;

use Easybook\Events\EasybookEvents;
use Easybook\Events\ParseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Trefoil\Plugins\BasePlugin;

/**
 * Manage the illustrations in the book item.
 *
 * An illustration is a block delimited by '<<' and '<</' marks.
 *
 * Expected syntax:
 *
 * << ========= "This is the illustration caption" =========
 * . . . whatever Markdown or HTML content
 * <</ =================
 *
 * where the '=' in the opening and closing block marks are optional, just to visually
 * delimit the illustration.
 *
 * ATX-style headers can be used inside of the illustration content and
 * will not be parsed by easybook (i.e. not added labels and ignored in the TOC).
 *
 * WARNINGS:
 *
 *  - When enabled, this functionality will take over the "tables" numbering
 *    and listing of easybook. If a table is needed as an illustration it will
 *    need to be done with this new markup.
 *    Ordinary tables (outside an illustration markup) will be ignored and just
 *    parsed as Markdown tables, not easybook tables.
 *
 *  - It needs the "lot.twig" templates present in the theme if used.
 *
 *  - Illustration HTML can be customized with "illustration.twig" template, but
 *    the plugin will apply a default HTML if the template is not present.
 *
 */
class IllustrationsPlugin extends BasePlugin implements EventSubscriberInterface
{
    protected $illustrations = [];

    public static function getSubscribedEvents()
    {
        return [
            EasybookEvents::PRE_PARSE  => ['onItemPreParse', -100],
            EasybookEvents::POST_PARSE => ['onItemPostParse', -1100]
        ];
    }

    /**
     * @param ParseEvent $event
     */
    public function onItemPreParse(ParseEvent $event)
    {
        $this->init($event);

        $content = $event->getItemProperty('original');

        $content = $this->parseAndRenderIllustrations($content);

        $event->setItemProperty('original', $content);
    }

    public function onItemPostParse(ParseEvent $event)
    {
        $this->init($event);

        $content = $event->getItemProperty('content');

        $this->replaceTablesWithIllustrations();

        $event->setItemProperty('content', $content);
    }

    /**
     * Parse illustrations and convert them to HTML.
     *
     * @param $content
     *
     * @return mixed
     */
    protected function parseAndRenderIllustrations($content)
    {
        $this->app['publishing.active_item.illustrations'] = [];

        $addIllustrationsLabels = in_array('illustration', $this->app->edition('labels') ?: array());
        $listOfTables = [];
        $parentItemNumber = $this->item['config']['number'];
        $counter = 0;

        $regExp = '/';
        $regExp .= '^<<'; // block opening
        $regExp .= '(?<p1> +=+)?'; // caption previous delimiter (optional)
        $regExp .= ' +"(?<caption>.*)"'; // caption
        $regExp .= '(?<p2>.*$)'; // caption post delimiter (optional)
        $regExp .= '(?<data>.*)'; // text inside the block
        $regExp .= '^<<\/'; // block closing
        $regExp .= '(?<p3>.*)?$'; // closing delimiter (optional)
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $content = preg_replace_callback(
            $regExp,
            function ($matches) use ($parentItemNumber, &$addIllustrationsLabels, &$listOfTables, &$counter) {

                $caption = $matches['caption'];

                $data = $this->preProcessHeaders($matches['data']);

                $counter++;

                $slug = $this->app->slugify('Illustration ' . $parentItemNumber . '-' . $counter);

                $parameters = array(
                    'item'    => array(
                        'caption' => '',
                        'content' => $data,
                        'label'   => '',
                        'number'  => $counter,
                        'slug'    => $slug,
                    ),
                    'element' => array(
                        'number' => $parentItemNumber,
                    ),
                );

                // the publishing edition wants to label illustrations
                $label = '';
                if ($addIllustrationsLabels) {
                    if (isset($this->app['labels']['label']['illustration'])) {
                        $label = $this->app->getLabel('illustration', $parameters);
                    } else {
                        $label = $this->app->getLabel('table', $parameters);
                    }
                    $parameters['item']['label'] = $label;
                    $parameters['item']['caption'] = $caption;
                }

                $listOfTables[] = $parameters;

                try {
                    // render with a template
                    return $this->app->render('illustration.twig', $parameters);
                } catch (\Twig_Error_Loader $e) {
                    // render anyway with a string
                    return sprintf(
                        '<div class="illustration" markdown="1" id="%s"><blockquote markdown="1">' .
                        '<h6>%s%s</h6><hr/>%s' .
                        '</blockquote></div>',
                        $slug,
                        $label ? $label . ' - ' : '',
                        $caption,
                        $data
                    );
                }
            },
            $content
        );

        $this->app['publishing.active_item.illustrations'] = $listOfTables;

        return $content;
    }

    /**
     * Convert the ATX Markdown headers inside the illustration to HTML
     * to keep easybook from parsing them (so it does not add labels and ids).
     *
     * @param $content
     *
     * @return mixed
     */
    protected function preProcessHeaders($content)
    {
        $regExp = '/';
        $regExp .= '^(?<atx>#{5,6}) '; // atx header
        $regExp .= '(?<htext>.*)$'; // header text/rest
        $regExp .= '/Ums'; // Ungreedy, multiline, dotall

        $content = preg_replace_callback(
            $regExp,
            function ($matches) {

                $level = strlen($matches['atx']);

                $html = sprintf(
                    '<h%s>%s</h%s>',
                    $level,
                    $matches['htext'],
                    $level
                );

                return $html;
            },
            $content
        );

        return $content;
    }

    /**
     * Replace all tables (in the internal list of tables) in the current item
     * with the detected illustrations.
     */
    protected function replaceTablesWithIllustrations()
    {
        $newTables = [];

        // filter out the tables of this item
        foreach ($this->app['publishing.list.tables'] as $itemTables) {
            $newItemTables = [];

            foreach ($itemTables as $table) {
                if ($table['element']['number'] !== $this->item['config']['number']) {
                    $newItemTables[] = $table;
                }
            }

            if (count($newItemTables)) {
                $newTables[] = $newItemTables;
            }
        }

        // and append the illustrations of this item
        $this->app['publishing.list.tables'] = $newTables;
        $this->app->append('publishing.list.tables', array_values($this->app['publishing.active_item.illustrations']));
    }

}
