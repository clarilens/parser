<?php

declare(strict_types=1);

namespace Clarilens\Parser\Config;

enum CaptureSourceMode: string
{
    case Raw = 'raw';
    case Visible = 'visible';
}
