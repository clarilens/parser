<?php

declare(strict_types=1);

namespace Clarilens\Parser\Crawl;

use RuntimeException;

final class RetryExhausted extends RuntimeException
{
    public function __construct(public readonly int $attempts, \Throwable $previous)
    {
        parent::__construct('retry_exhausted: ' . $previous->getMessage(), previous: $previous);
    }
}
