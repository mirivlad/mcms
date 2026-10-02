<?php

declare(strict_types=1);

namespace MCMS\Core\Content;

final class TagNames
{
    public static function parse(?string $input): array
    {
        $tags = [];
        foreach (explode(',', $input ?? '') as $name) {
            $name = trim($name);
            if ($name !== '') {
                $tags[mb_strtolower($name)] ??= $name;
            }
        }
        return array_values($tags);
    }

    public static function normalize(?string $input): ?string
    {
        $tags = self::parse($input);
        $value = implode(', ', $tags);
        if (mb_strlen($value) > 1000) {
            throw new \InvalidArgumentException(lang('MCMS.content.tagsTooLong'));
        }
        return $value === '' ? null : $value;
    }
}
