<?php

declare(strict_types=1);

namespace Clarilens\Parser\Source;

final class Url
{
    /** @return array{scheme: string, host: string}|null */
    public static function parts(mixed $url): ?array
    {
        if (!is_string($url) || $url === '' || strlen($url) > 2048
            || preg_match('/[\\x00-\\x20\\x7f\\\\]/', $url)
            || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'])) {
            return null;
        }
        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host'] ?? '');
        if ($scheme === 'file' && $host === '' && str_starts_with($parts['path'] ?? '', '/')) {
            return ['scheme' => $scheme, 'host' => $host];
        }
        if (!in_array($scheme, ['http', 'https'], true) || $host === '' || str_ends_with($host, '.')) {
            return null;
        }
        $ip = trim($host, '[]');
        if (filter_var($ip, FILTER_VALIDATE_IP) === false
            && !preg_match('/^(?=.{1,253}$)[a-z0-9]+(?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9]+(?:[a-z0-9-]*[a-z0-9])?)*$/D', $host)) {
            return null;
        }
        return ['scheme' => $scheme, 'host' => $host];
    }
}
