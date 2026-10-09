<?php

declare(strict_types=1);

use MCMS\Core\Admin\AdminRegistry;
use PHPUnit\Framework\TestCase;

final class AdminRegistryTest extends TestCase
{
    public function testMenuAndWidgetsAreOrdered(): void
    {
        $registry = new AdminRegistry();

        $registry->addMenu('later', 'Later', '/later', order: 90);
        $registry->addMenu('first', 'First', '/first', order: 10);
        $registry->addWidget('later', 'later', order: 50);
        $registry->addWidget('first', 'first', order: 5);

        self::assertSame(['first', 'later'], array_column($registry->menu(), 'id'));
        self::assertSame(['first', 'later'], array_column($registry->widgets(), 'id'));
    }

    public function testReplacingSameIdDoesNotDuplicateContribution(): void
    {
        $registry = new AdminRegistry();
        $registry->addMenu('example', 'Old', '/old');
        $registry->addMenu('example', 'New', '/new');

        self::assertCount(1, $registry->menu());
        self::assertSame('New', $registry->menu()[0]['label']);
    }
    public function testSettingsAliasesPreserveSuffixesOnlyForTheirOwnCentralSection(): void
    {
        $registry = new AdminRegistry();
        $registry->addSettings('general', 'General', '/admin/settings', 'ti ti-settings', 'core.settings', 'general');
        $registry->addSettings('seo', 'SEO', '/admin/seo', 'ti ti-link', 'seo.manage', 'content');
        self::assertSame('/admin/settings/seo', $registry->settings()[1]['centralUrl']);
        self::assertSame('/admin/settings/seo/settings', $registry->settingsPath('/admin/seo/settings', '/admin/settings/seo/settings'));
        self::assertSame('/admin/settings/seo?edit=old#redirects', $registry->settingsPath('/admin/seo?edit=old#redirects', '/admin/settings/seo'));
        self::assertSame('/admin/seo/settings', $registry->settingsPath('/admin/seo/settings', '/admin/seo'));
        self::assertSame('/admin/seo-other', $registry->settingsPath('/admin/seo-other', '/admin/settings/seo'));
        self::assertSame('/admin/users', $registry->settingsPath('/admin/users', '/admin/settings/seo'));
        self::assertSame('/admin/seo', $registry->settingsPath('/admin/seo', '/admin/settings/seo-other'));
    }

}
