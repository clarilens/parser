<?php

declare(strict_types=1);

namespace Clarilens\Parser\Result;

use Clarilens\Parser\Validation\Checks;
use InvalidArgumentException;

final readonly class ObservedFact
{
    public function __construct(
        public string $field,
        public string $value,
        public FactEvidence $evidence,
        public ?string $groupKey = null,
    ) {
        Checks::field($field);
        if ($value === '' || ($groupKey !== null && $groupKey === '')) {
            throw new InvalidArgumentException('Invalid observed fact.');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'value' => $this->value,
            'evidence' => $this->evidence->toArray(),
            ...($this->groupKey === null ? [] : ['groupKey' => $this->groupKey]),
        ];
    }

    public static function fromArray(mixed $data): self
    {
        if (!is_array($data) || !is_string($data['field'] ?? null)
            || !is_string($data['value'] ?? null)
            || (isset($data['groupKey']) && !is_string($data['groupKey']))) {
            throw new InvalidArgumentException('Invalid observed fact.');
        }
        return new self(
            $data['field'],
            $data['value'],
            FactEvidence::fromArray($data['evidence'] ?? null),
            $data['groupKey'] ?? null,
        );
    }
}
