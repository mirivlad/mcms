<?php

declare(strict_types=1);

namespace MCMS\Modules\Cart\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use MCMS\Modules\Cart\Api\CartInterface;
use Throwable;

final class Cart extends BaseController
{
    public function index(): string
    {
        $services = service('mcmsModuleServices');
        $ordersEnabled = $services->has('orders.orders');
        $deliveryEnabled = $services->has('delivery.methods');
        $deliveryMethods = $deliveryEnabled ? $services->get('delivery.methods')->selectable() : [];

        return service('mcmsThemes')->render('cart.index', [
            'cart' => $this->cart()->snapshot(),
            'checkoutEnabled' => $ordersEnabled && (! $deliveryEnabled || $deliveryMethods !== []),
            'checkoutBlockedByDelivery' => $ordersEnabled && $deliveryEnabled && $deliveryMethods === [],
            'deliveryEnabled' => $deliveryEnabled,
            'deliveryMethods' => $deliveryMethods,
            'metaTitle' => lang('Cart.title') . ' · ' . setting('Core.siteName'),
        ], 'MCMS\\Modules\\Cart\\Views\\public_index');
    }

    public function fragment(): ResponseInterface
    {
        $cart = $this->cart()->snapshot();
        return $this->response
            ->setHeader('Content-Type', 'text/html; charset=UTF-8')
            ->setHeader('Cache-Control', 'no-store, private')
            ->setBody(view('MCMS\\Modules\\Cart\\Views\\_modal', ['cart' => $cart]));
    }

    public function add(): RedirectResponse|ResponseInterface
    {
        try {
            $result = $this->cart()->add(
                (int) $this->request->getPost('variant_id'),
                $this->quantity(false)
            );
            $message = $result['adjusted'] ? lang('Cart.adjusted') : lang('Cart.added');

            return $this->respond($result, $message, '/cart');
        } catch (Throwable $e) {
            return $this->fail($e->getMessage(), '/cart');
        }
    }

    public function update(): RedirectResponse|ResponseInterface
    {
        try {
            $result = $this->cart()->setQuantity(
                (int) $this->request->getPost('variant_id'),
                $this->quantity(true)
            );
            $message = $result['adjusted'] ? lang('Cart.adjusted') : lang('Cart.updated');

            return $this->respond($result, $message, '/cart');
        } catch (Throwable $e) {
            return $this->fail($e->getMessage(), '/cart');
        }
    }

    public function remove(int $variantId): RedirectResponse|ResponseInterface
    {
        try {
            $this->cart()->remove($variantId);
            $result = $this->cart()->snapshot();

            return $this->respond([
                'variant_id' => $variantId,
                'quantity' => 0,
                'adjusted' => false,
                'item' => null,
                'total_items' => $result['total_items'],
                'subtotal_minor' => $result['subtotal_minor'],
                'subtotal_display' => $result['subtotal_display'],
                'empty' => $result['items'] === [],
            ], lang('Cart.removed'), '/cart');
        } catch (Throwable $e) {
            return $this->fail($e->getMessage(), '/cart');
        }
    }

    public function clear(): RedirectResponse|ResponseInterface
    {
        try {
            $this->cart()->clear();

            return $this->respond([
                'total_items' => 0,
                'subtotal_minor' => 0,
                'subtotal_display' => '0,00 ₽',
                'empty' => true,
            ], lang('Cart.cleared'), '/cart');
        } catch (Throwable $e) {
            return $this->fail($e->getMessage(), '/cart');
        }
    }

    public function count(): ResponseInterface
    {
        return $this->response->setJSON([
            'total_items' => $this->cart()->count(),
            'csrf' => ['name' => csrf_token(), 'hash' => csrf_hash()],
        ]);
    }

    public function asset(string $name): ResponseInterface
    {
        $types = [
            'cart.css' => 'text/css; charset=UTF-8',
            'cart.js' => 'text/javascript; charset=UTF-8',
        ];
        if (! isset($types[$name])) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response
            ->setHeader('Content-Type', $types[$name])
            ->setHeader('Cache-Control', 'public, max-age=300')
            ->setBody(file_get_contents(dirname(__DIR__) . '/assets/' . $name));
    }

    private function quantity(bool $allowZero): int
    {
        $raw = trim((string) $this->request->getPost('quantity'));
        if ($raw === '' || preg_match('/^\d+$/', $raw) !== 1) {
            throw new \InvalidArgumentException(lang('Cart.invalidQuantity'));
        }
        $quantity = (int) $raw;
        if ($quantity < ($allowZero ? 0 : 1) || $quantity > 2_000_000_000) {
            throw new \InvalidArgumentException(lang('Cart.invalidQuantity'));
        }

        return $quantity;
    }

    private function respond(array $result, string $message, string $redirect): RedirectResponse|ResponseInterface
    {
        if ($this->request->isAJAX()) {
            return $this->response->setJSON($result + [
                'success' => true,
                'message' => $message,
                'csrf' => ['name' => csrf_token(), 'hash' => csrf_hash()],
            ]);
        }

        return redirect()->to($this->safeReturn((string) $this->request->getPost('return'), $redirect))
            ->with('message', $message);
    }

    private function fail(string $message, string $redirect): RedirectResponse|ResponseInterface
    {
        if ($this->request->isAJAX()) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => $message,
                'csrf' => ['name' => csrf_token(), 'hash' => csrf_hash()],
            ]);
        }

        return redirect()->to($this->safeReturn((string) $this->request->getPost('return'), $redirect))
            ->with('error', $message);
    }

    private function safeReturn(string $return, string $fallback): string
    {
        $return = trim($return);
        if ($return === '' || ! str_starts_with($return, '/') || str_starts_with($return, '//')
            || str_contains($return, "\n") || str_contains($return, "\r")) {
            return $fallback;
        }

        return $return;
    }

    private function cart(): CartInterface
    {
        return service('mcmsModuleServices')->get('cart.cart');
    }
}
