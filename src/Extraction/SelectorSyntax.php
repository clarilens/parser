<?php

declare(strict_types=1);

namespace Clarilens\Parser\Extraction;

enum SelectorSyntax: string
{
    case Css = 'css';
    case XPath = 'xpath';
}
