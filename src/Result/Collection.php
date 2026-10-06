<?php

declare(strict_types=1);

namespace Clarilens\Parser\Result;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @template T
 * @implements IteratorAggregate<int, T>
 */
final readonly class Collection implements IteratorAggregate, Countable
{
    /** @param list<T> $items */
    public function __construct(public array $items = [])
    {
    }

    /** @return Traversable<int, T> */
    #[\Override]
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    #[\Override]
    public function count(): int
    {
        return count($this->items);
    }
}
