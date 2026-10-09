<?php

declare(strict_types=1);

namespace Clarilens\Parser\Result;

use InvalidArgumentException;

final readonly class FactResolution
{
    public function __construct(
        public string $field,
        public FactState $state,
        public string $ruleId,
        public ?string $groupKey = null,
        public ?string $reason = null,
        /** @var list<FactEvidence> */
        public array $evidences = [],
    ) {
        if ($field === '' || $ruleId === ''
            || ($groupKey !== null && $groupKey === '')) {
            throw new InvalidArgumentException('Invalid fact resolution.');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['field' => $this->field, 'state' => $this->state->value, 'ruleId' => $this->ruleId, 'groupKey' => $this->groupKey, 'reason' => $this->reason, 'failureId' => $this->failureId(), 'evidences' => array_map(static fn (FactEvidence $evidence): array => $evidence->toArray(), $this->evidences)];
    }

    public function failureId(): string
    {
        return hash('sha256', $this->ruleId . "\0" . ($this->groupKey ?? '') . "\0" . $this->field . "\0" . $this->state->value);
    }

    public static function fromArray(mixed $data): self
    {
        if (!is_array($data) || !is_string($data['field'] ?? null) || !is_string($data['state'] ?? null) || !is_string($data['ruleId'] ?? null)
            || (isset($data['groupKey']) && !is_string($data['groupKey']))
            || (isset($data['reason']) && !is_string($data['reason']))
            || !is_array($data['evidences'] ?? []) || !array_is_list($data['evidences'] ?? [])) {
            throw new InvalidArgumentException('Invalid fact resolution.');
        }
        $state = FactState::tryFrom($data['state']);
        if ($state === null) {
            throw new InvalidArgumentException('Invalid fact resolution state.');
        }
        return new self($data['field'], $state, $data['ruleId'], $data['groupKey'] ?? null, $data['reason'] ?? null, array_map(FactEvidence::fromArray(...), $data['evidences'] ?? []));
    }
}
