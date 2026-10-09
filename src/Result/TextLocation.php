<?php

declare(strict_types=1);

namespace Clarilens\Parser\Result;

final readonly class TextLocation
{
    public function __construct(public string $nodePath, public int $offset)
    {
    }
}
