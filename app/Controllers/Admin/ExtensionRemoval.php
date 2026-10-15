<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;
use Throwable;

final class ExtensionRemoval extends BaseAdminController
{
    public function index(string $type, string $id): string|RedirectResponse
    {
        if ($denied = $this->guard($type === 'module' ? 'core.modules' : 'core.themes')) {
            return $denied;
        }
        try {
            $removal = service('mcmsExtensionRemoval');
            $preview = $removal->preview($type, $id);
            if (strtolower($this->request->getMethod()) === 'post') {
                if ($this->request->getPost('confirm') !== $id || ! in_array($this->request->getPost('mode'), ['keep', 'purge'], true)) {
                    throw new RuntimeException(lang('Extensions.confirmRequired'));
                }
                $removal->remove($type, $id, $this->request->getPost('mode') === 'purge',
                    (string) $this->request->getPost('fingerprint'), $this->request->getPost('backup') === '1');
                return redirect()->to('/admin/' . ($type === 'module' ? 'modules' : 'themes'))
                    ->with('message', lang('Extensions.removed'));
            }
            return view('admin/extensions/remove', ['adminMenu' => $this->adminMenu(), ...$preview]);
        } catch (Throwable $e) {
            return redirect()->to('/admin/' . ($type === 'module' ? 'modules' : 'themes'))->with('error', $e->getMessage());
        }
    }

    public function recovery(): string|RedirectResponse
    {
        $flag = service('mcmsModuleRuntime')->maintenance();
        if ($denied = $this->guard(service('mcmsExtensionRemoval')->recoveryType() === 'theme' ? 'core.themes' : 'core.modules')) {
            return $denied;
        }
        if (strtolower($this->request->getMethod()) === 'post') {
            try {
                service('mcmsExtensionRemoval')->recover();
                return redirect()->to('/admin/modules')->with('message', lang('Extensions.recovered'));
            } catch (Throwable $e) {
                return redirect()->to('/admin/extensions/recovery')->with('error', $e->getMessage());
            }
        }
        return view('admin/extensions/recovery', ['adminMenu' => $this->adminMenu(), 'flag' => $flag]);
    }
}
