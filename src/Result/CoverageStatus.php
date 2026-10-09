<?php

declare(strict_types=1);

namespace Clarilens\Parser\Result;

enum CoverageStatus: string
{
    case Unconfirmed = 'unconfirmed';
    case Confirmed = 'confirmed';
    case Failed = 'failed';
}
