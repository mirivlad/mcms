<?php

namespace Config;

use CodeIgniter\Config\BaseService;
use MCMS\Core\Admin\AdminRegistry;
use MCMS\Core\Modules\ModuleManager;

class Services extends BaseService
{
    public static function mcmsAdmin(bool $getShared = true): AdminRegistry
    {
        if ($getShared) {
            return static::getSharedInstance('mcmsAdmin');
        }

        $registry = new AdminRegistry();
        $registry->addMenu('dashboard', 'Dashboard', '/admin', 'ti ti-home', 'core.admin', 10);
        $registry->addMenu('modules', 'Modules', '/admin/modules', 'ti ti-plug', 'core.modules', 50);

        return $registry;
    }

    public static function mcmsModules(bool $getShared = true): ModuleManager
    {
        if ($getShared) {
            return static::getSharedInstance('mcmsModules');
        }

        return new ModuleManager(db_connect(), static::mcmsAdmin());
    }
}
