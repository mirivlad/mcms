<?php

declare(strict_types=1);

namespace MCMS\Core\Content;

use MCMS\Core\Modules\ModuleServiceRegistry;
use InvalidArgumentException;

final class ResourceEditor
{
    public function __construct(private readonly ModuleServiceRegistry $services) {}

    public function fields(): array
    {
        $fields = [];
        foreach ($this->services->all() as $service) {
            if ($service instanceof ResourceEditorExtensionInterface) {
                $fields[$service->key()] = $service;
            }
        }
        return $fields;
    }

    public function save(string $type, string $id, mixed $input): void
    {
        if ($input === null) {
            return; // Older clients and disabled extensions preserve saved choices.
        }
        if (! is_array($input)) {
            throw new InvalidArgumentException(lang('MCMS.resourceOptions.invalid'));
        }
        foreach ($this->fields() as $key => $field) {
            if (array_key_exists($key, $input)) {
                if (! is_string($input[$key])) {
                    throw new InvalidArgumentException(lang('MCMS.resourceOptions.invalid'));
                }
                $field->save($type, $id, $input[$key]);
            }
        }
    }
}
