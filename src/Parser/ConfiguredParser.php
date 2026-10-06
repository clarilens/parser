<?php

declare(strict_types=1);

namespace Clarilens\Parser\Parser;

use Clarilens\Parser\Result\ParseOutcome;

final readonly class ConfiguredParser
{
    public function __construct(public DocumentParser $parser, public ParseRequest $command)
    {
    }

    public function parse(): ParseOutcome
    {
        return $this->parser->parse($this->command);
    }
}
