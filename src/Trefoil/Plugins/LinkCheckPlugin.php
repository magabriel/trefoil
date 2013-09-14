<?php
namespace Trefoil\Plugins;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Easybook\Events\EasybookEvents as Events;
use Easybook\Events\BaseEvent;
use Easybook\Events\ParseEvent;

class LinkCheckPlugin implements EventSubscriberInterface
{
    protected $app;
    protected $output;
    protected $item;

    protected static $externalLinks = array();

    public static function getSubscribedEvents()
    {
        return array(
                Events::PRE_DECORATE => array(
                        array(
                                'onItemPreDecorate',
                                -499 // before LinkPlugin
                        ),
                        array(
                                'onItemPreDecorateLast',
                                -501 // after LinkPlugin
                        )
                ),
                Events::POST_PARSE => array(
                        'onItemPostParse',
                        1000
                ),
                Events::POST_PUBLISH => 'onPostPublish'
        );
    }

    public function onItemPreDecorate(BaseEvent $event)
    {
        $this->app = $event->app;
        $this->output = $event->app->get('console.output');

        $this->item = $event->getItem();

        $this->app['book.logger']->debug('onItemPreDecorate:begin', get_class(), $this->item['config']['content']);

        $content = $this->item['content'];
        $this->checkInternalLinks($content);

        $this->app['book.logger']->debug('onItemPreDecorate:end', get_class(), $this->item['config']['content']);
    }

    public function onItemPostParse(BaseEvent $event)
    {
        $this->app = $event->app;
        $this->output = $this->app->get('console.output');
        $edition = $this->app['publishing.edition'];
        $this->item = $event->getItem();

        $this->app['book.logger']->debug('onItemPostParse:begin', get_class(), $this->item['config']['content']);

        $content = $event->getContent();

        if (isset($this->app->book('editions')[$edition]['check_external_links'])
            && $this->app->book('editions')[$edition]['check_external_links']) {
            $this->checkExternalLinks($content);
        }

        $this->app['book.logger']->debug('onItemPostParse:end', get_class(), $this->item['config']['content']);
    }

    public function onItemPreDecorateLast(BaseEvent $event)
    {
        $this->app = $event->app;
        $this->output = $this->app->get('console.output');

        $item = $event->getItem();
        $content = $item['content'];

        $this->app['book.logger']->debug('onItemPreDecorateLast:begin', get_class(), $this->item['config']['content']);

        $content = $this->fixInternalLinks($content);

        $item['content'] = $content;
        $event->setItem($item);

        $this->app['book.logger']->debug('onItemPreDecorate:end', get_class(), $this->item['config']['content']);
    }

    protected function fixInternalLinks($content)
    {
        $format = $this->app->edition('format');
        if (!in_array($format, array(
                'epub',
                'epub2'
        ))) {
            return $content;
        }

        // undo changes done by LinkCheckPlugin
        $content = preg_replace_callback('/<a (?<prev>.*)href="\.\/(?<link>.*)" ?(?<post>.*)>(?<text>.*)<\/a>/Us',
            function ($matches)
            {
                $link = sprintf('<a %s href="%s" %s>%s</a>', $matches['prev'],$matches['link'], $matches['post'], $matches['text']);
                return $link;
            }, $content);

        return $content;
    }

    protected function checkInternalLinks($content)
    {
        return;
        // TODO: Esto no funciona ya porque ahora no se utiliza 'publishing.links' para guardar los links
        // hay que hacer lo mismo que hace el LinkPlugin para obtener los links internos


        // only for html-based formats
        $format = $this->app->edition('format');
        if (!in_array($format,
            array(
                    'html_chunked',
                    'epub',
                    'epub2'
            ))) {
            return $content;
        }

        $links = $this->app->get('publishing.links');
        $errors = array();

        $content = preg_replace_callback('/<a href="(#.*)"(.*)<\/a>/Us',
            function ($matches) use (&$links, &$errors)
                      {
                      if (!isset($links[$matches[1]])) {
                      $errors[] = sprintf(" <error>Internal link target not found:</error> %s", $matches[1]);
                      $links[$matches[1]] = 'BAD-TARGET';
                      }

                      }, $content);

        if ($errors) {
            $errors[] = '';
            $this->output->writeLn($errors);
        }

        $this->app->set('publishing.links', $links);
    }

