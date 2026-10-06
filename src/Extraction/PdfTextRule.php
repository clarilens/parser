<?php

declare(strict_types=1);

namespace Clarilens\Parser\Extraction;

use Clarilens\Parser\Parser\ParserType;
use Clarilens\Parser\Result\ExtractedValue;
use Clarilens\Parser\Result\PageLocation;
use Clarilens\Parser\Validation\Checks;
use InvalidArgumentException;
use Override;

final readonly class PdfTextRule implements ExtractionRule
{
    public function __construct(public string $field, public bool $required)
    {
        Checks::field($field);
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
            throw new InvalidArgumentException('PDF text requires a PDF context.');
        }
        $values = [];
        foreach ($context->pages as $index => $text) {
            if (trim($text) !== '') {
                $values[] = new ExtractedValue($this->field, trim($text), new PageLocation($index + 1), $text);
            }
        }
        return $values;
    }
}
