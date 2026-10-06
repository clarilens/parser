<?php

declare(strict_types=1);

namespace Clarilens\Parser\Extraction;

use Clarilens\Parser\Parser\ParserType;
use Clarilens\Parser\Result\ExtractedValue;
use InvalidArgumentException;
use Override;
use Symfony\Component\CssSelector\CssSelectorConverter;

final readonly class CssRule implements ExtractionRule
{
    private XPathRule $xpathRule;

    public function __construct(
        public string $field,
        public bool $required,
        public string $selector,
        public NodeTextMode $textMode = NodeTextMode::All,
    ) {
        try {
            $xpath = (new CssSelectorConverter())->toXPath($selector);
        } catch (\Throwable $error) {
            throw new InvalidArgumentException('Invalid CSS selector.', previous: $error);
        }
        $this->xpathRule = new XPathRule($field, $required, $xpath, $textMode);
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
        return $type === ParserType::HTML;
    }

    /** @return list<ExtractedValue> */
    #[Override]
    public function extract(RuleContext $context): array
    {
        return $this->xpathRule->extract($context);
    }
}
