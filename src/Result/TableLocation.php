<?php

declare(strict_types=1);

namespace Clarilens\Parser\Result;

final readonly class TableLocation
{
    public function __construct(
        public string $nodePath,
        public int $row,
        public int $column,
        public int $originRow,
        public int $originColumn,
        public string $header,
    ) {
    }
}
