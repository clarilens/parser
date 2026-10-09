<?php

declare(strict_types=1);

namespace Clarilens\Parser\Config;

use Clarilens\Parser\Extraction\CssRule;
use Clarilens\Parser\Extraction\ExtractionRule;
use Clarilens\Parser\Extraction\JsonScriptRule;
use Clarilens\Parser\Extraction\NodeTextMode;
use Clarilens\Parser\Extraction\PdfRegexRule;
use Clarilens\Parser\Extraction\PdfTextRule;
use Clarilens\Parser\Extraction\TableRule;
use Clarilens\Parser\Extraction\XPathRule;
use InvalidArgumentException;

final class RuleRegistry
{
    /** @var array<string, callable(array): ExtractionRule> */
    private array $factories = [];

    /** @var array<string, callable(ExtractionRule): ?array> */
    private array $encoders = [];

    public function __construct()
    {
        $this->register('xpath', static fn (array $data): ExtractionRule => new XPathRule(
            self::string($data, 'field'),
            self::required($data),
            self::string($data, 'selector'),
            self::textMode($data),
        ));
        $this->register('css', static fn (array $data): ExtractionRule => new CssRule(
            self::string($data, 'field'),
            self::required($data),
            self::string($data, 'selector'),
            self::textMode($data),
        ));
        $this->register('jsonScript', static fn (array $data): ExtractionRule => new JsonScriptRule(
            self::string($data, 'field'),
            self::required($data),
            self::string($data, 'selector'),
            self::path($data),
        ));
        $this->register('table', static fn (array $data): ExtractionRule => new TableRule(
            self::string($data, 'field'),
            self::required($data),
            self::string($data, 'rows'),
            self::string($data, 'label'),
            self::integer($data, 'labelColumn'),
            self::columns($data),
        ));
        $this->register('pdfText', static fn (array $data): ExtractionRule => new PdfTextRule(
            self::string($data, 'field'),
            self::required($data),
        ));
        $this->register('pdfRegex', static fn (array $data): ExtractionRule => new PdfRegexRule(
            self::string($data, 'field'),
            self::required($data),
            self::string($data, 'pattern'),
        ));
    }

    /**
     * @param callable(array): ExtractionRule $factory
     * @param ?callable(ExtractionRule): ?array $encoder
     **/
    public function register(string $type, callable $factory, ?callable $encoder = null): void
    {
        if ($type === '' || isset($this->factories[$type])) {
            throw new InvalidArgumentException('Invalid or duplicate rule type.');
        }
        $this->factories[$type] = $factory;
        if ($encoder !== null) {
            $this->encoders[$type] = $encoder;
        }
    }

    public function fromArray(mixed $data): ExtractionRule
    {
        if (!is_array($data) || !is_string($data['type'] ?? null)
            || !isset($this->factories[$data['type']])) {
            throw new InvalidArgumentException('Unknown extraction rule type.');
        }
        return ($this->factories[$data['type']])($data);
    }

    /** @return array<string, mixed> */
    public function toArray(ExtractionRule $rule): array
    {
        $data = match (true) {
            $rule instanceof XPathRule => ['type' => 'xpath', 'selector' => $rule->selector, 'textMode' => $rule->textMode->value],
            $rule instanceof CssRule => ['type' => 'css', 'selector' => $rule->selector, 'textMode' => $rule->textMode->value],
            $rule instanceof JsonScriptRule => ['type' => 'jsonScript', 'selector' => $rule->selector, 'path' => $rule->path],
            $rule instanceof TableRule => ['type' => 'table', 'rows' => $rule->rows, 'label' => $rule->label,
                'labelColumn' => $rule->labelColumn, 'valueColumns' => $rule->valueColumns],
            $rule instanceof PdfTextRule => ['type' => 'pdfText'],
            $rule instanceof PdfRegexRule => ['type' => 'pdfRegex', 'pattern' => $rule->pattern],
            default => null,
        };
        if ($data === null) {
            foreach ($this->encoders as $type => $encoder) {
                $encoded = $encoder($rule);
                if ($encoded !== null) {
                    $data = ['type' => $type, ...$encoded];
                    break;
                }
            }
        }
        if ($data === null) {
            throw new InvalidArgumentException('Rule has no JSON encoder.');
        }
        return ['field' => $rule->field(), 'required' => $rule->required(), ...$data];
    }

    private static function string(array $data, string $key): string
    {
        if (!is_string($data[$key] ?? null)) {
            throw new InvalidArgumentException("Invalid rule $key.");
        }
        return $data[$key];
    }

    private static function integer(array $data, string $key): int
    {
        if (!is_int($data[$key] ?? null)) {
            throw new InvalidArgumentException("Invalid rule $key.");
        }
        return $data[$key];
    }

    private static function required(array $data): bool
    {
        if (!is_bool($data['required'] ?? null)) {
            throw new InvalidArgumentException('Invalid rule required flag.');
        }
        return $data['required'];
    }

    /** @return non-empty-list<string|int> */
    private static function path(array $data): array
    {
        if (!is_array($data['path'] ?? null) || $data['path'] === [] || !array_is_list($data['path'])) {
            throw new InvalidArgumentException('Invalid JSON path.');
        }
        return $data['path'];
    }

    private static function textMode(array $data): NodeTextMode
    {
        $mode = $data['textMode'] ?? NodeTextMode::All->value;
        if (!is_string($mode) || ($selected = NodeTextMode::tryFrom($mode)) === null) {
            throw new InvalidArgumentException('Invalid text mode.');
        }
        return $selected;
    }

    /** @return list<int> */
    private static function columns(array $data): array
    {
        if (!is_array($data['valueColumns'] ?? null)) {
            throw new InvalidArgumentException('Invalid table value columns.');
        }
        return $data['valueColumns'];
    }
}
