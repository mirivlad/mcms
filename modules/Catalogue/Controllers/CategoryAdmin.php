<?php

declare(strict_types=1);

namespace MCMS\Modules\Catalogue\Controllers;

use App\Controllers\Admin\BaseAdminController;
use CodeIgniter\HTTP\RedirectResponse;
use MCMS\Modules\Catalogue\Services\Categories;
use RuntimeException;
use Throwable;

final class CategoryAdmin extends BaseAdminController
{
    public function index(): string|RedirectResponse
    {
        if (($redirect = $this->guard('catalogue.view')) !== null) {
            return $redirect;
        }
        return view('MCMS\\Modules\\Catalogue\\Views\\categories', [
            'title' => lang('Catalogue.categories'), 'adminMenu' => $this->adminMenu(), 'user' => auth()->user(),
            'categories' => $this->categories()->list(), 'canCreate' => $this->can('catalogue.create'),
            'canEdit' => $this->can('catalogue.edit'), 'canDelete' => $this->can('catalogue.delete'),
        ]);
    }

    public function create(): string|RedirectResponse
    {
        if (($redirect = $this->guard('catalogue.create')) !== null) {
            return $redirect;
        }
        return $this->editor(null);
    }

    public function edit(int $id): string|RedirectResponse
    {
        if (($redirect = $this->guard('catalogue.edit')) !== null) {
            return $redirect;
        }
        $category = $this->categories()->find($id);
        return $category === null ? redirect()->to('/admin/catalogue/categories')->with('error', lang('Catalogue.categoryNotFound')) : $this->editor($category);
    }

    public function delete(int $id): RedirectResponse
    {
        if (($redirect = $this->guard('catalogue.delete')) !== null) {
            return $redirect;
        }
        try {
            if ($this->request->getPost('confirm') !== '1') {
                throw new RuntimeException(lang('Catalogue.confirmCategoryDelete'));
            }
            $this->categories()->delete($id);
            return redirect()->to('/admin/catalogue/categories')->with('message', lang('Catalogue.categoryDeleted'));
        } catch (Throwable $e) {
            return redirect()->to('/admin/catalogue/categories')->with('error', $e->getMessage());
        }
    }

    private function editor(?array $category): string|RedirectResponse
    {
        if ($this->request->getMethod() === 'POST') {
            try {
                $file = $this->request->getFile('image');
                $path = null;
                if ($file !== null && $file->getError() !== UPLOAD_ERR_NO_FILE) {
                    if (! $file->isValid()) {
                        throw new RuntimeException(lang('Catalogue.invalidCategoryImage'));
                    }
                    $path = $file->getTempName();
                }
                $saved = $this->categories()->save($category['id'] ?? null, (array) $this->request->getPost(), $path);
                return redirect()->to('/admin/catalogue/categories/' . $saved['id'] . '/edit')->with('message', lang('Catalogue.categorySaved'));
            } catch (Throwable $e) {
                return redirect()->back()->withInput()->with('error', $e->getMessage());
            }
        }
        $excluded = $category === null ? [] : $this->categories()->descendants($category['id']);
        return view('MCMS\\Modules\\Catalogue\\Views\\category_form', [
            'title' => lang($category === null ? 'Catalogue.createCategory' : 'Catalogue.editCategory'),
            'adminMenu' => $this->adminMenu(), 'user' => auth()->user(), 'category' => $category,
            'parents' => array_values(array_filter($this->categories()->list(), static fn (array $row): bool => ! in_array($row['id'], $excluded, true))),
        ]);
    }

    private function categories(): Categories
    {
        return service('mcmsModuleServices')->get('catalogue.categories');
    }
}
