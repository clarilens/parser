<?php

declare(strict_types=1);

namespace Clarilens\Parser\Extraction;

use Clarilens\Parser\Parser\ParserType;
use Clarilens\Parser\Result\ExtractedValue;
use Clarilens\Parser\Validation\Checks;
use DOMNode;
use InvalidArgumentException;
use Override;

final readonly class XPathRule implements ExtractionRule
{
    public function __construct(public string $field, public bool $required, public string $selector, public NodeTextMode $textMode = NodeTextMode::All)
    {
        Checks::field($field);
        Checks::selector($selector);
    }

    #[Override]
    public function field(): string
    {
        return $this->field;
    }

    #[Override]
    public function required(): bool
    {
        return $this->required;
    }

    #[Override]
    public function supports(ParserType $type): bool
    {
        return $type === ParserType::HTML || $type === ParserType::XML;
    }

    /** @return list<ExtractedValue> */
    #[Override]
    public function extract(RuleContext $context): array
    {
        if (!$context instanceof DomContext) {
            throw new InvalidArgumentException('XPath requires a DOM context.');
        }

        $matches = $context->xpath->query($this->selector);
        if ($matches === false) {
            throw new InvalidArgumentException('Invalid XPath selector.');
        }

        $values = [];
        foreach ($matches as $node) {
            if ($node instanceof DOMNode && ($value = DomValue::fromNode($this->field, $node, $this->textMode)) !== null) {
                $values[] = $value;
            }
        }

        return $values;
    }
}
