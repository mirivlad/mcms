<?php

namespace Config;

use CodeIgniter\Config\BaseService;
use App\Services\DemoData;
use MCMS\Core\Admin\AdminRegistry;
use MCMS\Core\Backup\BackupManager;
use MCMS\Core\Backup\RestoreManager;
use MCMS\Core\Auth\AccessManager;
use MCMS\Core\Install\InstallationService;
use MCMS\Core\Modules\ModuleManager;
use MCMS\Core\Modules\ModulePackages;
use MCMS\Core\Modules\ModuleRuntime;
use MCMS\Core\Modules\ModuleUpdates;
use MCMS\Core\Modules\ModuleServiceRegistry;
use MCMS\Core\Navigation\NavigableResourceRegistry;
use MCMS\Core\Storage\LocalStorage;
use MCMS\Core\Storage\ScopedStorage;
use MCMS\Core\Storage\StorageInterface;
use MCMS\Core\Themes\ThemeManager;
use MCMS\Core\Themes\ThemePackages;
use MCMS\Core\Content\ShortcodeRenderer;

class Services extends BaseService
{
    public static function mcmsDemoData(bool $getShared = true): DemoData
    {
        if ($getShared) {
            return static::getSharedInstance('mcmsDemoData');
        }

        return new DemoData(db_connect());
    }

    public static function mcmsResourceEditor(bool $getShared = true): \MCMS\Core\Content\ResourceEditor
    {
        if ($getShared) {
            return static::getSharedInstance('mcmsResourceEditor');
        }
        return new \MCMS\Core\Content\ResourceEditor(static::mcmsModuleServices());
    }

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
        $registry->addMenu('dashboard', '@MCMS.nav.dashboard', '/admin', 'ti ti-home', 'core.admin', 10, group: 'main');
        $registry->addMenu('users', '@MCMS.nav.users', '/admin/users', 'ti ti-users', 'core.users', 30, group: 'administration');
        $registry->addMenu('access', '@MCMS.nav.access', '/admin/access', 'ti ti-shield-lock', 'core.access', 40, group: 'administration');
        $registry->addMenu('modules', '@MCMS.nav.modules', '/admin/modules', 'ti ti-plug', 'core.modules', 50, group: 'administration');
        $registry->addMenu('themes', '@MCMS.nav.themes', '/admin/themes', 'ti ti-palette', 'core.themes', 65, group: 'appearance');
        $registry->addMenu('settings', '@MCMS.nav.settings', '/admin/settings', 'ti ti-settings', 'core.settings', 70, group: 'administration');
        $registry->addMenu('system', '@MCMS.nav.system', '/admin/system', 'ti ti-activity', 'core.system', 90, group: 'administration');
        $registry->addMenu('backups', '@MCMS.backups.title', '/admin/backups', 'ti ti-database-export', 'core.backups', 95, group: 'administration');

        $registry->addSettings('general', '@Settings.general', '/admin/settings', 'ti ti-settings', 'core.settings', 'general');
        $registry->addSettings('themes', '@MCMS.nav.themes', '/admin/themes', 'ti ti-palette', 'core.themes', 'appearance');

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
            WRITEPATH . 'modules-data/installed',
            static::mcmsModuleRuntime(),
        );
    }

    public static function mcmsModulePackages(bool $getShared = true): ModulePackages
    {
        if ($getShared) {
            return static::getSharedInstance('mcmsModulePackages');
        }

        return new ModulePackages(static::mcmsModules(), WRITEPATH . 'modules-data', static::mcmsModuleRuntime());
    }

    public static function mcmsModuleRuntime(bool $getShared = true): ModuleRuntime
    {
        if ($getShared) {
            return static::getSharedInstance('mcmsModuleRuntime');
        }
        return new ModuleRuntime(WRITEPATH . 'modules-data');
    }

    public static function mcmsModuleUpdates(bool $getShared = true): ModuleUpdates
    {
        if ($getShared) {
            return static::getSharedInstance('mcmsModuleUpdates');
        }
        return new ModuleUpdates(static::mcmsModules(), static::mcmsModulePackages(), static::mcmsModuleRuntime(), WRITEPATH . 'modules-data');
    }

    public static function mcmsThemeUpdates(bool $getShared = true): \MCMS\Core\Themes\ThemeUpdates
    {
        if ($getShared) {
            return static::getSharedInstance('mcmsThemeUpdates');
        }
        return new \MCMS\Core\Themes\ThemeUpdates(static::mcmsThemes(), static::mcmsThemePackages(), static::mcmsModuleRuntime(), WRITEPATH . 'themes-data');
    }

    public static function mcmsThemePackages(bool $getShared = true): ThemePackages
    {
        if ($getShared) {
            return static::getSharedInstance('mcmsThemePackages');
        }
        return new ThemePackages(static::mcmsThemes(), WRITEPATH . 'themes-data', static::mcmsModuleRuntime());
    }

    public static function mcmsThemes(bool $getShared = true): ThemeManager
    {
        if ($getShared) {
            return static::getSharedInstance('mcmsThemes');
        }

        return new ThemeManager(static::settings(), ROOTPATH . 'themes', null, WRITEPATH . 'themes-data');
    }

    /** Every module uses its own directory inside the ONE modules_data volume. */
    public static function mcmsModuleStorage(string $moduleId): ScopedStorage
    {
        return new ScopedStorage(WRITEPATH . 'modules-data/data', $moduleId);
    }

    /** Themes use their own persistent files inside the ONE themes_data volume. */
    public static function mcmsThemeStorage(string $themeId): ScopedStorage
    {
        return new ScopedStorage(WRITEPATH . 'themes-data/data', $themeId);
    }

    public static function mcmsBackups(bool $getShared = true): BackupManager
    {
        if ($getShared) {
            return static::getSharedInstance('mcmsBackups');
        }

        return new BackupManager(WRITEPATH . 'backups', [
            'modules_data' => WRITEPATH . 'modules-data',
            'themes_data' => WRITEPATH . 'themes-data',
        ]);
    }

    public static function mcmsBackupRestore(bool $getShared = true): RestoreManager
    {
        if ($getShared) {
            return static::getSharedInstance('mcmsBackupRestore');
        }
        return new RestoreManager(static::mcmsBackups(), WRITEPATH . 'backups', [
            'modules_data' => WRITEPATH . 'modules-data',
            'themes_data' => WRITEPATH . 'themes-data',
        ]);
    }

    public static function mcmsStorage(bool $getShared = true): StorageInterface
    {
        if ($getShared) {
            return static::getSharedInstance('mcmsStorage');
        }

        return new LocalStorage(WRITEPATH . 'modules-data/core');
    }
}
