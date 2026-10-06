<?php

declare(strict_types=1);

namespace Clarilens\Parser\Extraction;

use Clarilens\Parser\Parser\ParserType;

interface RuleContext
{
    public function type(): ParserType;
}
