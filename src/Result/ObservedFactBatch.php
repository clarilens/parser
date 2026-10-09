<?php

declare(strict_types=1);

namespace Clarilens\Parser\Result;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;

final readonly class ObservedFactBatch
{
    public const int SCHEMA_VERSION = 2;

    /** @param list<ObservedFact> $facts */
    public function __construct(
        public string $sourceId,
        public string $sourceVersion,
        public string $parserVersion,
        public DateTimeImmutable $observedAt,
        public bool $complete,
        public array $facts,
        /** @var list<FactResolution> */
        public array $resolutions = [],
        /** @var array<string, bool> */
        public array $groupCompleteness = [],
        public ?string $configHash = null,
        public ?string $registryFingerprint = null,
        public ?int $configSchemaVersion = null,
    ) {
        if (trim($sourceId) === '' || trim($sourceVersion) === '' || trim($parserVersion) === ''
            || !array_is_list($facts)) {
            throw new InvalidArgumentException('Invalid observed fact batch.');
        }
        foreach ($facts as $fact) {
            if (!$fact instanceof ObservedFact || ($fact->evidence->sourceId !== null && $fact->evidence->sourceId !== $sourceId)) {
                throw new InvalidArgumentException('Fact belongs to another source.');
            }
        }
        if (!array_is_list($resolutions)) {
            throw new InvalidArgumentException('Invalid fact resolutions.');
        }
        foreach ($resolutions as $resolution) {
            if (!$resolution instanceof FactResolution) {
                throw new InvalidArgumentException('Invalid fact resolution.');
            }
        }
        foreach ($groupCompleteness as $group => $state) {
            if (!is_string($group) || !is_bool($state)) {
                throw new InvalidArgumentException('Invalid group completeness.');
            }
        }
    }

    public static function fromOutcome(
        ParseOutcome $outcome,
        string $sourceId,
        string $sourceVersion,
        string $parserVersion,
        bool $complete = false,
    ): self {
        if ($outcome->origin === null) {
            throw new InvalidArgumentException('Document origin is required for observed facts.');
        }
        return new self(
            $sourceId,
            $sourceVersion,
            $parserVersion,
            $outcome->origin->observedAt,
            $complete,
            $outcome->facts($sourceId)->items,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'sourceId' => $this->sourceId,
            'sourceVersion' => $this->sourceVersion,
            'parserVersion' => $this->parserVersion,
            'observedAt' => $this->observedAt->format(DATE_ATOM),
            'complete' => $this->complete,
            'facts' => array_map(static fn (ObservedFact $fact): array => $fact->toArray(), $this->facts),
            'resolutions' => array_map(static fn (FactResolution $resolution): array => $resolution->toArray(), $this->resolutions),
            'groupCompleteness' => $this->groupCompleteness,
            'configHash' => $this->configHash,
            'registryFingerprint' => $this->registryFingerprint,
            'configSchemaVersion' => $this->configSchemaVersion,
        ];
    }

    /** @throws JsonException */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @throws JsonException */
    public static function fromJson(string $json): self
    {
        return self::fromArray(json_decode($json, true, 64, JSON_THROW_ON_ERROR));
    }

    public static function fromArray(mixed $data): self
    {
        if (!is_array($data) || !in_array($data['schemaVersion'] ?? null, [1, self::SCHEMA_VERSION], true)
            || !is_string($data['sourceId'] ?? null)
            || !is_string($data['sourceVersion'] ?? null)
            || !is_string($data['parserVersion'] ?? null)
            || !is_string($data['observedAt'] ?? null)
            || !is_bool($data['complete'] ?? null)
            || !is_array($data['facts'] ?? null) || !array_is_list($data['facts'])
            || !is_array($data['resolutions'] ?? []) || !array_is_list($data['resolutions'] ?? [])
            || !is_array($data['groupCompleteness'] ?? [])
            || (isset($data['configHash']) && !is_string($data['configHash']))
            || (isset($data['registryFingerprint']) && !is_string($data['registryFingerprint']))
            || (isset($data['configSchemaVersion']) && !is_int($data['configSchemaVersion']))) {
            throw new InvalidArgumentException('Invalid observed fact batch.');
        }
        try {
            $observedAt = new DateTimeImmutable($data['observedAt']);
        } catch (\Exception $error) {
            throw new InvalidArgumentException('Invalid observation time.', previous: $error);
        }
        return new self(
            $data['sourceId'],
            $data['sourceVersion'],
            $data['parserVersion'],
            $observedAt,
            $data['complete'],
            array_map(ObservedFact::fromArray(...), $data['facts']),
            array_map(FactResolution::fromArray(...), $data['resolutions'] ?? []),
            $data['groupCompleteness'] ?? [],
            $data['configHash'] ?? null,
            $data['registryFingerprint'] ?? null,
            $data['configSchemaVersion'] ?? null,
        );
    }
}
