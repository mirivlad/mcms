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
            'sharedHosting' => \MCMS\Core\Install\SharedHostSetup::enabled(),
            'sharedCorePreview' => session()->get('shared_core_preview'),
            'sharedCoreLast' => \MCMS\Core\Install\SharedHostSetup::enabled()
                ? service('mcmsSharedCoreUpdater')->last() : null,
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
                'core' => ! \MCMS\Core\Install\SharedHostSetup::enabled()
                    && $this->request->getPost('core') === '1',
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
    public function prepareSharedCore(): RedirectResponse
    {
        if ($denied = $this->guard('core.system')) {
            return $denied;
        }
        try {
            $upload = $this->request->getFile('package');
            if ($upload === null || ! $upload->isValid() || ! $upload->isFile()) {
                throw new \RuntimeException('Select a valid mCMS shared-hosting release ZIP.');
            }
            $preview = service('mcmsSharedCoreUpdater')->prepare($upload->getTempName());
            session()->set('shared_core_preview', $preview);
            return redirect()->to('/admin/updates')->with('message', 'Core ZIP verified. Review the target release before applying it.');
        } catch (Throwable $e) {
            return redirect()->to('/admin/updates')->with('error', $e->getMessage());
        }
    }

    public function applySharedCore(): RedirectResponse
    {
        if ($denied = $this->guard('core.system')) {
            return $denied;
        }
        try {
            $preview = session()->get('shared_core_preview');
            $token = (string) $this->request->getPost('token');
            if (! is_array($preview) || ! is_string($preview['token'] ?? null)
                || ! hash_equals($preview['token'], $token)
                || $this->request->getPost('acknowledge') !== '1') {
                throw new \RuntimeException('Confirm the verified Core ZIP and safety backup.');
            }
            session()->remove('shared_core_preview');
            // Avoid holding the session lock through the Core source swap.
            session()->close();
            service('mcmsSharedCoreUpdater')->apply($token);
            session()->start();
            return redirect()->to('/admin/updates/shared-core/finish');
        } catch (Throwable $e) {
            session()->start();
            log_message('error', 'Shared Core apply failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->to('/admin/updates')->with('error', $e->getMessage());
        }
    }

    public function finishSharedCore(): RedirectResponse
    {
        if ($denied = $this->guard('core.system')) {
            return $denied;
        }
        try {
            session()->close();
            service('mcmsSharedCoreUpdater')->finish();
            session()->start();
            return redirect()->to('/admin/updates')->with('message', 'Core ZIP upgrade and migrations completed successfully.');
        } catch (Throwable $e) {
            session()->start();
            log_message('error', 'Shared Core finalization failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->to('/admin/updates')->with('error', 'Core update failed; recovery will restore the previous release.');
        }
    }

}
