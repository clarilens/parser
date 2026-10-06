<?php

declare(strict_types=1);

namespace Clarilens\Parser\Extraction;

enum NodeTextMode: string
{
    case All = 'all';
    case Visible = 'visible';
    case Own = 'own';
}
