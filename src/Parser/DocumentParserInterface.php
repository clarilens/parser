<?php

declare(strict_types=1);

namespace Clarilens\Parser\Parser;

/**
 * @template TRequest
 * @template TResult
 */
interface DocumentParserInterface
{
    /**
     * @param TRequest $request
     * @return TResult
     */
    public function parse(object $request): object;
}
