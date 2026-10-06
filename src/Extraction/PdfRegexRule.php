<?php

declare(strict_types=1);

namespace Clarilens\Parser\Extraction;

use Clarilens\Parser\Parser\ParserType;
use Clarilens\Parser\Result\ExtractedValue;
use Clarilens\Parser\Result\PageLocation;
use Clarilens\Parser\Validation\Checks;
use InvalidArgumentException;
use Override;

final readonly class PdfRegexRule implements ExtractionRule
{
    public function __construct(public string $field, public bool $required, public string $pattern)
    {
        Checks::field($field);
        Checks::regex($pattern);
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
        return $type === ParserType::PDF;
    }

    /** @return list<ExtractedValue> */
    #[Override]
    public function extract(RuleContext $context): array
    {
        if (!$context instanceof PdfContext) {
            throw new InvalidArgumentException('PDF regex requires a PDF context.');
        }
        $values = [];
        foreach ($context->pages as $index => $text) {
            preg_match_all($this->pattern, $text, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $value = trim($match[1] ?? $match[0]);
                if ($value !== '') {
                    $values[] = new ExtractedValue($this->field, $value, new PageLocation($index + 1), $match[0]);
                }
            }
        }
        return $values;
    }
}
