<?php

declare(strict_types=1);

namespace Clarilens\Parser\Result;

use Clarilens\Parser\Validation\Checks;

final readonly class RuleFailure
{
    public function __construct(public string $field, public string $reason)
    {
        Checks::field($field);
    }
}
