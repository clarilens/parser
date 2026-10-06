<?php

declare(strict_types=1);

namespace Clarilens\Parser\Result;

final readonly class TableGrid
{
    /** @param list<list<TableCell>> $rows */
    public function __construct(public string $name, public string $nodePath, public array $rows)
    {
    }
}
