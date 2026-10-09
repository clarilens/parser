<?php

declare(strict_types=1);

namespace Clarilens\Parser\Config;

enum FieldScope: string
{
    case Document = 'document';
    case Table = 'table';
    case Row = 'row';
    case Variant = 'variant';
}
