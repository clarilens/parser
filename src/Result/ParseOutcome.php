<?php

declare(strict_types=1);

namespace Clarilens\Parser\Result;

use InvalidArgumentException;

final readonly class ParseOutcome
{
    /**
     * @param Collection<ExtractedValue> $values
     * @param Collection<RuleFailure> $failures
     * @param Collection<TableGrid> $tables
     */
    public function __construct(
        public Collection $values,
        public Collection $failures,
        public ?DocumentOrigin $origin = null,
        public Collection $tables = new Collection(),
    ) {
    }

    /** @return Collection<ObservedFact> */
    public function facts(?string $sourceId = null): Collection
    {
        if ($this->origin === null) {
            throw new InvalidArgumentException('Document origin is required for observed facts.');
        }
        $facts = [];
        foreach ($this->values as $value) {
            $facts[] = new ObservedFact(
                $value->field,
                $value->value,
                new FactEvidence(
                    $this->origin->url,
                    $this->origin->observedAt,
                    $value->raw ?? $value->value,
                    $value->location,
                    $sourceId,
                ),
            );
        }
        return new Collection($facts);
    }
}
