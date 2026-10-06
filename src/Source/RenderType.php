<?php

declare(strict_types=1);

namespace Clarilens\Parser\Source;

enum RenderType: string
{
    case Browser = 'browser';
    case Http = 'http';
}
