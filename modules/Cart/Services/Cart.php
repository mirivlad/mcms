<?php

declare(strict_types=1);

namespace MCMS\Modules\Cart\Services;

use InvalidArgumentException;
use MCMS\Modules\Cart\Api\CartInterface;
use MCMS\Modules\Catalogue\Api\CatalogueInterface;
use MCMS\Modules\Stock\Api\StockInterface;
use RuntimeException;

final class Cart implements CartInterface
{
    private const SESSION_KEY = 'mcms_cart';
    private const MAX_QUANTITY = 2_000_000_000;

    public function __construct(
        private readonly CatalogueInterface $catalogue,
        private readonly StockInterface $stock,
    ) {
    }

    public function snapshot(): array
    {
        [$state, $presentation] = $this->reconcile();
        $subtotal = 0;
        $totalItems = 0;
        foreach ($presentation as $item) {
            $subtotal += (int) $item['subtotal_minor'];
            $totalItems += (int) $item['quantity'];
        }

        return [
            'items' => $presentation,
            'total_items' => $totalItems,
            'subtotal_minor' => $subtotal,
            'subtotal_display' => $this->money($subtotal),
        ];
    }

    public function items(): array
    {
        return $this->snapshot()['items'];
    }

    public function count(): int
    {
        return (int) $this->snapshot()['total_items'];
    }

    public function add(int $variantId, int $quantity): array
    {
        $this->assertQuantity($quantity, false);
        $variant = $this->publishedVariant($variantId);
        $state = $this->state();
        $current = (int) ($state['items'][$variantId]['quantity'] ?? 0);
        $requested = $current + $quantity;
        if ($requested > self::MAX_QUANTITY) {
            throw new InvalidArgumentException(lang('Cart.invalidQuantity'));
        }

        $actual = $this->fitAvailability($variantId, $requested);
        if ($actual < 1) {
            throw new RuntimeException(lang('Cart.outOfStock'));
        }

        if (! isset($state['items'][$variantId])) {
            $state['items'][$variantId] = $this->snapshotVariant($variant);
        }
        $state['items'][$variantId]['quantity'] = $actual;
        $state['items'][$variantId]['updated_at'] = time();
        $this->save($state);

        return $this->mutation($variantId, $requested);
    }

    public function setQuantity(int $variantId, int $quantity): array
    {
        $this->assertQuantity($quantity, true);
        $state = $this->state();
        if (! isset($state['items'][$variantId])) {
            throw new InvalidArgumentException(lang('Cart.itemNotFound'));
        }

        if ($quantity === 0) {
            $this->remove($variantId);

            return $this->mutation($variantId, 0);
        }

        try {
            $this->publishedVariant($variantId);
        } catch (RuntimeException|InvalidArgumentException $e) {
            $this->remove($variantId);
            throw $e;
        }

        $actual = $this->fitAvailability($variantId, $quantity);
        if ($actual < 1) {
            $this->remove($variantId);

            return $this->mutation($variantId, $quantity);
        }

        $state['items'][$variantId]['quantity'] = $actual;
        $state['items'][$variantId]['updated_at'] = time();
        $this->save($state);

        return $this->mutation($variantId, $quantity);
    }

    public function remove(int $variantId): void
    {
        $state = $this->state();
        unset($state['items'][$variantId]);
        $this->save($state);
    }

    public function clear(): void
    {
        session()->remove(self::SESSION_KEY);
    }

    /**
     * Cart never reserves stock. It only reconciles stale quantities with current
     * availability so the customer gets useful feedback before checkout.
     *
     * @return array{0:array{items:array<int,array<string,mixed>>},1:list<array<string,mixed>>}
     */
    private function reconcile(): array
    {
        $state = $this->state();
        if ($state['items'] === []) {
            return [$state, []];
        }

        $changed = false;
        $presentation = [];
        foreach ($state['items'] as $variantId => $item) {
            $variantId = (int) $variantId;
            $requested = max(0, (int) ($item['quantity'] ?? 0));
            $variant = $this->catalogue->findVariant($variantId);

            if ($variant === null || ($variant['product_status'] ?? '') !== 'published' || $requested < 1) {
                unset($state['items'][$variantId]);
                $changed = true;
                continue;
            }

            $actual = $this->fitAvailability($variantId, $requested);
            if ($actual < 1) {
                unset($state['items'][$variantId]);
                $changed = true;
                continue;
            }

            $adjusted = $actual !== $requested;
            if ($adjusted) {
                $state['items'][$variantId]['quantity'] = $actual;
                $state['items'][$variantId]['updated_at'] = time();
                $changed = true;
            }

            $presentation[] = $this->present($state['items'][$variantId], $adjusted, $requested);
        }

        if ($changed) {
            $this->save($state);
        }

        return [$state, $presentation];
    }

