<?php

declare(strict_types=1);

namespace Clarilens\Parser\Extraction;

use Clarilens\Parser\Parser\ParserType;
use Clarilens\Parser\Result\ExtractedValue;

interface ExtractionRule
{
    public function field(): string;

    public function required(): bool;

    public function supports(ParserType $type): bool;

    /** @return list<ExtractedValue> */
    public function extract(RuleContext $context): array;
}
