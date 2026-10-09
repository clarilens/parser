<?php

declare(strict_types=1);

namespace Clarilens\Parser\Config;

use JsonException;

final class CanonicalConfig
{
    /** @param array<string, mixed> $config
     * @throws JsonException
     */
    public static function hash(array $config): string
    {
        return hash('sha256', json_encode(self::sort($config), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private static function sort(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $item) {
            $value[$key] = self::sort($item);
        }
        return $value;
    }
}
