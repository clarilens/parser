<?php

declare(strict_types=1);

namespace Clarilens\Parser\Condition;

enum ConditionOperator: string
{
    case All = 'all';
    case Any = 'any';
    case Not = 'not';
    case Exists = 'exists';
    case Equals = 'equals';
    case In = 'in';
    case Matches = 'matches';
    case Compare = 'compare';
}
