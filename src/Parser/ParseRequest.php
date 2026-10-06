<?php

declare(strict_types=1);

namespace Clarilens\Parser\Parser;

use Clarilens\Parser\Config\ParserConfig;
use Clarilens\Parser\Result\DocumentOrigin;
use InvalidArgumentException;

final readonly class ParseRequest
{
    /** @param ?list<string> $pdfPages */
    public function __construct(
        public ParserConfig $config,
        public ?string $content = null,
        public ?array $pdfPages = null,
        public ?DocumentOrigin $origin = null,
    ) {
        if ($content !== null && $pdfPages !== null) {
            throw new InvalidArgumentException('Content and PDF pages are mutually exclusive.');
        }
    }
}
