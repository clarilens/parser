<?php

declare(strict_types=1);

namespace Clarilens\Parser\Result;

enum FactState: string
{
    case Absent = 'absent';
    case NotApplicable = 'not_applicable';
    case Error = 'error';
    case Conflict = 'conflict';
}
