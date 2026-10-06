<?php

declare(strict_types=1);

namespace Clarilens\Parser\Result;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;

final readonly class ObservedFactBatch
{
    public const int SCHEMA_VERSION = 1;

    /** @param list<ObservedFact> $facts */
    public function __construct(
        public string $sourceId,
        public string $sourceVersion,
        public string $parserVersion,
        public DateTimeImmutable $observedAt,
        public bool $complete,
        public array $facts,
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
        if (!is_array($data) || ($data['schemaVersion'] ?? null) !== self::SCHEMA_VERSION
            || !is_string($data['sourceId'] ?? null)
            || !is_string($data['sourceVersion'] ?? null)
            || !is_string($data['parserVersion'] ?? null)
            || !is_string($data['observedAt'] ?? null)
            || !is_bool($data['complete'] ?? null)
            || !is_array($data['facts'] ?? null) || !array_is_list($data['facts'])) {
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
        );
    }
}
