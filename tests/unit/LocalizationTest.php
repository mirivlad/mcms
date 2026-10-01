<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use MCMS\Core\Admin\AdminRegistry;

final class LocalizationTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        service('language')->setLocale('en');
        parent::tearDown();
    }

    public function testCoreSupportsEnglishAndRussian(): void
    {
        $app = config('App');

        self::assertContains('en', $app->supportedLocales);
        self::assertContains('ru', $app->supportedLocales);

        service('language')->setLocale('ru');

        self::assertSame('Настройки', lang('MCMS.nav.settings'));
        self::assertSame('Русский', lang('MCMS.locale.ru'));
    }

    public function testAdminRegistryResolvesLabelInActiveLocale(): void
    {
        service('language')->setLocale('ru');

        $registry = new AdminRegistry();
        $registry->addMenu('settings', '@MCMS.nav.settings', '/admin/settings');

        self::assertSame('Настройки', $registry->menu()[0]['label']);

        service('language')->setLocale('en');

        self::assertSame('Settings', $registry->menu()[0]['label']);
    }
}
