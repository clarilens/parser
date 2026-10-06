<?php

declare(strict_types=1);

namespace Clarilens\Parser\Extraction;

use Clarilens\Parser\Parser\ParserType;
use Clarilens\Parser\Result\ExtractedValue;
use Clarilens\Parser\Result\JsonLocation;
use Clarilens\Parser\Validation\Checks;
use DOMElement;
use InvalidArgumentException;
use JsonException;
use Override;

final readonly class JsonScriptRule implements ExtractionRule
{
    /** @param non-empty-list<string|int> $path */
    public function __construct(
        public string $field,
        public bool $required,
        public string $selector,
        public array $path,
    ) {
        Checks::field($field);
        Checks::selector($selector);
        if ($path === [] || !array_is_list($path) || count($path) > 32) {
            throw new InvalidArgumentException('Invalid JSON path.');
        }
        foreach ($path as $segment) {
            if ((!is_string($segment) && !is_int($segment)) || $segment === '' || (is_int($segment) && $segment < 0)) {
                throw new InvalidArgumentException('Invalid JSON path segment.');
            }
        }
    }

    #[Override]
    public function field(): string
    {
        return $this->field;
    }

    #[Override]
    public function required(): bool
    {
        return $this->required;
    }

    #[Override]
    public function supports(ParserType $type): bool
    {
        return $type === ParserType::HTML;
    }

    /** @return list<ExtractedValue> */
    #[Override]
    public function extract(RuleContext $context): array
    {
        if (!$context instanceof DomContext) {
            throw new InvalidArgumentException('JSON script requires HTML context.');
        }
        $nodes = $context->xpath->query($this->selector);
        if ($nodes === false) {
            throw new InvalidArgumentException('Invalid JSON script selector.');
        }
        $values = [];
        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement || strtolower($node->tagName) !== 'script') {
                throw new InvalidArgumentException('JSON selector must resolve to script elements.');
            }
            try {
                $data = json_decode($node->textContent, true, 64, JSON_THROW_ON_ERROR);
            } catch (JsonException $error) {
                throw new InvalidArgumentException('Invalid embedded JSON.', previous: $error);
            }
            foreach ($this->select($data, $this->path) as [$value, $pointer]) {
                if (!is_scalar($value)) {
                    throw new InvalidArgumentException('JSON path must resolve to scalar values.');
                }
                $text = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
                if ($text !== '') {
                    $values[] = new ExtractedValue($this->field, $text, new JsonLocation($node->getNodePath(), $pointer), $text);
                    if (count($values) > 10_000) {
                        throw new InvalidArgumentException('Too many JSON values.');
                    }
                }
            }
        }
        return $values;
    }

    /**
     * @param list<string|int> $path
     * @return list<array{mixed, string}>
     */
    private function select(mixed $value, array $path, string $pointer = ''): array
    {
        if ($path === []) {
            return [[$value, $pointer]];
        }
        if (!is_array($value)) {
            return [];
        }
        $segment = array_shift($path);
        $keys = $segment === '*' ? array_keys($value) : [$segment];
        $found = [];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $value)) {
                continue;
            }
            $escaped = str_replace(['~', '/'], ['~0', '~1'], (string) $key);
            array_push($found, ...$this->select($value[$key], $path, $pointer . '/' . $escaped));
            if (count($found) > 10_000) {
                throw new InvalidArgumentException('Too many JSON matches.');
            }
        }
        return $found;
    }
}
