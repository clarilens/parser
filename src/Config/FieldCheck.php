<?php

declare(strict_types=1);

namespace Clarilens\Parser\Config;

use Clarilens\Parser\Result\ExtractedValue;
use Clarilens\Parser\Result\RuleFailure;
use Clarilens\Parser\Validation\Checks;
use InvalidArgumentException;

final readonly class FieldCheck
{
    public function __construct(
        public string $field,
        public int $minCount = 0,
        public ?int $maxCount = null,
        public ?string $pattern = null,
        public ?string $equals = null,
    ) {
        Checks::field($field);
        if ($minCount < 0 || $maxCount !== null && $maxCount < $minCount) {
            throw new InvalidArgumentException('Invalid field count check.');
        }
        if ($pattern !== null && ($pattern === '' || @preg_match($pattern, '') === false)) {
            throw new InvalidArgumentException('Invalid field pattern check.');
        }
    }

    /**
     * @param list<ExtractedValue> $values
     * @return list<RuleFailure>
     **/
    public function verify(array $values): array
    {
        $matching = array_values(
            array_filter($values, fn (ExtractedValue $value): bool => $value->field === $this->field),
        );
        $count = count($matching);
        $failures = [];
        if ($count < $this->minCount || $this->maxCount !== null && $count > $this->maxCount) {
            $failures[] = new RuleFailure($this->field, 'Unexpected value count.');
        }
        foreach ($matching as $value) {
            if ($this->pattern !== null && preg_match($this->pattern, $value->value) !== 1
                || $this->equals !== null && $value->value !== $this->equals) {
                $failures[] = new RuleFailure($this->field, 'Value failed field check.');
            }
        }
        return $failures;
    }

    public static function fromArray(mixed $data): self
    {
        if (!is_array($data) || !is_string($data['field'] ?? null)
            || !is_int($data['minCount'] ?? 0)
            || (isset($data['maxCount']) && !is_int($data['maxCount']))
            || (isset($data['pattern']) && !is_string($data['pattern']))
            || (isset($data['equals']) && !is_string($data['equals']))) {
            throw new InvalidArgumentException('Invalid field check.');
        }
        return new self(
            $data['field'],
            $data['minCount'] ?? 0,
            $data['maxCount'] ?? null,
            $data['pattern'] ?? null,
            $data['equals'] ?? null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'minCount' => $this->minCount,
            ...($this->maxCount === null ? [] : ['maxCount' => $this->maxCount]),
            ...($this->pattern === null ? [] : ['pattern' => $this->pattern]),
            ...($this->equals === null ? [] : ['equals' => $this->equals]),
        ];
    }
}
