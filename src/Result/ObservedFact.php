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
        /** @var list<FactEvidence> */
        public array $evidences = [],
        public ?string $rawInput = null,
        /** @var list<string> */
        public array $operations = [],
    ) {
        Checks::field($field);
        if ($value === '' || ($groupKey !== null && $groupKey === '')) {
            throw new InvalidArgumentException('Invalid observed fact.');
        }
        foreach ($evidences as $item) {
            if (!$item instanceof FactEvidence) {
                throw new InvalidArgumentException('Invalid fact evidence list.');
            }
        }
    }

    /** @return non-empty-list<FactEvidence> */
    public function allEvidence(): array
    {
        return $this->evidences === [] ? [$this->evidence] : $this->evidences;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'value' => $this->value,
            'evidence' => $this->evidence->toArray(),
            'evidences' => array_map(static fn (FactEvidence $item): array => $item->toArray(), $this->allEvidence()),
            ...($this->rawInput === null ? [] : ['rawInput' => $this->rawInput]),
            ...($this->operations === [] ? [] : ['operations' => $this->operations]),
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
            isset($data['evidences']) && is_array($data['evidences']) ? array_map(FactEvidence::fromArray(...), $data['evidences']) : [],
            $data['rawInput'] ?? null,
            $data['operations'] ?? [],
        );
    }
}
