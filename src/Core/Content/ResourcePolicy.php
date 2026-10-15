<?php

declare(strict_types=1);

namespace MCMS\Core\Content;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;

/** Each optional module owns its policy table and lifecycle. */
final class ResourcePolicy implements ResourceEditorExtensionInterface
{
    public function __construct(private readonly BaseConnection $db, private readonly string $name, private readonly string $title, private readonly string $table)
    {
    }

    public function key(): string { return $this->name; }
    public function label(): string { return lang($this->title); }

    public function value(string $type, string $id): string
    {
        $row = $this->db->table($this->table)->where('target_type', $type)->where('target_id', $id)->get()->getRowArray();
        return $row['mode'] ?? 'inherit';
    }

    public function save(string $type, string $id, string $value): void
    {
        if (! in_array($value, ['inherit', 'allow', 'deny'], true)) {
            throw new InvalidArgumentException(lang('MCMS.resourceOptions.invalid'));
        }
        $this->db->table($this->table)->upsert(['target_type' => $type, 'target_id' => $id, 'mode' => $value]);
    }

    public function allowed(string $type, string $id, bool $inherited): bool
    {
        return match ($this->value($type, $id)) {
            'allow' => true, 'deny' => false, default => $inherited,
        };
    }
}
