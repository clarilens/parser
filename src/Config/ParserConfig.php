<?php

declare(strict_types=1);

namespace Clarilens\Parser\Config;

use Clarilens\Parser\Config\RuleRegistry;
use Clarilens\Parser\Extraction\ExtractionRule;
use Clarilens\Parser\Extraction\TableGridRule;
use Clarilens\Parser\Extraction\XPathRule;
use Clarilens\Parser\Parser\ParserType;
use Clarilens\Parser\Source\RenderType;
use Clarilens\Parser\Source\SourceConfig;
use Clarilens\Parser\Source\SourceType;
use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use JsonException;

final readonly class ParserConfig
{
    /**
     * @param list<ExtractionRule> $rules
     * @param array<string, string> $namespaces
     * @param list<FieldCheck> $checks
     * @param list<TableGridRule> $tables
     */
    public function __construct(
        public ?SourceConfig $source,
        public ParserType $parserType,
        public array $rules,
        public array $namespaces = [],
        public array $checks = [],
        public bool $strict = false,
        public array $tables = [],
    ) {
        if (($rules === [] && $tables === []) || count($rules) > 100 || !array_is_list($rules)) {
            throw new InvalidArgumentException('Invalid extraction rules.');
        }
        if ($source?->type === SourceType::Web && $source->render === RenderType::Browser
            && $parserType !== ParserType::HTML) {
            throw new InvalidArgumentException('Browser rendering supports HTML only.');
        }
        foreach ($rules as $rule) {
            if (!$rule instanceof ExtractionRule || !$rule->supports($parserType)) {
                throw new InvalidArgumentException('Rule does not support parser type.');
            }
        }
        $fields = array_map(static fn (ExtractionRule $rule): string => $rule->field(), $rules);
        foreach ($checks as $check) {
            if (!$check instanceof FieldCheck || !in_array($check->field, $fields, true)) {
                throw new InvalidArgumentException('Field check references an unknown rule.');
            }
        }
        if (!array_is_list($tables) || count($tables) > 20 || ($tables !== [] && $parserType !== ParserType::HTML)) {
            throw new InvalidArgumentException('Invalid table grid rules.');
        }
        foreach ($tables as $table) {
            if (!$table instanceof TableGridRule) {
                throw new InvalidArgumentException('Invalid table grid rule.');
            }
        }
        if ($parserType !== ParserType::XML && $namespaces !== []) {
            throw new InvalidArgumentException('Namespaces require XML parser.');
        }
        foreach ($namespaces as $prefix => $uri) {
            if (!is_string($prefix) || !preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*$/', $prefix)
                || !is_string($uri) || $uri === '') {
                throw new InvalidArgumentException('Invalid XML namespace mapping.');
            }
        }
        if ($parserType === ParserType::XML) {
            $xpath = new DOMXPath(new DOMDocument());
            foreach ($namespaces as $prefix => $uri) {
                $xpath->registerNamespace($prefix, $uri);
            }
            foreach ($rules as $rule) {
                if ($rule instanceof XPathRule && @$xpath->query($rule->selector) === false) {
                    throw new InvalidArgumentException('Invalid or unbound XML XPath prefix.');
                }
            }
        }
    }

    /** @throws JsonException */
    public static function fromJson(string|array $json, ?RuleRegistry $registry = null): self
    {
        $data = is_string($json) ? json_decode($json, true, 512, JSON_THROW_ON_ERROR) : $json;
        if (!is_array($data) || !is_array($data['parser'] ?? null)
            || !is_string($data['parser']['type'] ?? null)
            || ($type = ParserType::tryFrom($data['parser']['type'])) === null
            || !is_array($data['rules'] ?? []) || !array_is_list($data['rules'] ?? [])
            || !is_array($data['parser']['namespaces'] ?? [])
            || !is_array($data['checks'] ?? []) || !array_is_list($data['checks'] ?? [])
            || !is_bool($data['strict'] ?? false)
            || ($data['version'] ?? 1) !== 1
            || !is_array($data['tables'] ?? []) || !array_is_list($data['tables'] ?? [])) {
            throw new InvalidArgumentException('Invalid parser config.');
        }
        $registry ??= new RuleRegistry();
        return new self(
            isset($data['source']) ? SourceConfig::fromArray($data['source']) : null,
            $type,
            array_map($registry->fromArray(...), $data['rules'] ?? []),
            $data['parser']['namespaces'] ?? [],
            array_map(FieldCheck::fromArray(...), $data['checks'] ?? []),
            $data['strict'] ?? false,
            array_map(TableGridRule::fromArray(...), $data['tables'] ?? []),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(?RuleRegistry $registry = null): array
    {
        $registry ??= new RuleRegistry();
        return [
            'version' => 1,
            ...($this->source === null ? [] : ['source' => $this->source->toArray()]),
            'parser' => [
                'type' => $this->parserType->value,
                ...($this->namespaces === [] ? [] : ['namespaces' => $this->namespaces]),
            ],
            'rules' => array_map($registry->toArray(...), $this->rules),
            ...($this->tables === [] ? [] : ['tables' => array_map(static fn (TableGridRule $table): array => $table->toArray(), $this->tables)]),
            ...($this->checks === [] ? [] : ['checks' => array_map(static fn (FieldCheck $check): array => $check->toArray(), $this->checks)]),
            ...($this->strict ? ['strict' => true] : []),
        ];
    }

    /** @throws JsonException */
    public function toJson(?RuleRegistry $registry = null): string
    {
        return json_encode($this->toArray($registry), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
