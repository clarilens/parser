<?php

declare(strict_types=1);

namespace Clarilens\Parser\Source;

use InvalidArgumentException;

final readonly class SourcePolicy
{
    public const int MAX_SOURCE_BYTES = 10_000_000;

    /** @param list<string> $allowedWebHosts */
    public function __construct(
        public string $fileRoot,
        public array $allowedWebHosts = [],
    ) {
        if (!is_dir($fileRoot)) {
            throw new InvalidArgumentException('Source root does not exist.');
        }
    }
}
