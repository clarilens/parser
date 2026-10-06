<?php

declare(strict_types=1);

namespace Clarilens\Parser\Extraction;

use Clarilens\Parser\Result\ExtractedValue;
use Clarilens\Parser\Result\NodeLocation;
use DOMElement;
use DOMNode;
use DOMText;

final class DomValue
{
    public static function normalize(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', str_replace("\xc2\xa0", ' ', $text)) ?? $text);
    }

    public static function fromNode(string $field, DOMNode $node, NodeTextMode $mode = NodeTextMode::All): ?ExtractedValue
    {
        $text = match ($mode) {
            NodeTextMode::All => $node->textContent,
            NodeTextMode::Visible => implode(' ', self::visibleParts($node)),
            NodeTextMode::Own => self::ownText($node),
        };
        $value = self::normalize($text);
        return $value === '' ? null : new ExtractedValue($field, $value, new NodeLocation($node->getNodePath()), $text);
    }

    /** @return list<string> */
    private static function visibleParts(DOMNode $node): array
    {
        if ($node instanceof DOMText) {
            return trim($node->textContent) === '' ? [] : [$node->textContent];
        }
        if ($node instanceof DOMElement && in_array(strtolower($node->tagName), ['script', 'style', 'template', 'noscript'], true)) {
            return [];
        }
        $parts = [];
        foreach ($node->childNodes as $child) {
            array_push($parts, ...self::visibleParts($child));
        }
        return $parts;
    }

    private static function ownText(DOMNode $node): string
    {
        $parts = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $parts[] = $child->textContent;
            }
        }
        return implode(' ', $parts);
    }
}
