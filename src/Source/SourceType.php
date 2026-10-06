<?php

declare(strict_types=1);

namespace Clarilens\Parser\Source;

enum SourceType: string
{
    case File = 'file';
    case Web = 'web';
}
