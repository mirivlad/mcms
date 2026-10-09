<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Settings\Settings;
use MCMS\Core\Admin\AdminRegistry;
use MCMS\Core\Install\InstallationService;
use MCMS\Core\Modules\ModuleManager;
use MCMS\Core\Modules\ModuleServiceRegistry;
use MCMS\Core\Navigation\NavigableResourceRegistry;
use RuntimeException;
use Throwable;

/** Explicitly opted-in, additive demo content for one installation. */
final class DemoData
{
    public const MARKER = 'Core.demoSeedCompletedAt';

    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function seed(): string
    {
        $runtime = service('mcmsModuleRuntime');
        if ($runtime->maintenance() !== null) {
            return 'module-maintenance';
        }
        if (getenv('MCMS_DEMO_SEED') !== '1') {
            return 'disabled';
        }
        if (! (new InstallationService($this->db))->isInstalled()) {
            return 'waiting-for-installation';
        }

        if (! $runtime->isExclusive()) {
            return $runtime->exclusive(fn () => $this->seed());
        }

        // Serialize startup and installer requests in the supported single-app stack.
        $lock = fopen(WRITEPATH . 'demo-seed.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX)) {
            throw new RuntimeException('Cannot lock demo-data initialization.');
        }
        try {
            // A fresh Settings handler sees completion recorded by a previous process.
            $settings = new Settings(config('Settings'));
            if ($settings->get(self::MARKER) !== null) {
                return 'already-completed';
            }

            $services = new ModuleServiceRegistry();
            $navigation = new NavigableResourceRegistry();
            $modules = new ModuleManager($this->db, new AdminRegistry(), $services, ROOTPATH . 'modules', $navigation, WRITEPATH . 'modules/installed', $runtime);
            foreach (['pages', 'news', 'catalogue', 'seo'] as $module) {
                $modules->enable($module);
            }
            $modules->bootEnabled();
            $userId = (int) $this->db->table('auth_groups_users')->where('group', 'superadmin')->get()->getRowArray()['user_id'];

            // Module migrations run first: MariaDB DDL must not commit this content transaction.
            $this->db->transBegin();
            try {
                $this->content($services, $userId);
                $settings->set(self::MARKER, date('Y-m-d H:i:s'));
                if (! $this->db->transStatus()) {
                    throw new RuntimeException('Demo-data transaction failed.');
                }
                $this->db->transCommit();
            } catch (Throwable $e) {
                $this->db->transRollback();
                throw $e;
            }
            return 'completed';
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function content(ModuleServiceRegistry $services, int $userId): void
    {
        $pages = $services->get('pages.repository');
        $news = $services->get('news.repository');
        $products = $services->get('catalogue.repository');
        $categories = $services->get('catalogue.categories');
        $images = $services->get('product-images.library');
        $redirects = $services->get('seo.redirects');
        $note = '<p><em>Демонстрационные данные mCMS. Условия и цены приведены для тестирования.</em></p>';

        $existingPages = $this->slugs($pages);
        foreach ([
            ['about', 'О проекте', '<h2>Мастерская повседневных вещей</h2><p>Здесь собраны полезные вещи для дома, работы и небольших путешествий. Мы выбираем простые материалы и понятные формы.</p><h2>Что посмотреть</h2><p>Откройте <a href="/shop">каталог товаров</a> и <a href="/news">новости</a>, попробуйте поиск и переключение тем.</p>'],
            ['delivery', 'Доставка', '<h2>Способы получения</h2><ul><li>Самовывоз из мастерской.</li><li>Курьерская доставка по городу.</li><li>Отправка в другие регионы.</li></ul><p>В этом демо заказ и доставка не оформляются: страница показывает пример информационного раздела.</p>'],
            ['payment', 'Оплата', '<h2>Пример условий оплаты</h2><p>В реальном магазине здесь размещаются доступные способы оплаты, условия возврата и реквизиты продавца.</p><p>Демонстрационный сайт не принимает платежи.</p>'],
            ['contacts', 'Контакты', '<h2>Связаться с мастерской</h2><p>Почта: hello@example.test</p><p>Адрес: Демонстрационная улица, дом 1.</p><p>Время работы: понедельник — пятница, с 10:00 до 18:00.</p>'],
            ['faq', 'Частые вопросы', '<h2>Как выбрать вариант товара?</h2><p>Откройте карточку и выберите размер или комплектацию. Цена и артикул изменятся вместе с вариантом.</p><h2>Можно ли оформить заказ?</h2><p>Этот набор предназначен для проверки каталога и контента. Покупки здесь не оформляются.</p>'],
        ] as [$slug, $title, $body]) {
            $slug = 'demo-' . $slug;
            if (! isset($existingPages[$slug])) {
                $pages->create(['title' => $title . ' (демо)', 'slug' => $slug, 'body' => $body . $note,
                    'status' => 'published', 'tags' => 'демо, информация'], $userId);
            }
        }

        $existingNews = $this->slugs($news);
        foreach ([
            ['opening', 'Добро пожаловать в мастерскую', 'Мы открываем демонстрационную витрину: новости, страницы и каталог теперь можно посмотреть на одном сайте.', 'published'],
            ['new-collection', 'Новая коллекция для рабочего стола', 'Блокноты, карандаши и настольные аксессуары помогают собрать удобное рабочее место. В каталоге есть несколько вариантов размеров и комплектаций.', 'published'],
            ['materials', 'Как мы выбираем материалы', 'Керамика, хлопок и дерево хорошо сочетаются в повседневных вещах. В карточках товаров можно сравнить описания, цены и доступные варианты.', 'published'],
            ['weekend', 'Идеи для спокойных выходных', 'Возьмите любимую кружку, небольшой блокнот и термос. Иногда для хорошего выходного достаточно прогулки и пары записей о том, что удалось заметить.', 'published'],
            ['care', 'Как ухаживать за любимыми вещами', 'Керамическую посуду бережно мойте мягкой губкой, текстиль сушите расправленным, а деревянные поверхности протирайте слегка влажной тканью.', 'published'],
            ['draft', 'Анонс будущей коллекции', 'Этот материал оставлен черновиком: он виден в админке, но не должен появляться на публичном сайте.', 'draft'],
        ] as $index => [$slug, $title, $text, $status]) {
            $slug = 'demo-' . $slug;
            if (! isset($existingNews[$slug])) {
                $news->create(['title' => $title . ' (демо)', 'slug' => $slug, 'excerpt' => $text,
                    'body' => '<h2>' . $title . '</h2><p>' . $text . '</p>' . $note, 'status' => $status,
                    'published_at' => date('Y-m-d H:i:s', time() - (6 - $index) * 86400), 'tags' => 'демо, мастерская'], $userId);
            }
        }

        $categoryIds = [];
        foreach ([
            ['home', 'Для дома (демо)', null, 'home', '#206bc4'],
            ['ceramics', 'Керамика (демо)', 'home', 'cup', '#c76b3d'],
            ['textiles', 'Текстиль (демо)', 'home', 'shirt', '#8756a8'],
            ['work', 'Работа и путешествия (демо)', null, 'briefcase', '#26856b'],
            ['stationery', 'Канцелярия (демо)', 'work', 'pencil', '#d08d20'],
            ['travel', 'Для прогулок (демо)', 'work', 'backpack', '#3185a1'],
        ] as [$slug, $name, $parent, $icon, $color]) {
            $category = $categories->bySlug('demo-' . $slug);
            $category ??= $categories->save(null, ['name' => $name, 'slug' => 'demo-' . $slug,
                'parent_id' => $parent === null ? null : $categoryIds[$parent], 'icon' => $icon, 'icon_color' => $color]);
            $categoryIds[$slug] = $category['id'];
        }

        $existingProducts = $this->slugs($products);
        foreach ([
            ['ceramic-mug', 'Керамическая кружка', 'ceramics', 'Кружка с удобной ручкой и матовой глазурью.', '890.00', '350 мл', '1190.00', '500 мл'],
            ['bowl', 'Чаша для завтрака', 'ceramics', 'Глубокая чаша для каши, фруктов и небольших салатов.', '1250.00', '', null, ''],
            ['plate', 'Тарелка ручной работы', 'ceramics', 'Плоская тарелка с мягким краем и тёплым оттенком.', '1590.00', '20 см', '2190.00', '26 см'],
            ['cotton-bag', 'Хлопковая сумка', 'textiles', 'Лёгкая сумка для покупок и ежедневных прогулок.', '650.00', 'Натуральный', '690.00', 'Тёмный'],
            ['linen-towel', 'Льняное полотенце', 'textiles', 'Фактурное полотенце для кухни, 45 × 65 см.', '780.00', '', null, ''],
            ['pillow-cover', 'Чехол для подушки', 'textiles', 'Хлопковый чехол со скрытой молнией.', '1450.00', '40 × 40 см', '1750.00', '50 × 50 см'],
            ['notebook', 'Блокнот для заметок', 'stationery', 'Плотная бумага, мягкая обложка и прошитый переплёт.', '420.00', 'A6', '690.00', 'A5'],
            ['pencils', 'Набор карандашей', 'stationery', 'Шесть графитовых карандашей для рисунков и записей.', '340.00', '', null, ''],
            ['desk-tray', 'Настольный органайзер', 'stationery', 'Небольшой деревянный лоток для канцелярии и мелочей.', '1890.00', '', null, ''],
            ['thermos', 'Термос для прогулок', 'travel', 'Стальной термос с герметичной крышкой.', '2490.00', '500 мл', '2990.00', '750 мл'],
            ['travel-pouch', 'Дорожный чехол', 'travel', 'Чехол на молнии для кабелей и небольших аксессуаров.', '990.00', '', null, ''],
            ['picnic-set', 'Набор для пикника', 'travel', 'Пример товара в черновике для проверки публикации.', '3990.00', '', null, ''],
        ] as [$slug, $title, $category, $summary, $price, $label, $secondPrice, $secondLabel]) {
            $sku = 'MCMS-DEMO-' . strtoupper($slug);
            $slug = 'demo-' . $slug;
            if (isset($existingProducts[$slug])) {
                continue;
            }
            $variants = [['sku' => $sku . '-1', 'label' => $label, 'price' => $price]];
            if ($secondPrice !== null) {
                $variants[] = ['sku' => $sku . '-2', 'label' => $secondLabel, 'price' => $secondPrice];
            }
            $products->save(null, ['title' => $title . ' (демо)', 'slug' => $slug, 'summary' => $summary,
                'body' => '<p>' . $summary . '</p>' . $note, 'category_id' => $categoryIds[$category],
                'status' => $slug === 'demo-picnic-set' ? 'draft' : 'published',
                'image_set_id' => $images->createSet($userId), 'variants' => $variants], $userId);
        }

        foreach (['/demo-about-old.html' => '/demo-about', '/demo-delivery-old.html' => '/demo-delivery'] as $source => $target) {
            if ($redirects->find($source) === null && $pages->findPublishedBySlug(ltrim($target, '/')) !== null) {
                $redirects->save($source, $target);
            }
        }
    }

    private function slugs(object $repository): array
    {
        $slugs = [];
        $offset = 0;
        do {
            $rows = $repository->list('', null, 100, $offset);
            foreach ($rows as $row) {
                $slugs[$row['slug']] = true;
            }
            $offset += 100;
        } while (count($rows) === 100);
        return $slugs;
    }
}
