<?php

declare(strict_types=1);

namespace Clarilens\Parser\Result;

final readonly class TableCell
{
    public function __construct(
        public int $row,
        public int $column,
        public string $text,
        public string $nodePath,
    ) {
    }
}
