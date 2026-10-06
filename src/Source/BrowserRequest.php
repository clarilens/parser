<?php

declare(strict_types=1);

namespace Clarilens\Parser\Source;

use Clarilens\Parser\Exception\UrlContainsCredentialsException;
use Clarilens\Parser\Source\Url;
use Clarilens\Parser\Validation\Checks;
use InvalidArgumentException;

final readonly class BrowserRequest
{
    /**
     * @param list<array{type: string, selector: string}> $actions
     * @param list<string> $allowedHosts
     */
    public function __construct(
        public string $url,
        public array $actions = [],
        public ?string $readySelector = null,
        public int $timeoutMs = 30000,
        public array $allowedHosts = [],
        public bool $allowLocalFile = false,
    ) {
        $parts = Url::parts($url);
        if (parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null) {
            throw new UrlContainsCredentialsException('Browser URL must not contain credentials.');
        }
        if ($parts === null
            || !(($parts['scheme'] === 'file' && $allowLocalFile)
                || (in_array($parts['scheme'], ['http', 'https'], true)
                    && in_array($parts['host'], $allowedHosts, true)
                    && self::publicHost($parts['host'])))
            || count($actions) > 5) {
            throw new InvalidArgumentException('Invalid browser request.');
        }
        Checks::timeout($timeoutMs);
        if ($readySelector !== null) {
            Checks::selector($readySelector);
        }
        foreach ($actions as $action) {
            if (($action['type'] ?? null) !== 'click' || !is_string($action['selector'] ?? null)) {
                throw new InvalidArgumentException('Invalid browser action.');
            }
            Checks::selector($action['selector']);
        }
    }

    private static function publicHost(string $host): bool
    {
        return filter_var($host, FILTER_VALIDATE_IP) === false
            || filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
