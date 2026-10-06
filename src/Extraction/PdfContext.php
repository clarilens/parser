<?php

declare(strict_types=1);

namespace Clarilens\Parser\Extraction;

use Clarilens\Parser\Parser\ParserType;
use Override;

final readonly class PdfContext implements RuleContext
{
    /** @param list<string> $pages */
    public function __construct(public array $pages)
    {
    }

    #[Override]
    public function type(): ParserType
    {
        return ParserType::PDF;
    }
}
