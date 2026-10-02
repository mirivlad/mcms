<?php

declare(strict_types=1);

namespace MCMS\Modules\Galleries;

use CodeIgniter\Router\RouteCollection;
use MCMS\Core\Modules\ModuleContext;
use MCMS\Core\Modules\ModuleProviderInterface;
use MCMS\Modules\Galleries\Navigation\GalleryArchiveNavigationProvider;
use MCMS\Modules\Galleries\Navigation\GalleryNavigationProvider;
use MCMS\Modules\Galleries\Services\GalleryRepository;
use MCMS\Modules\Media\Api\MediaLibraryInterface;
use RuntimeException;

final class Module implements ModuleProviderInterface
{
    public function boot(ModuleContext $context): void
    {
        $media = $context->services->get('media.library');
        if (! $media instanceof MediaLibraryInterface) {
            throw new RuntimeException(lang('Galleries.Errors.mediaUnavailable'));
        }
        $repository = new GalleryRepository(db_connect(), $media);
        $context->services->register('galleries.repository', $repository);
        $context->navigation->register(new GalleryArchiveNavigationProvider());
        $context->navigation->register(new GalleryNavigationProvider($repository));
        $context->admin->addMenu('galleries', '@Galleries.Admin.title', '/admin/galleries', 'ti ti-photo', 'galleries.view', 58);
    }

    public function routes(RouteCollection $routes): void
    {
        $routes->group('admin/galleries', ['filter' => 'session'], static function (RouteCollection $routes): void {
            $routes->get('/', '\\MCMS\\Modules\\Galleries\\Controllers\\Admin::index');
            $routes->match(['get', 'post'], 'create', '\\MCMS\\Modules\\Galleries\\Controllers\\Admin::create');
            $routes->match(['get', 'post'], '(:num)/edit', '\\MCMS\\Modules\\Galleries\\Controllers\\Admin::edit/$1');
            $routes->post('(:num)/delete', '\\MCMS\\Modules\\Galleries\\Controllers\\Admin::delete/$1');
            $routes->get('media', '\\MCMS\\Modules\\Galleries\\Controllers\\Admin::media');
        });
        $routes->get('galleries', '\\MCMS\\Modules\\Galleries\\Controllers\\Galleries::index');
        $routes->get('galleries/(:segment)', '\\MCMS\\Modules\\Galleries\\Controllers\\Galleries::show/$1');
    }
}
