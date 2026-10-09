<?php

declare(strict_types=1);

namespace Clarilens\Parser\Extraction;

use InvalidArgumentException;

final class TextCapture
{
    /** @return list<array{value: string, quote: string, offset: int}> */
    public static function find(string $raw, ?string $pattern, bool $normalize): array
    {
        if (!preg_match('//u', $raw)) {
            throw new InvalidArgumentException('Invalid UTF-8 source text.');
        }
        $text = $raw;
        /** @var list<array{int, int}> $map */
        $map = [];
        if ($normalize) {
            preg_match_all('/\s+|\S/u', $raw, $parts, PREG_OFFSET_CAPTURE);
            $text = '';
            foreach ($parts[0] as [$part, $byte]) {
                $piece = preg_match('/^\s+$/u', $part) ? ' ' : $part;
                $text .= $piece;
                for ($i = 0; $i < strlen($piece); ++$i) {
                    $map[] = [$byte, $byte + strlen($part)];
                }
            }
        }
        if ($pattern === null) {
            $matches = [[[$text, 0]]];
        } else {
            $count = preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
            if ($count === false) {
                throw new InvalidArgumentException('Invalid capture pattern.');
            }
        }
        $found = [];
        foreach ($matches as $match) {
            [$value, $start] = $match['value'] ?? $match[0];
            if ($value === '') {
                continue;
            }
            $end = $start + strlen($value);
            $rawStart = $normalize ? ($map[$start][0] ?? null) : $start;
            $rawEnd = $normalize && $end > 0 ? ($map[$end - 1][1] ?? null) : $end;
            if ($rawStart === null || $rawEnd === null) {
                throw new InvalidArgumentException('Unmappable text capture.');
            }
            $quote = substr($raw, $rawStart, $rawEnd - $rawStart);
            preg_match_all('/./us', substr($raw, 0, $rawStart), $prefix);
            $found[] = ['value' => $value, 'quote' => $quote, 'offset' => count($prefix[0])];
        }
        return $found;
    }
}
