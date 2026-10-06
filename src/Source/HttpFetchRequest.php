<?php

declare(strict_types=1);

namespace Clarilens\Parser\Source;

use Clarilens\Parser\Source\Url;
use InvalidArgumentException;

final readonly class HttpFetchRequest
{
    /** @param list<string> $allowedHosts */
    public function __construct(
        public string $url,
        public array $allowedHosts,
        public int $maxBytes,
        public int $timeoutMs = 30000,
    ) {
        $parts = Url::parts($url);
        if ($parts === null || !in_array($parts['scheme'], ['http', 'https'], true)
            || parse_url($url, PHP_URL_USER) !== null
            || parse_url($url, PHP_URL_PASS) !== null
            || !in_array($parts['host'], $allowedHosts, true)
            || $maxBytes < 1 || $maxBytes > SourcePolicy::MAX_SOURCE_BYTES
            || $timeoutMs < 1 || $timeoutMs > 30000
        ) {
            throw new InvalidArgumentException('Invalid HTTP source request.');
        }
    }
}
