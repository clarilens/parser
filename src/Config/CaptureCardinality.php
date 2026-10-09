<?php

declare(strict_types=1);

namespace Clarilens\Parser\Config;

enum CaptureCardinality: string
{
    case One = 'one';
    case First = 'first';
    case All = 'all';
}
