<?php

declare(strict_types=1);

namespace Clarilens\Parser\Result;

final readonly class PageLocation
{
    public function __construct(public int $page)
    {
        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be positive.');
        }
    }
}
