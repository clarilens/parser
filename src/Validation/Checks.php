<?php

declare(strict_types=1);

namespace Clarilens\Parser\Validation;

use InvalidArgumentException;

final class Checks
{
    public static function field(string $field): void
    {
        if ($field === '' || strlen($field) > 100) {
            throw new InvalidArgumentException('Invalid field identifier.');
        }
    }

    public static function selector(string $selector): void
    {
        if ($selector === '' || strlen($selector) > 1000) {
            throw new InvalidArgumentException('Invalid selector.');
        }
    }

    public static function regex(string $expression): void
    {
        self::selector($expression);
        if (@preg_match($expression, '') === false) {
            throw new InvalidArgumentException('Invalid PDF regular expression.');
        }
    }

    public static function timeout(int $milliseconds): void
    {
        if ($milliseconds < 1 || $milliseconds > 30000) {
            throw new InvalidArgumentException('Invalid source timeout.');
        }
    }
}
