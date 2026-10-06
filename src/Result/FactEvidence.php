<?php

declare(strict_types=1);

namespace Clarilens\Parser\Result;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class FactEvidence
{
    public function __construct(
        public string $url,
        public DateTimeImmutable $observedAt,
        public string $wording,
        public NodeLocation|PageLocation|JsonLocation|null $location = null,
        public ?string $sourceId = null,
        public ?string $applicability = null,
    ) {
        if (trim($url) === '' || trim($wording) === ''
            || ($sourceId !== null && trim($sourceId) === '')
            || ($applicability !== null && trim($applicability) === '')) {
            throw new InvalidArgumentException('Invalid fact evidence.');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $location = match (true) {
            $this->location instanceof NodeLocation => ['nodePath' => $this->location->nodePath],
            $this->location instanceof JsonLocation => ['nodePath' => $this->location->nodePath, 'pointer' => $this->location->pointer],
            $this->location instanceof PageLocation => ['page' => $this->location->page],
            default => null,
        };
        return [
            'url' => $this->url,
            'observedAt' => $this->observedAt->format(DATE_ATOM),
            'wording' => $this->wording,
            ...($location === null ? [] : ['location' => $location]),
            ...($this->sourceId === null ? [] : ['sourceId' => $this->sourceId]),
            ...($this->applicability === null ? [] : ['applicability' => $this->applicability]),
        ];
    }

    public static function fromArray(mixed $data): self
    {
        if (!is_array($data) || !is_string($data['url'] ?? null)
            || !is_string($data['observedAt'] ?? null)
            || !is_string($data['wording'] ?? null)
            || (isset($data['sourceId']) && !is_string($data['sourceId']))
            || (isset($data['applicability']) && !is_string($data['applicability']))) {
            throw new InvalidArgumentException('Invalid fact evidence.');
        }
        try {
            $observedAt = new DateTimeImmutable($data['observedAt']);
        } catch (\Exception $error) {
            throw new InvalidArgumentException('Invalid evidence time.', previous: $error);
        }
        $location = $data['location'] ?? null;
        if ($location !== null && !is_array($location)) {
            throw new InvalidArgumentException('Invalid evidence location.');
        }
        $place = null;
        if ($location !== null) {
            if (isset($location['pointer']) && is_string($location['nodePath'] ?? null) && is_string($location['pointer'])) {
                $place = new JsonLocation($location['nodePath'], $location['pointer']);
            } elseif (is_string($location['nodePath'] ?? null)) {
                $place = new NodeLocation($location['nodePath']);
            } elseif (is_int($location['page'] ?? null) && $location['page'] > 0) {
                $place = new PageLocation($location['page']);
            } else {
                throw new InvalidArgumentException('Invalid evidence location.');
            }
        }
        return new self(
            $data['url'],
            $observedAt,
            $data['wording'],
            $place,
            $data['sourceId'] ?? null,
            $data['applicability'] ?? null,
        );
    }
}
