<?php

declare(strict_types=1);

namespace Clarilens\Parser\Condition;

use Clarilens\Parser\Config\FieldType;
use InvalidArgumentException;

/** Closed condition tree; absent inputs evaluate to unknown. */
final readonly class Condition
{
    /** @var array<string, FieldType> */
    private array $fieldTypes;

    /** @param array<string, mixed> $expression
     * @param array<string, string> $fieldTypes
     */
    public function __construct(public array $expression, array $fieldTypes)
    {
        $this->fieldTypes = array_map(static fn (string $type): FieldType => FieldType::from($type), $fieldTypes);
        $this->validate($expression, 0);
    }

    /** @param array<string, scalar|null> $values */
    public function evaluate(array $values): ConditionResult
    {
        return ConditionResult::fromNullableBool($this->test($this->expression, $values));
    }

    /** @return list<string> */
    public function fields(): array
    {
        return array_values(array_unique($this->references($this->expression)));
    }

    /** @param array<string, mixed> $node
     * @return list<string>
     */
    private function references(array $node): array
    {
        $op = array_key_first($node);
        $operator = ConditionOperator::from($op);
        $arg = $node[$op];
        if ($operator === ConditionOperator::Not) {
            return $this->references($arg);
        }
        if ($operator === ConditionOperator::All || $operator === ConditionOperator::Any) {
            $references = [];
            foreach ($arg as $child) {
                array_push($references, ...$this->references($child));
            }
            return $references;
        }
        return $operator === ConditionOperator::Equals && isset($arg['other']) ? [$arg['field'], $arg['other']] : [$operator === ConditionOperator::Exists ? $arg : $arg['field']];
    }

    /** @param array<string, mixed> $node */
    private function validate(array $node, int $depth): void
    {
        if ($depth > 16 || count($node) !== 1) {
            throw new InvalidArgumentException('Invalid condition tree.');
        }
        $op = array_key_first($node);
        $operator = is_string($op) ? ConditionOperator::tryFrom($op) : null;
        $arg = $node[$op];
        if ($operator === ConditionOperator::All || $operator === ConditionOperator::Any) {
            if (!is_array($arg) || !array_is_list($arg) || $arg === [] || count($arg) > 32) {
                throw new InvalidArgumentException('Invalid condition operands.');
            }
            foreach ($arg as $child) {
                if (!is_array($child)) {
                    throw new InvalidArgumentException('Invalid condition operand.');
                }
                $this->validate($child, $depth + 1);
            }
            return;
        }
        if ($operator === ConditionOperator::Not) {
            if (!is_array($arg)) {
                throw new InvalidArgumentException('Invalid condition operand.');
            }
            $this->validate($arg, $depth + 1);
            return;
        }
        if ($operator === ConditionOperator::Exists) {
            $field = $arg;
        } elseif (in_array($operator, [ConditionOperator::Equals, ConditionOperator::In, ConditionOperator::Matches, ConditionOperator::Compare], true) && is_array($arg)) {
            $field = $arg['field'] ?? null;
        } else {
            throw new InvalidArgumentException('Unknown condition operator.');
        }
        if (!is_string($field) || !isset($this->fieldTypes[$field])) {
            throw new InvalidArgumentException('Unknown condition field.');
        }
        if ($operator === ConditionOperator::Equals && (!array_key_exists('value', $arg) && !is_string($arg['other'] ?? null))) {
            throw new InvalidArgumentException('Invalid equality condition.');
        }
        if ($operator === ConditionOperator::Equals && array_key_exists('value', $arg) && !$this->typed($field, $arg['value'])) {
            throw new InvalidArgumentException('Condition value type mismatch.');
        }
        if ($operator === ConditionOperator::Equals && isset($arg['other']) && ($this->fieldTypes[$arg['other']] ?? null) !== $this->fieldTypes[$field]) {
            throw new InvalidArgumentException('Condition field type mismatch.');
        }
        if ($operator === ConditionOperator::In && (!is_array($arg['values'] ?? null) || !array_is_list($arg['values']) || $arg['values'] === [])) {
            throw new InvalidArgumentException('Invalid membership condition.');
        }
        if ($operator === ConditionOperator::In) {
            foreach ($arg['values'] as $value) {
                if (!$this->typed($field, $value)) {
                    throw new InvalidArgumentException('Condition value type mismatch.');
                }
            }
        }
        if ($operator === ConditionOperator::Matches && (!is_string($arg['pattern'] ?? null) || @preg_match($arg['pattern'], '') === false)) {
            throw new InvalidArgumentException('Invalid condition regex.');
        }
        if ($operator === ConditionOperator::Matches && $this->fieldTypes[$field] === FieldType::Number) {
            throw new InvalidArgumentException('Regex requires a text field.');
        }
        if ($operator === ConditionOperator::Compare && ($this->fieldTypes[$field] !== FieldType::Number || !is_string($arg['operator'] ?? null) || NumericComparator::tryFrom($arg['operator']) === null || !is_numeric($arg['value'] ?? null))) {
            throw new InvalidArgumentException('Invalid numeric comparison.');
        }
    }

    private function typed(string $field, mixed $value): bool
    {
        return $this->fieldTypes[$field] === FieldType::Number ? is_int($value) || is_float($value) : is_string($value);
    }

    /** @param array<string, mixed> $node
     * @param array<string, scalar|null> $values
     */
    private function test(array $node, array $values): ?bool
    {
        $op = array_key_first($node);
        $operator = ConditionOperator::from($op);
        $arg = $node[$op];
        if ($operator === ConditionOperator::Not) {
            $result = $this->test($arg, $values);
            return $result === null ? null : !$result;
        }
        if ($operator === ConditionOperator::All || $operator === ConditionOperator::Any) {
            $results = array_map(fn (array $child): ?bool => $this->test($child, $values), $arg);
            if ($operator === ConditionOperator::All) {
                return in_array(false, $results, true) ? false : (in_array(null, $results, true) ? null : true);
            }
            return in_array(true, $results, true) ? true : (in_array(null, $results, true) ? null : false);
        }
        $field = $operator === ConditionOperator::Exists ? $arg : $arg['field'];
        if (!array_key_exists($field, $values) || $values[$field] === null) {
            return null;
        }
        $value = $values[$field];
        return match ($operator) {
            ConditionOperator::Exists => true,
            ConditionOperator::Equals => isset($arg['other'])
                ? (array_key_exists($arg['other'], $values) && $values[$arg['other']] !== null ? $this->equal($field, $value, $values[$arg['other']]) : null)
                : $this->equal($field, $value, $arg['value']),
            ConditionOperator::In => in_array($value, $arg['values'], true),
            ConditionOperator::Matches => preg_match($arg['pattern'], (string) $value) === 1,
            ConditionOperator::Compare => match (NumericComparator::from($arg['operator'])) {
                NumericComparator::LessThan => (float) $value < (float) $arg['value'],
                NumericComparator::LessThanOrEqual => (float) $value <= (float) $arg['value'],
                NumericComparator::GreaterThan => (float) $value > (float) $arg['value'],
                NumericComparator::GreaterThanOrEqual => (float) $value >= (float) $arg['value'],
            },
        };
    }

    private function equal(string $field, mixed $left, mixed $right): bool
    {
        return $this->fieldTypes[$field] === FieldType::Number ? (float) $left === (float) $right : $left === $right;
    }
}
