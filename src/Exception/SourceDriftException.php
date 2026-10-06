<?php

declare(strict_types=1);

namespace Clarilens\Parser\Exception;

use Clarilens\Parser\Result\RuleFailure;
use RuntimeException;

final class SourceDriftException extends RuntimeException
{
    /** @param list<RuleFailure> $failures */
    public function __construct(public readonly array $failures)
    {
        parent::__construct('Source did not satisfy parser checks.');
    }
}
