<?php

declare(strict_types=1);

namespace Clarilens\Parser\Extraction;

use Clarilens\Parser\Config\DecimalSeparator;
use Clarilens\Parser\Config\OperationRegistry;
use Clarilens\Parser\Config\TransformOperation;
use InvalidArgumentException;

final class FactTransform
{
    /** @param list<array<string, mixed>> $transforms */
    public static function apply(string $raw, array $transforms, OperationRegistry $registry): string
    {
        $value = $raw;
        foreach ($transforms as $transform) {
            $value = match (TransformOperation::from($transform['op'])) {
                TransformOperation::Trim => trim($value),
                TransformOperation::Number => self::number($value, $transform),
                TransformOperation::Enum => self::enum($value, $transform),
                TransformOperation::Url => self::url($value, $transform),
                TransformOperation::Registered => $registry->apply($transform['id'], $transform['version'], $value),
                default => throw new InvalidArgumentException('Unknown transform.'),
            };
        }
        return $value;
    }

    /** @param array<string, mixed> $options */
    private static function number(string $value, array $options): string
    {
        $unit = $options['unit'] ?? '';
        if (!is_string($unit)) {
            throw new InvalidArgumentException('Invalid unit.');
        }
        $text = str_replace(["\u{00A0}", "\u{202F}"], ' ', trim($value));
        if ($unit !== '') {
            if (!str_ends_with($text, $unit)) {
                throw new InvalidArgumentException('Missing declared unit.');
            }
            $text = trim(substr($text, 0, -strlen($unit)));
        }
        $decimal = DecimalSeparator::tryFrom($options['decimal'] ?? DecimalSeparator::Dot->value);
        if ($decimal === null || !preg_match($decimal === DecimalSeparator::Comma ? '/^-?\d{1,3}(?:[ .]\d{3})*(?:,\d+)?$|^-?\d+(?:,\d+)?$/u' : '/^-?\d{1,3}(?:[ ,]\d{3})*(?:\.\d+)?$|^-?\d+(?:\.\d+)?$/u', $text)) {
            throw new InvalidArgumentException('Ambiguous number.');
        }
        $group = $decimal === DecimalSeparator::Comma ? [' ', '.'] : [' ', ','];
        $number = str_replace($group, '', $text);
        return str_replace(',', '.', $number);
    }

    /** @param array<string, mixed> $options */
    private static function enum(string $value, array $options): string
    {
        foreach ($options['map'] ?? [] as $name => $aliases) {
            if (is_array($aliases) && in_array($value, $aliases, true)) {
                return (string) $name;
            }
        }
        throw new InvalidArgumentException('Unknown enum value.');
    }

    /** @param array<string, mixed> $options */
    private static function url(string $value, array $options): string
    {
        $base = $options['base'] ?? null;
        if (!is_string($base) || !filter_var($base, FILTER_VALIDATE_URL) || str_starts_with($value, '//')) {
            throw new InvalidArgumentException('Invalid URL base or value.');
        }
        $url = filter_var($value, FILTER_VALIDATE_URL) ? $value : rtrim($base, '/') . '/' . ltrim($value, '/');
        if (!in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true) || parse_url($url, PHP_URL_HOST) !== parse_url($base, PHP_URL_HOST)) {
            throw new InvalidArgumentException('Unsafe URL.');
        }
        return $url;
    }
}
