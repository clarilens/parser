<?php

declare(strict_types=1);

namespace Clarilens\Parser\Config;

enum TransformOperation: string
{
    case Trim = 'trim';
    case Number = 'number';
    case Enum = 'enum';
    case Url = 'url';
    case Registered = 'registered';
}
