<?php

declare(strict_types=1);

namespace Clarilens\Parser\Result;

final readonly class CrawlOutcome
{
    /** @param list<CrawlRecord> $records
     * @param list<CrawlFailure> $failures
     * @param list<string> $incompleteSubtrees
     */
    public function __construct(
        public array $records,
        public array $failures,
        public array $incompleteSubtrees,
        public bool $traversalComplete,
        public CoverageStatus $coverage,
        public int $discoveredSources,
        public int $processedSources,
        public ?int $expectedItems,
        public int $distinctItems,
        public int $configSchemaVersion,
        public string $configHash,
        public string $registryFingerprint,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'records' => array_map(static fn (CrawlRecord $record): array => $record->toArray(), $this->records),
            'failures' => array_map(static fn (CrawlFailure $failure): array => $failure->toArray(), $this->failures),
            'incompleteSubtrees' => $this->incompleteSubtrees,
            'traversalComplete' => $this->traversalComplete,
            'coverage' => $this->coverage->value,
            'discoveredSources' => $this->discoveredSources,
            'processedSources' => $this->processedSources,
            'expectedItems' => $this->expectedItems,
            'distinctItems' => $this->distinctItems,
            'configSchemaVersion' => $this->configSchemaVersion,
            'configHash' => $this->configHash,
            'registryFingerprint' => $this->registryFingerprint,
        ];
    }
}
