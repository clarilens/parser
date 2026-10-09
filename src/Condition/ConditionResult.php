<?php

declare(strict_types=1);

namespace Clarilens\Parser\Condition;

enum ConditionResult
{
    case True;
    case False;
    case Unknown;

    public static function fromNullableBool(?bool $value): self
    {
        return match ($value) {
            true => self::True,
            false => self::False,
            null => self::Unknown,
        };
    }
}
