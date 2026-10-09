<?php

declare(strict_types=1);

namespace Clarilens\Parser\Condition;

enum NumericComparator: string
{
    case LessThan = 'lt';
    case LessThanOrEqual = 'lte';
    case GreaterThan = 'gt';
    case GreaterThanOrEqual = 'gte';
}
