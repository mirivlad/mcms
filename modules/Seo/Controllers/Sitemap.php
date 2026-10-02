<?php

declare(strict_types=1);

namespace MCMS\Modules\Seo\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use MCMS\Core\Navigation\PublicResourceProviderInterface;
use XMLWriter;

final class Sitemap extends BaseController
{
    public function index(): ResponseInterface
    {
        $writer = new XMLWriter();
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElementNS(null, 'urlset', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $seen = [];
        $write = static function (string $path) use ($writer, &$seen): void {
            if (isset($seen[$path])) { return; }
            $seen[$path] = true;
            $writer->startElement('url');
            $writer->writeElement('loc', site_url(ltrim($path, '/')));
            $writer->endElement();
        };
        $write('/');
        foreach (service('mcmsNavigation')->all() as $provider) {
            if ($provider instanceof PublicResourceProviderInterface) {
                foreach ($provider->resources() as $resource) { $write($resource['url']); }
            }
        }
        $writer->endElement();
        $writer->endDocument();
        return $this->response->setContentType('application/xml')->setBody($writer->outputMemory());
    }
}
