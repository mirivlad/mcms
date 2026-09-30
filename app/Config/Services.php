<?php

namespace Config;

use CodeIgniter\Config\BaseService;
use MCMS\Core\Admin\AdminRegistry;
use MCMS\Core\Auth\AccessManager;
use MCMS\Core\Install\InstallationService;
use MCMS\Core\Modules\ModuleManager;
use MCMS\Core\Modules\ModuleServiceRegistry;
use MCMS\Core\Storage\LocalStorage;
use MCMS\Core\Storage\StorageInterface;

class Services extends BaseService
{
    public static function mcmsAdmin(bool $getShared = true): AdminRegistry
    {
        if ($getShared) {
            return static::getSharedInstance('mcmsAdmin');
        }

        $registry = new AdminRegistry();
        $registry->addMenu('dashboard', 'Dashboard', '/admin', 'ti ti-home', 'core.admin', 10);
        $registry->addMenu('users', 'Users', '/admin/users', 'ti ti-users', 'core.users', 30);
        $registry->addMenu('access', 'Access', '/admin/access', 'ti ti-shield-lock', 'core.access', 40);
        $registry->addMenu('modules', 'Modules', '/admin/modules', 'ti ti-plug', 'core.modules', 50);
        $registry->addMenu('settings', 'Settings', '/admin/settings', 'ti ti-settings', 'core.settings', 70);
        $registry->addMenu('system', 'System', '/admin/system', 'ti ti-activity', 'core.system', 90);

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

    public static function mcmsModules(bool $getShared = true): ModuleManager
    {
        if ($getShared) {
            return static::getSharedInstance('mcmsModules');
        }

        return new ModuleManager(
            db_connect(),
            static::mcmsAdmin(),
            static::mcmsModuleServices(),
        );
    }

    public static function mcmsStorage(bool $getShared = true): StorageInterface
    {
        if ($getShared) {
            return static::getSharedInstance('mcmsStorage');
        }

        return new LocalStorage(WRITEPATH . 'storage');
    }
}