    protected function checkExternalLinks($content)
    {
        if (!$this->item['config']['content'] || !$content) {
            return;
        }

        $this->output->writeLn('');
        $this->output->writeLn(sprintf(' Processing element "%s" ', $this->item['config']['content']));
        $this->output->writeLn(' ' . str_repeat('-', 50));

        $hasLinks = false;

        $content = preg_replace_callback('/<a .*href="([^#].*)".*>(.*)<\/a>/Us',
            function ($matches) use (&$errors, &$hasLinks)
                      {
                      $hasLinks = true;
                      !$this->checkValidLink($matches[1], $matches[2]);
                      }, $content);

        if (!$hasLinks) {
            $this->output->writeLn(' > No links');
        }
    }

    protected function checkValidLink($url, $linkText = '')
    {
        if (empty($url)) {
            return false;
        }

        // Make Sure We Have protocol added to the URL
        if (stripos($url, "http://") === false && stripos($url, "https://") === false) {
            $url = 'http://' . $url;
        }

        $this->output->writeLn(sprintf(' > Checking link "%s" => ', $linkText));
        $this->output->write(sprintf('     %s ...', $url));

        /* @see http://www.php.net/manual/es/function.fopen.php#95455 */
        ini_set('user_agent', 'Mozilla/5.0 (X11; Linux i686; rv:12.0) Gecko/20100101 Firefox/12.0');

        $f = @fopen(html_entity_decode($url), "r");
        if ($f) {
            fclose($f);
        }

        $rc = '';
        $msg = '';

        if (isset($http_response_header) && isset($http_response_header[0])) {
            $parts = explode(' ', $http_response_header[0]);
            if (count($parts) > 1) {
                $rc = $parts[1];
                $msg = implode(' ', array_slice($parts, 2));
            } else {
                $msg = $parts[0];
            }
        }

        if (false == $f) {
            $this->output->writeLn(sprintf('<error>Error: %s %s</error>', $rc, $msg));
            $this->saveLinkData($url, $linkText, $rc . ' ' . $msg);
            return false;
        }

        $this->output->writeLn('<info>OK</info>');
        $this->saveLinkData($url, $linkText, 'OK');

        return true;
    }

    protected function saveLinkData($url, $linkText, $status)
    {
        $linkData = array();

        $linkData['url'] = $url;
        $linkData['linkText'] = $linkText;
        $linkData['status'] = $status;

        $element = $this->item['config']['content'];
        if (!isset(static::$externalLinks[$element])) {
            static::$externalLinks[$element] = array();
        }

        static::$externalLinks[$element][] = $linkData;
    }

    public function onPostPublish(BaseEvent $event)
    {
        $this->app = $event->app;
        $this->output = $event->app->get('console.output');

        if (static::$externalLinks) {
            // create the report
            $outputDir = $this->app['publishing.dir.output'];
            $reportFile = $outputDir . '/report-link-check-external.txt';

            $report = '';
            $report .= $this->getExternalLinksReport(false);
            $report .= "\n\n";
            $report .= $this->getExternalLinksReport(true);

            file_put_contents($reportFile, $report);
        }
    }

    protected function getExternalLinksReport($ok = true)
    {
        $report = array();

        $report[] = 'External links report ' . ($ok ? '(Correct)' : '(Errors)');
        $report[] = '=======================================';
        $report[] = '';

        $report[] = $this->utf8Sprintf('%-20s %-100s', 'Status', 'Text / <URL>');
        $report[] = $this->utf8Sprintf("%'--20s %'--100s", '', '');

        foreach (static::$externalLinks as $element => $elementLinks) {
            $rep = array();
            $rep[] = 'Element: ' . $element;
            $rep[] = '';
            $count = 0;
            foreach ($elementLinks as $linkData) {
                if (($ok && 'OK' == $linkData['status']) || (!$ok && 'OK' != $linkData['status'])) {
                    $rep[] = $this->utf8Sprintf('%-20s %-100s', $linkData['status'], $linkData['linkText']);
                    if ($linkData['linkText'] != $linkData['url']) {
                        $rep[] = $this->utf8Sprintf('%-20s %-100s', '', '<' . $linkData['url'] . '>');
                    }
                    $count++;
                }
            }

            if ($count) {
                $report = array_merge($report, $rep);

                $report[] = '';
                $report[] = sprintf('   Total %s: %s', $element, $count);
                $report[] = '';
            }
        }

        return implode("\n", $report) . "\n";
    }

    protected function utf8Sprintf($format)
    {
        $args = func_get_args();

        for ($i = 1; $i < count($args); $i++) {
            $args[$i] = iconv('UTF-8', 'ISO-8859-15', $args[$i]);
        }

        return iconv('ISO-8859-15', 'UTF-8', call_user_func_array('sprintf', $args));
    }
}

