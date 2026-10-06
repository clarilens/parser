<?php

declare(strict_types=1);

namespace Clarilens\Parser\Parser;

enum ParserType: string
{
    case HTML = 'HTML';
    case XML = 'XML';
    case PDF = 'PDF';
}
