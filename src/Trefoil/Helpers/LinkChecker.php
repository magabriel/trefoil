<?php
namespace Trefoil\Helpers;

/**
 * Check external links
 */
class LinkChecker
{
    public function check($url)
    {
        if (empty($url)) {
            throw new \InvalidArgumentException('Url not set');
        }

        // Make Sure We Have protocol added to the URL
        if (stripos($url, "http://") === false && stripos($url, "https://") === false) {
            $url = 'http://' . $url;
        }

        /* @see http://www.php.net/manual/es/function.fopen.php#95455 */
        ini_set('user_agent', 'Mozilla/5.0 (X11; Linux i686; rv:12.0) Gecko/20100101 Firefox/12.0');

        $rc = '';
        $msg = 'Invalid host';

        $f = @fopen(html_entity_decode($url), "r");
        if ($f) {
            fclose($f);
        }

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
            throw new \Exception(sprintf('%s %s', $msg, $rc));
        }

        return true;
    }
}
