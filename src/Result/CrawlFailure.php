<?php

declare(strict_types=1);

namespace Clarilens\Parser\Result;

final readonly class CrawlFailure
{
    public function __construct(public string $id, public string $stage, public string $branch, public CrawlFailureCode $code, public ?string $detail = null, public int $attempts = 1)
    {
    }

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return ['id' => $this->id, 'stage' => $this->stage, 'branch' => $this->branch, 'code' => $this->code->value, 'detail' => $this->detail, 'attempts' => $this->attempts];
    }
}