    private function fitAvailability(int $variantId, int $requested): int
    {
        return min($requested, max(0, (int) $this->stock->level($variantId)['available']));
    }

    private function mutation(int $variantId, int $requested): array
    {
        $summary = $this->snapshot();
        $item = null;
        foreach ($summary['items'] as $candidate) {
            if ((int) $candidate['variant_id'] === $variantId) {
                $item = $candidate;
                break;
            }
        }
        $finalQuantity = $item === null ? 0 : (int) $item['quantity'];

        return [
            'variant_id' => $variantId,
            'requested_quantity' => $requested,
            'quantity' => $finalQuantity,
            'adjusted' => $finalQuantity < $requested,
            'item' => $item,
            'total_items' => $summary['total_items'],
            'subtotal_minor' => $summary['subtotal_minor'],
            'subtotal_display' => $summary['subtotal_display'],
            'empty' => $summary['items'] === [],
        ];
    }

    private function publishedVariant(int $variantId): array
    {
        $variant = $this->catalogue->findVariant($variantId);
        if ($variant === null) {
            throw new InvalidArgumentException(lang('Cart.variantNotFound'));
        }
        if (($variant['product_status'] ?? '') !== 'published') {
            throw new RuntimeException(lang('Cart.unavailable'));
        }

        return $variant;
    }

    private function snapshotVariant(array $variant): array
    {
        return [
            'variant_id' => (int) $variant['id'],
            'product_id' => (int) $variant['product_id'],
            'product_title' => (string) $variant['product_title'],
            'product_slug' => (string) $variant['product_slug'],
            'sku' => (string) $variant['sku'],
            'label' => (string) $variant['label'],
            'unit_price_minor' => (int) $variant['price_minor'],
            'currency' => (string) $variant['currency'],
            'quantity' => 0,
            'added_at' => time(),
            'updated_at' => time(),
        ];
    }

    private function present(array $item, bool $adjusted = false, ?int $requested = null): array
    {
        $quantity = (int) $item['quantity'];
        $unit = (int) $item['unit_price_minor'];
        $subtotal = $unit * $quantity;

        return $item + [
            'product_url' => '/shop/' . $item['product_slug'],
            'unit_price_display' => $this->money($unit),
            'subtotal_minor' => $subtotal,
            'subtotal_display' => $this->money($subtotal),
            'stock_adjusted' => $adjusted,
            'requested_quantity' => $requested ?? $quantity,
        ];
    }

    /** @return array{items:array<int,array<string,mixed>>} */
    private function state(): array
    {
        $state = session()->get(self::SESSION_KEY);
        $items = is_array($state) && isset($state['items']) && is_array($state['items'])
            ? $state['items']
            : [];

        return ['items' => $items];
    }

    private function save(array $state): void
    {
        if (($state['items'] ?? []) === []) {
            session()->remove(self::SESSION_KEY);
            return;
        }

        session()->set(self::SESSION_KEY, ['items' => $state['items']]);
    }

    private function assertQuantity(int $quantity, bool $allowZero): void
    {
        $minimum = $allowZero ? 0 : 1;
        if ($quantity < $minimum || $quantity > self::MAX_QUANTITY) {
            throw new InvalidArgumentException(lang('Cart.invalidQuantity'));
        }
    }

    private function money(int $minor): string
    {
        return number_format(intdiv($minor, 100), 0, ',', ' ')
            . ',' . str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT) . ' ₽';
    }
}
