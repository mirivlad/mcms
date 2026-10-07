<?php

declare(strict_types=1);

namespace MCMS\Modules\Delivery\Services;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use MCMS\Modules\Delivery\Api\DeliveryInterface;

final class Delivery implements DeliveryInterface
{
    private const TYPES = ['delivery', 'pickup'];

    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function all(bool $enabledOnly = false): array
    {
        $builder = $this->db->table('delivery_methods')
            ->orderBy('position', 'ASC')
            ->orderBy('price_minor', 'ASC')
            ->orderBy('id', 'ASC');

        if ($enabledOnly) {
            $builder->where('enabled', 1);
        }

        return array_map(
            fn (array $row): array => $this->present($row),
            $builder->get()->getResultArray()
        );
    }

    public function selectable(): array
    {
        return $this->all(true);
    }

    public function find(int $id): ?array
    {
        $row = $this->db->table('delivery_methods')->where('id', $id)->get()->getRowArray();

        return $row === null ? null : $this->present($row);
    }

    public function findSelectable(int $id): ?array
    {
        $row = $this->db->table('delivery_methods')
            ->where('id', $id)
            ->where('enabled', 1)
            ->get()
            ->getRowArray();

        return $row === null ? null : $this->present($row);
    }

    public function save(?int $id, array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $type = trim((string) ($data['type'] ?? ''));
        $priceMinor = self::priceMinor((string) ($data['price'] ?? ''));
        $position = filter_var($data['position'] ?? null, FILTER_VALIDATE_INT);

        if ($name === '' || mb_strlen($name) > 190) {
            throw new InvalidArgumentException(lang('Delivery.invalidName'));
        }
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException(lang('Delivery.invalidType'));
        }
        if ($position === false || $position < 0 || $position > 2_000_000_000) {
            throw new InvalidArgumentException(lang('Delivery.invalidPosition'));
        }

        $now = date('Y-m-d H:i:s');
        $row = [
            'name' => $name,
            'type' => $type,
            'price_minor' => $priceMinor,
            'enabled' => (string) ($data['enabled'] ?? '0') === '1' ? 1 : 0,
            'position' => $position,
            'updated_at' => $now,
        ];

        if ($id === null) {
            $this->db->table('delivery_methods')->insert($row + ['created_at' => $now]);
            $id = (int) $this->db->insertID();
        } else {
            if ($this->find($id) === null) {
                throw new InvalidArgumentException(lang('Delivery.notFound'));
            }
            $this->db->table('delivery_methods')->where('id', $id)->update($row);
        }

        return $this->find($id) ?? throw new InvalidArgumentException(lang('Delivery.notFound'));
    }

    public function delete(int $id): void
    {
        if ($this->find($id) === null) {
            throw new InvalidArgumentException(lang('Delivery.notFound'));
        }

        $this->db->table('delivery_methods')->where('id', $id)->delete();
    }

    public function reorder(array $positions): void
    {
        if ($positions === []) {
            throw new InvalidArgumentException(lang('Delivery.invalidPosition'));
        }

        $this->db->transBegin();
        try {
            foreach ($positions as $id => $position) {
                $id = filter_var($id, FILTER_VALIDATE_INT);
                $position = filter_var($position, FILTER_VALIDATE_INT);
                if ($id === false || $id < 1 || $position === false || $position < 0 || $position > 2_000_000_000) {
                    throw new InvalidArgumentException(lang('Delivery.invalidPosition'));
                }
                if ($this->find($id) === null) {
                    throw new InvalidArgumentException(lang('Delivery.notFound'));
                }
                $this->db->table('delivery_methods')->where('id', $id)->update([
                    'position' => $position,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            if (! $this->db->transStatus()) {
                throw new InvalidArgumentException(lang('Delivery.saveFailed'));
            }
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public static function priceMinor(string $input): int
    {
        $input = str_replace(',', '.', trim($input));
        if (preg_match('/^(0|[1-9][0-9]{0,8})(?:\.([0-9]{1,2}))?$/', $input, $matches) !== 1) {
            throw new InvalidArgumentException(lang('Delivery.invalidPrice'));
        }

        return (int) $matches[1] * 100 + (int) str_pad($matches[2] ?? '', 2, '0');
    }

    private function present(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['price_minor'] = (int) $row['price_minor'];
        $row['enabled'] = (bool) $row['enabled'];
        $row['position'] = (int) $row['position'];
        $row['price'] = intdiv($row['price_minor'], 100) . '.'
            . str_pad((string) ($row['price_minor'] % 100), 2, '0', STR_PAD_LEFT);
        $row['price_display'] = $this->money($row['price_minor']);
        $row['type_label'] = lang('Delivery.type.' . $row['type']);

        return $row;
    }

    private function money(int $minor): string
    {
        return number_format(intdiv($minor, 100), 0, ',', ' ')
            . ',' . str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT) . ' ₽';
    }
}
