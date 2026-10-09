<?php

declare(strict_types=1);

namespace Clarilens\Parser\Result;

final readonly class CrawlRecord
{
    /** @param array<string, scalar|null> $fields */
    public function __construct(
        public string $key,
        public string $type,
        public ?string $parentKey,
        public array $fields,
        public string $sourceUrl,
        public string $location,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['key' => $this->key, 'type' => $this->type, 'parentKey' => $this->parentKey, 'fields' => $this->fields, 'sourceUrl' => $this->sourceUrl, 'location' => $this->location];
    }
}
