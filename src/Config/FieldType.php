<?php

declare(strict_types=1);

namespace Clarilens\Parser\Config;

enum FieldType: string
{
    case String = 'string';
    case Number = 'number';
    case Url = 'url';
}
