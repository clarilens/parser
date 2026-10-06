<?php

declare(strict_types=1);

namespace Clarilens\Parser\Extraction;

use Clarilens\Parser\Parser\ParserType;
use DOMDocument;
use DOMXPath;
use Override;

final readonly class DomContext implements RuleContext
{
    public DOMXPath $xpath;

    public function __construct(public ParserType $parserType, public DOMDocument $document, array $namespaces = [])
    {
        $this->xpath = new DOMXPath($document);
        foreach ($namespaces as $prefix => $uri) {
            $this->xpath->registerNamespace($prefix, $uri);
        }
    }

    #[Override]
    public function type(): ParserType
    {
        return $this->parserType;
    }
}
