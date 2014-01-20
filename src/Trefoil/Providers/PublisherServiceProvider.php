<?php
/*
 * This file is part of the trefoil application.
 *
 * (c) Miguel Angel Gabriel <magabriel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Trefoil\Providers;

use Easybook\DependencyInjection\Application;
use Easybook\DependencyInjection\ServiceProviderInterface;
use Easybook\Publishers\HtmlChunkedPublisher;
use Easybook\Publishers\PdfPublisher;
use Trefoil\Publishers\Epub2Publisher;
use Trefoil\Publishers\HtmlPublisher;
use Trefoil\Publishers\MobiPublisher;

class PublisherServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['publisher'] =
            $app->share(
                function (Application $app) {
                    $outputFormat = $app->edition('format');

                    switch (strtolower($outputFormat)) {
                        case 'pdf':
                            $publisher = new PdfPublisher($app);
                            break;

                        case 'html':
                            $publisher = new HtmlPublisher($app);
                            break;

                        case 'html_chunked':
                            $publisher = new HtmlChunkedPublisher($app);
                            break;

                        case 'epub':
                            $publisher = new Epub2Publisher($app);
                            break;

                        case 'mobi':
                            $publisher = new MobiPublisher($app);
                            break;

                        default:
                            throw new \RuntimeException(sprintf(
                                                            'Unknown "%s" format for "%s" edition (allowed: "pdf", "html", "html_chunked", "epub", "mobi")',
                                                            $outputFormat,
                                                            $app['publishing.edition']
                                                        ));
                    }

                    if (true != $publisher->checkIfThisPublisherIsSupported()) {
                        throw new \RuntimeException(sprintf(
                                                        "Your system doesn't support publishing books with the '%s' format\n"
                                                        . "Check the easybook documentation to know the dependencies required by this format.",
                                                        $outputFormat
                                                    ));
                    }

                    return $publisher;
                }
            );
    }
}
