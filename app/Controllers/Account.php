<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Exceptions\PageNotFoundException;
use Throwable;

final class Account extends BaseController
{
    public function logout(): RedirectResponse
    {
        auth()->logout();
        return redirect()->to('/')->with('message', lang('MCMS.account.loggedOut'));
    }

    public function index(): string
    {
        $user = auth()->user();
        $ordersEnabled = service('mcmsModuleServices')->has('orders.orders');
        return $this->renderAccountPage('account', [
            'user' => $user,
            'profile' => $this->profile((int) $user->id),
            'ordersEnabled' => $ordersEnabled,
            'recentOrders' => $ordersEnabled ? $this->userOrders(3) : [],
        ]);
    }

    public function saveProfile(): RedirectResponse
    {
        $user = auth()->user();
        $name = trim((string) $this->request->getPost('full_name'));
        $phone = trim((string) $this->request->getPost('phone'));
        $username = trim((string) $this->request->getPost('username'));

        if (mb_strlen($name) > 190 || mb_strlen($phone) > 64
            || preg_match('/\A[a-zA-Z0-9.]{3,30}\z/D', $username) !== 1) {
            return redirect()->to('/account')->withInput()->with('error', lang('MCMS.account.invalidProfile'));
        }

        try {
            if ($username !== (string) $user->username) {
                $user->username = $username;
                $users = auth()->getProvider();
                if (! $users->save($user)) {
                    return redirect()->to('/account')->withInput()
                        ->with('error', implode(' ', $users->errors()));
                }
            }
            $db = db_connect();
            $now = date('Y-m-d H:i:s');
            $record = ['full_name' => $name, 'phone' => $phone, 'updated_at' => $now];
            $exists = $db->table('account_profiles')->where('user_id', (int) $user->id)
                ->countAllResults() > 0;
            if ($exists) {
                $db->table('account_profiles')->where('user_id', (int) $user->id)->update($record);
            } else {
                $record['user_id'] = (int) $user->id;
                $record['created_at'] = $now;
                $db->table('account_profiles')->insert($record);
            }
            return redirect()->to('/account')->with('message', lang('MCMS.account.profileSaved'));
        } catch (Throwable) {
            return redirect()->to('/account')->withInput()->with('error', lang('MCMS.account.saveFailed'));
        }
    }

    public function changePassword(): RedirectResponse
    {
        $user = auth()->user();
        $current = (string) $this->request->getPost('current_password');
        $new = (string) $this->request->getPost('new_password');
        $confirm = (string) $this->request->getPost('new_password_confirm');
        if (! password_verify($current, $user->getPasswordHash() ?? '')) {
            return redirect()->to('/account')->with('error', lang('MCMS.account.currentPasswordInvalid'));
        }
        if ($new !== $confirm || strlen($new) < 8 || $new === $current) {
            return redirect()->to('/account')->with('error', lang('MCMS.account.passwordInvalid'));
        }
        $valid = service('passwords')->check($new, $user);
        if (! $valid->isOK()) {
            return redirect()->to('/account')->with('error', $valid->reason());
        }
        try {
            $user->setPassword($new);
            if (! auth()->getProvider()->save($user)) {
                return redirect()->to('/account')->with('error', lang('MCMS.account.saveFailed'));
            }
            return redirect()->to('/account')->with('message', lang('MCMS.account.passwordSaved'));
        } catch (Throwable) {
            return redirect()->to('/account')->with('error', lang('MCMS.account.saveFailed'));
        }
    }

    public function orders(): string
    {
        $orders = service('mcmsModuleServices')->has('orders.orders') ? $this->userOrders(100) : [];
        return $this->renderAccountPage('orders', ['orders' => $orders]);
    }

    public function order(int $id): string
    {
        if (! service('mcmsModuleServices')->has('orders.orders')) {
            throw PageNotFoundException::forPageNotFound();
        }
        $db = db_connect();
        // Include the owner predicate in the lookup, not only in UI rendering.
        $order = $db->table('orders')->where('id', $id)
            ->where('customer_user_id', (int) auth()->id())->get()->getRowArray();
        if ($order === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        $items = $db->table('order_items')->where('order_id', $id)
            ->orderBy('id')->get()->getResultArray();
        return $this->renderAccountPage('order', ['order' => $order, 'items' => $items]);
    }

    private function renderAccountPage(string $page, array $data = []): string
    {
        $key = match ($page) {
            'account' => 'title',
            'order' => 'order',
            'orders' => 'orders',
        };
        $title = lang('MCMS.account.' . $key);
        return service('mcmsThemes')->render('account.' . $page, [
            ...$data,
            // CodeIgniter view services may retain prior view data between requests.
            'accountDashboard' => $page === 'account',
            'accountPageTitle' => $title,
            'metaTitle' => $title . ' · ' . (string) (setting('Core.siteName') ?? 'mCMS'),
            'metaRobots' => 'noindex, nofollow',
        ], 'auth/' . $page);
    }

    /** @return list<array<string,mixed>> */
    private function userOrders(int $limit): array
    {
        // Customer ownership is enforced by SQL, including dashboard previews.
        return db_connect()->table('orders')
            ->select('id, status, created_at, total_minor, currency')
            ->where('customer_user_id', (int) auth()->id())
            ->orderBy('id', 'DESC')->limit($limit)->get()->getResultArray();
    }

    /** @return array{full_name:string,phone:string} */
    private function profile(int $userId): array
    {
        return db_connect()->table('account_profiles')
            ->select('full_name, phone')->where('user_id', $userId)->get()->getRowArray()
            ?? ['full_name' => '', 'phone' => ''];
    }
}
