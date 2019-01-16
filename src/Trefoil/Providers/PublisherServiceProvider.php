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

namespace Trefoil\Providers;

use Easybook\Publishers\HtmlChunkedPublisher;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Trefoil\DependencyInjection\Application;
use Trefoil\Publishers\Epub2Publisher;
use Trefoil\Publishers\HtmlPublisher;
use Trefoil\Publishers\MobiPublisher;
use Trefoil\Publishers\PdfPrinceXmlPublisher;
use Trefoil\Publishers\PdfWkhtmltopdfPublisher;

/**
 * Class PublisherServiceProvider
 *
 * @package Trefoil\Providers
 */
class PublisherServiceProvider implements ServiceProviderInterface
{
    /**
     * @param Container $app
     */
    public function register(Container $app): void
    {
        $app['publisher'] = function (Container $app) {
            /** @var Application $app */
            $outputFormat = $app->edition('format');

            switch (strtolower($outputFormat)) {
                case 'pdf':
                    $pdfEngine = $app->edition('pdf_engine') ?? '';

                    switch (strtolower($pdfEngine)) {
                        case 'wkhtmltopdf':
                            $publisher = new PdfWkhtmltopdfPublisher($app);
                            break;

                        // PrinceXML is the default
                        case 'princexml':
                        case '':
                        case null:
                            $publisher = new PdfPrinceXmlPublisher($app);
                            break;

                        default:
                            throw new \RuntimeException(
                                sprintf(
                                    'Unknown "%s" pdf_engine for "%s" edition (allowed: "PrinceXML" (default), "wkhtmltopdf")',
                                    $pdfEngine,
                                    $app['publishing.edition']));
                    }

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
                    throw new \RuntimeException(
                        sprintf(
                            'Unknown "%s" format for "%s" edition (allowed: "pdf", "html", "html_chunked", "epub", "mobi")',
                            $outputFormat,
                            $app['publishing.edition']));
            }

            if (true !== $publisher->checkIfThisPublisherIsSupported()) {
                throw new \RuntimeException(
                    sprintf(
                        "Your system doesn't support publishing books with the '%s' format\n".'Check the easybook documentation to know the dependencies required by this format.',
                        $outputFormat));
            }

            return $publisher;
        };
    }
}
