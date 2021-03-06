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
namespace Trefoil\Helpers;

/**
 * Check external links
 */
class LinkChecker
{
    /**
     * @param string $url
     * @return bool
     */
    public function check(string $url): bool
    {
        if (empty($url)) {
            throw new \InvalidArgumentException('Url not set');
        }

        // Make Sure We Have protocol added to the URL
        if (stripos($url, 'http://') === false && stripos($url, 'https://') === false) {
            $url = 'http://' . $url;
        }

        $options = ['http' => ['user_agent' => 'Mozilla / 5.0 (X11; Linux i686; rv:12.0) Gecko / 20100101 Firefox / 12.0']];
        $context = stream_context_create($options);
        
        $rc = '';
        $msg = 'Invalid host';

        /** @noinspection PhpUsageOfSilenceOperatorInspection */
        $f = @fopen(html_entity_decode($url), 'r', false, $context);
        if ($f) {
            fclose($f);
        }

        if (isset($http_response_header[0])) {
            $parts = explode(' ', $http_response_header[0]);
            if (count($parts) > 1) {
                $rc = $parts[1];
                $msg = implode(' ', array_slice($parts, 2));
            } else {
                $msg = $parts[0];
            }
        }

        if (false === $f) {
            throw new \RuntimeException(sprintf('%s %s', $msg, $rc));
        }

        return true;
    }
}
