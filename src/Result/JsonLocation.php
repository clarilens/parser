<?php

declare(strict_types=1);

namespace Clarilens\Parser\Result;

final readonly class JsonLocation
{
    public function __construct(public string $nodePath, public string $pointer)
    {
    }
}
