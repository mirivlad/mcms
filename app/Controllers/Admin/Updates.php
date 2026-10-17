<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use CodeIgniter\HTTP\RedirectResponse;
use Throwable;

final class Updates extends BaseAdminController
{
    public function index(): string|RedirectResponse
    {
        if ($denied = $this->guard('core.system')) {
            return $denied;
        }
        return view('admin/updates/index', [
            'adminMenu' => $this->adminMenu(),
            'policy' => service('mcmsUpdatePolicy')->get(),
            'status' => service('mcmsUpdatePolicy')->status(),
            'corePending' => \MCMS\Core\Packages\PackageState::read(service('mcmsUpdatePolicy')->root() . '/core-pending.json'),
            'catalogStatus' => service('mcmsExtensionCatalog')->status(),
            'catalogCache' => service('mcmsExtensionCatalog')->cache(),
            'entries' => service('mcmsExtensionCatalog')->entries(),
            'version' => \MCMS\Core\Version::VERSION,
        ]);
    }

    public function save(): RedirectResponse
    {
        if ($denied = $this->guard('core.system')) {
            return $denied;
        }
        try {
            $excluded = $this->request->getPost('excluded');
            service('mcmsUpdatePolicy')->save([
                'core' => $this->request->getPost('core') === '1',
                'modules' => $this->request->getPost('modules') === '1',
                'themes' => $this->request->getPost('themes') === '1',
                'excluded' => is_array($excluded) ? $excluded : [],
            ]);
            return redirect()->to('/admin/updates')->with('message', 'Настройки обновлений сохранены.');
        } catch (Throwable $e) {
            return redirect()->to('/admin/updates')->with('error', $e->getMessage());
        }
    }

    public function resetCorePending(): RedirectResponse
    {
        if ($denied = $this->guard('core.system')) {
            return $denied;
        }
        try {
            $path = service('mcmsUpdatePolicy')->root() . '/core-pending.json';
            \MCMS\Core\Packages\PackageState::remove($path);
            service('mcmsUpdatePolicy')->record(['type' => 'core', 'result' => 'reset', 'reason' => 'Pending Core deployment receipt reset by administrator']);
            return redirect()->to('/admin/updates')->with('message', 'Статус ожидания Core сброшен. Проверьте реальное состояние контейнеров.');
        } catch (Throwable $e) {
            return redirect()->to('/admin/updates')->with('error', $e->getMessage());
        }
    }

    public function check(): RedirectResponse
    {
        if ($denied = $this->guard('core.system')) {
            return $denied;
        }
        try {
            service('mcmsExtensionCatalog')->refresh(true);
            return redirect()->to('/admin/updates')->with('message', 'Каталог расширений проверен. Фоновый процесс применяет только разрешённые обновления.');
        } catch (Throwable $e) {
            return redirect()->to('/admin/updates')->with('error', $e->getMessage());
        }
    }
}
