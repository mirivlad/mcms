<?php

declare(strict_types=1);

namespace MCMS\Modules\Media;

use CodeIgniter\Router\RouteCollection;
use MCMS\Core\Modules\ModuleContext;
use MCMS\Core\Modules\ModuleProviderInterface;
use MCMS\Modules\Media\Services\MediaLibrary;
use MCMS\Modules\Media\Services\MediaUsage;

final class Module implements ModuleProviderInterface
{
    public function boot(ModuleContext $context): void
    {
        $context->services->register('media.usage', new MediaUsage(db_connect()));
        $context->services->register(
            'media.library',
            new MediaLibrary(
                db_connect(),
                FCPATH . 'assets/uploads/media',
            ),
        );

        $context->services->register('media.theme_images', new \MCMS\Modules\Media\Services\ThemeImages($context->services->get('media.library')));

        $context->admin->addMenu(
            'media',
            '@MCMS.nav.media',
            '/admin/media',
            'ti ti-photo',
            'media.view',
            60,
            group: 'content',
        );
    }

    public function routes(RouteCollection $routes): void
    {
        $routes->group('admin/media', ['filter' => 'session'], static function (RouteCollection $routes): void {
            $routes->get('/', '\\MCMS\\Modules\\Media\\Controllers\\Admin::index');
            $routes->post('upload', '\\MCMS\\Modules\\Media\\Controllers\\Admin::upload');
            $routes->post('(:num)/metadata', '\\MCMS\\Modules\\Media\\Controllers\\Admin::updateMetadata/$1');
            $routes->post('(:num)/delete', '\\MCMS\\Modules\\Media\\Controllers\\Admin::delete/$1');
            $routes->match(['get', 'post'], 'settings', '\\MCMS\\Modules\\Media\\Controllers\\Admin::settings');
            $routes->get('api/files', '\\MCMS\\Modules\\Media\\Controllers\\Admin::apiFiles');
        });
    }
}
