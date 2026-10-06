<?php

declare(strict_types=1);

namespace Clarilens\Parser\Extraction;

use Clarilens\Parser\Parser\ParserType;
use Clarilens\Parser\Result\ExtractedValue;
use Clarilens\Parser\Result\NodeLocation;
use Clarilens\Parser\Validation\Checks;
use DOMNode;
use InvalidArgumentException;

final readonly class TableRule implements ExtractionRule
{
    /** @param list<int> $valueColumns */
    public function __construct(
        public string $field,
        public bool $required,
        public string $rows,
        public string $label,
        public int $labelColumn,
        public array $valueColumns,
    ) {
        Checks::field($field);
        Checks::selector($rows);
        if ($label === '' || $labelColumn < 0 || $valueColumns === [] || !array_is_list($valueColumns)) {
            throw new InvalidArgumentException('Invalid table rule.');
        }
        foreach ($valueColumns as $column) {
            if (!is_int($column) || $column < 0) {
                throw new InvalidArgumentException('Invalid table value column.');
            }
        }
    }

    #[\Override]
    public function field(): string
    {
        return $this->field;
    }
    #[\Override]
    public function required(): bool
    {
        return $this->required;
    }
    #[\Override]
    public function supports(ParserType $type): bool
    {
        return $type === ParserType::HTML;
    }

    /** @return list<ExtractedValue> */
    #[\Override]
    public function extract(RuleContext $context): array
    {
        if (!$context instanceof DomContext) {
            throw new InvalidArgumentException('Table extraction requires a DOM context.');
        }
        $rows = $context->xpath->query($this->rows);
        if ($rows === false) {
            throw new InvalidArgumentException('Invalid table row selector.');
        }
        $values = [];
        foreach ($rows as $row) {
            if (!$row instanceof DOMNode) {
                continue;
            }
            $cells = $context->xpath->query('./th|./td', $row);
            $labelCell = $cells === false ? null : $cells->item($this->labelColumn);
            if ($cells === false || $this->labelColumn >= $cells->length
                || !$labelCell instanceof DOMNode
                || ($this->label !== '*' && DomValue::normalize($labelCell->textContent) !== $this->label)) {
                continue;
            }
            $parts = [];
            foreach ($this->valueColumns as $index) {
                $cell = $cells->item($index);
                if ($cell instanceof DOMNode) {
                    $part = DomValue::normalize($cell->textContent);
                    if ($part !== '') {
                        $parts[] = $part;
                    }
                }
            }
            if ($parts !== []) {
                $values[] = new ExtractedValue($this->field, implode(' | ', $parts), new NodeLocation($row->getNodePath()));
            }
        }
        return $values;
    }
}
