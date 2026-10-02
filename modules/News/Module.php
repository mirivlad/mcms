<?php

declare(strict_types=1);

namespace MCMS\Modules\News;

use CodeIgniter\Router\RouteCollection;
use MCMS\Core\Content\HtmlSanitizer;
use MCMS\Core\Modules\ModuleContext;
use MCMS\Core\Modules\ModuleProviderInterface;
use MCMS\Modules\Media\Api\MediaLibraryInterface;
use MCMS\Modules\News\Navigation\NewsArchiveNavigationProvider;
use MCMS\Modules\News\Navigation\NewsArticleNavigationProvider;
use MCMS\Modules\News\Services\NewsRepository;
use RuntimeException;

final class Module implements ModuleProviderInterface
{
    public function boot(ModuleContext $context): void
    {
        $media = $context->services->get('media.library');
        if (! $media instanceof MediaLibraryInterface) {
            throw new RuntimeException(lang('News.Errors.mediaUnavailable'));
        }

        $repository = new NewsRepository(
            db_connect(),
            new HtmlSanitizer(),
            $media,
        );

        $context->services->register('news.repository', $repository);
        $context->services->register('news.search', new \MCMS\Modules\News\Services\PublicSearch($repository));
        $context->navigation->register(new NewsArchiveNavigationProvider());
        $context->navigation->register(new NewsArticleNavigationProvider($repository));

        $context->admin->addMenu(
            'news',
            '@News.Admin.menuLabel',
            '/admin/news',
            'ti ti-news',
            'news.view',
            57,
        );
    }

    public function routes(RouteCollection $routes): void
    {
        $routes->group('admin/news', ['filter' => 'session'], static function (RouteCollection $routes): void {
            $routes->get('/', '\\MCMS\\Modules\\News\\Controllers\\Admin::index');
            $routes->get('categories', '\\MCMS\\Modules\\News\\Controllers\\Admin::categories');
            $routes->post('categories/save', '\\MCMS\\Modules\\News\\Controllers\\Admin::saveCategory');
            $routes->post('categories/delete', '\\MCMS\\Modules\\News\\Controllers\\Admin::deleteCategory');
            $routes->match(['get', 'post'], 'create', '\\MCMS\\Modules\\News\\Controllers\\Admin::create');
            $routes->post('preview', '\\MCMS\\Modules\\News\\Controllers\\Admin::preview');
            $routes->match(['get', 'post'], 'draft', '\\MCMS\\Modules\\News\\Controllers\\Admin::draft');
            $routes->post('draft/discard', '\\MCMS\\Modules\\News\\Controllers\\Admin::discardDraft');
            $routes->get('(:num)/history', '\\MCMS\\Modules\\News\\Controllers\\Admin::history/$1');
            $routes->get('(:num)/history/(:num)', '\\MCMS\\Modules\\News\\Controllers\\Admin::revision/$1/$2');
            $routes->get('media', '\\MCMS\\Modules\\News\\Controllers\\Admin::media');
            $routes->match(['get', 'post'], '(:num)/edit', '\\MCMS\\Modules\\News\\Controllers\\Admin::edit/$1');
            $routes->post('(:num)/delete', '\\MCMS\\Modules\\News\\Controllers\\Admin::delete/$1');
        });

        $routes->get('news', '\\MCMS\\Modules\\News\\Controllers\\News::index');
        $routes->get('news/(:segment)', '\\MCMS\\Modules\\News\\Controllers\\News::show/$1');
    }
}
