<?php

declare(strict_types=1);

namespace Clarilens\Parser\Extraction;

use Clarilens\Parser\Result\TableCell;
use Clarilens\Parser\Result\TableGrid;
use Clarilens\Parser\Validation\Checks;
use DOMElement;
use InvalidArgumentException;
use Symfony\Component\CssSelector\CssSelectorConverter;

final readonly class TableGridRule
{
    private string $xpath;

    public function __construct(
        public string $name,
        public string $selector,
        public bool $required = true,
        public string $syntax = SelectorSyntax::Css->value,
    ) {
        Checks::field($name);
        Checks::selector($selector);
        $selectorSyntax = SelectorSyntax::tryFrom($syntax);
        if ($selectorSyntax === null) {
            throw new InvalidArgumentException('Invalid table selector syntax.');
        }
        try {
            $this->xpath = $selectorSyntax === SelectorSyntax::Css ? (new CssSelectorConverter())->toXPath($selector) : $selector;
        } catch (\Throwable $error) {
            throw new InvalidArgumentException('Invalid table selector.', previous: $error);
        }
    }

    /** @return list<TableGrid> */
    public function extract(DomContext $context): array
    {
        $nodes = $context->xpath->query($this->xpath);
        if ($nodes === false) {
            throw new InvalidArgumentException('Invalid table selector.');
        }
        $tables = [];
        foreach ($nodes as $table) {
            if (!$table instanceof DOMElement || strtolower($table->tagName) !== 'table') {
                throw new InvalidArgumentException('Table selector must resolve to tables.');
            }
            $rows = $context->xpath->query('./thead/tr|./tbody/tr|./tfoot/tr|./tr', $table);
            if ($rows === false || $rows->length > 500) {
                throw new InvalidArgumentException('Invalid or oversized table.');
            }
            /** @var array<int, array<int, TableCell>> $grid */
            $grid = [];
            foreach ($rows as $rowIndex => $row) {
                $cells = $context->xpath->query('./th|./td', $row);
                if ($cells === false) {
                    throw new InvalidArgumentException('Invalid table cells.');
                }
                $column = 0;
                foreach ($cells as $cell) {
                    if (!$cell instanceof DOMElement) {
                        continue;
                    }
                    while (isset($grid[$rowIndex][$column])) {
                        ++$column;
                    }
                    $rowSpan = $this->span($cell, 'rowspan');
                    $colSpan = $this->span($cell, 'colspan');
                    if ($rowIndex + $rowSpan > 500 || $column + $colSpan > 100) {
                        throw new InvalidArgumentException('Table span exceeds limits.');
                    }
                    $text = DomValue::normalize($cell->textContent);
                    for ($r = $rowIndex; $r < $rowIndex + $rowSpan; ++$r) {
                        for ($c = $column; $c < $column + $colSpan; ++$c) {
                            if (isset($grid[$r][$c])) {
                                throw new InvalidArgumentException('Overlapping table cells.');
                            }
                            $grid[$r][$c] = new TableCell($r, $c, $text, $cell->getNodePath(), $rowIndex, $column);
                        }
                    }
                    $column += $colSpan;
                }
            }
            ksort($grid);
            $normalized = [];
            foreach ($grid as $row) {
                ksort($row);
                $normalized[] = array_values($row);
            }
            $tables[] = new TableGrid($this->name, $table->getNodePath(), $normalized);
        }
        return $tables;
    }

    private function span(DOMElement $cell, string $attribute): int
    {
        $value = $cell->getAttribute($attribute);
        if ($value === '') {
            return 1;
        }
        if (!ctype_digit($value) || (int) $value < 1 || (int) $value > 100) {
            throw new InvalidArgumentException('Invalid table span.');
        }
        return (int) $value;
    }

    public static function fromArray(mixed $data): self
    {
        if (!is_array($data) || !is_string($data['name'] ?? null)
            || !is_string($data['selector'] ?? null)
            || !is_bool($data['required'] ?? true)
            || !is_string($data['syntax'] ?? SelectorSyntax::Css->value)) {
            throw new InvalidArgumentException('Invalid table grid rule.');
        }
        return new self($data['name'], $data['selector'], $data['required'] ?? true, $data['syntax'] ?? SelectorSyntax::Css->value);
    }

    /** @return array{name: string, selector: string, required: bool, syntax: string} */
    public function toArray(): array
    {
        return ['name' => $this->name, 'selector' => $this->selector, 'required' => $this->required, 'syntax' => $this->syntax];
    }
}
