<?php

declare(strict_types=1);

namespace Clarilens\Parser\Result;

use DateTimeImmutable;

final readonly class DocumentOrigin
{
    public function __construct(public string $url, public DateTimeImmutable $observedAt)
    {
    }
}
