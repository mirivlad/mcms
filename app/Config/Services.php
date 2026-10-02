<?php

namespace Config;

use CodeIgniter\Config\BaseService;
use MCMS\Core\Admin\AdminRegistry;
use MCMS\Core\Auth\AccessManager;
use MCMS\Core\Install\InstallationService;
use MCMS\Core\Modules\ModuleManager;
use MCMS\Core\Modules\ModuleServiceRegistry;
use MCMS\Core\Navigation\NavigableResourceRegistry;
use MCMS\Core\Storage\LocalStorage;
use MCMS\Core\Storage\StorageInterface;
use MCMS\Core\Themes\ThemeManager;
use MCMS\Core\Content\ShortcodeRenderer;

class Services extends BaseService
{
    public static function mcmsShortcodes(bool $getShared = true): ShortcodeRenderer
    {
        if ($getShared) {
            return static::getSharedInstance('mcmsShortcodes');
        }
        return new ShortcodeRenderer(static::mcmsModuleServices());
    }

    public static function mcmsAdmin(bool $getShared = true): AdminRegistry
    {
        if ($getShared) {
            return static::getSharedInstance('mcmsAdmin');
        }

        $registry = new AdminRegistry();
        $registry->addMenu('dashboard', '@MCMS.nav.dashboard', '/admin', 'ti ti-home', 'core.admin', 10);
        $registry->addMenu('users', '@MCMS.nav.users', '/admin/users', 'ti ti-users', 'core.users', 30);
        $registry->addMenu('access', '@MCMS.nav.access', '/admin/access', 'ti ti-shield-lock', 'core.access', 40);
        $registry->addMenu('modules', '@MCMS.nav.modules', '/admin/modules', 'ti ti-plug', 'core.modules', 50);
        $registry->addMenu('themes', '@MCMS.nav.themes', '/admin/themes', 'ti ti-palette', 'core.themes', 65);
        $registry->addMenu('settings', '@MCMS.nav.settings', '/admin/settings', 'ti ti-settings', 'core.settings', 70);
        $registry->addMenu('system', '@MCMS.nav.system', '/admin/system', 'ti ti-activity', 'core.system', 90);

        return $registry;
    }

    public static function mcmsAccess(bool $getShared = true): AccessManager
    {
        if ($getShared) {
            return static::getSharedInstance('mcmsAccess');
        }

        return new AccessManager(db_connect());
    }

    public static function mcmsInstaller(bool $getShared = true): InstallationService
    {
        if ($getShared) {
            return static::getSharedInstance('mcmsInstaller');
        }

        return new InstallationService(db_connect());
    }

    public static function mcmsModuleServices(bool $getShared = true): ModuleServiceRegistry
    {
        if ($getShared) {
            return static::getSharedInstance('mcmsModuleServices');
        }

        return new ModuleServiceRegistry();
    }

    public static function mcmsNavigation(bool $getShared = true): NavigableResourceRegistry
    {
        if ($getShared) {
            return static::getSharedInstance('mcmsNavigation');
        }

        return new NavigableResourceRegistry();
    }

    public static function mcmsModules(bool $getShared = true): ModuleManager
    {
        if ($getShared) {
            return static::getSharedInstance('mcmsModules');
        }

        return new ModuleManager(
            db_connect(),
            static::mcmsAdmin(),
            static::mcmsModuleServices(),
            ROOTPATH . 'modules',
            static::mcmsNavigation(),
        );
    }

    public static function mcmsThemes(bool $getShared = true): ThemeManager
    {
        if ($getShared) {
            return static::getSharedInstance('mcmsThemes');
        }

        return new ThemeManager(static::settings());
    }

    public static function mcmsStorage(bool $getShared = true): StorageInterface
    {
        if ($getShared) {
            return static::getSharedInstance('mcmsStorage');
        }

        return new LocalStorage(WRITEPATH . 'storage');
    }
}
