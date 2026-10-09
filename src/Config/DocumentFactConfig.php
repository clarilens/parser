<?php

declare(strict_types=1);

namespace Clarilens\Parser\Config;

use Clarilens\Parser\Condition\Condition;
use Clarilens\Parser\Result\FactState;
use InvalidArgumentException;
use Symfony\Component\CssSelector\CssSelectorConverter;

final readonly class DocumentFactConfig
{
    /** @var list<array<string, mixed>> */
    public array $fields;
    public string $hash;
    public string $registryFingerprint;
    /** @var array<string, mixed> */
    public array $definition;
    /** @var list<array<string, mixed>> */
    public array $tables;

    /** @param array<string, mixed> $data */
    public function __construct(array $data, OperationRegistry $registry = new OperationRegistry())
    {
        if (($data['version'] ?? null) !== 1 || !is_array($data['fields'] ?? null) || !array_is_list($data['fields'])
            || ($data['fields'] === [] && ($data['tables'] ?? []) === []) || count($data['fields']) > 100) {
            throw new InvalidArgumentException('Invalid document fact config.');
        }
        $types = [];
        $scopes = [];
        foreach ($data['fields'] as $field) {
            if (!is_array($field) || !is_string($field['id'] ?? null) || !preg_match('/^[A-Za-z][A-Za-z0-9_.-]*$/', $field['id']) || isset($types[$field['id']])) {
                throw new InvalidArgumentException('Invalid or duplicate fact field.');
            }
            $types[$field['id']] = $field['type'] ?? 'string';
            $scopes[$field['id']] = $field['scope'] ?? FieldScope::Document->value;
            if (!is_string($types[$field['id']]) || FieldType::tryFrom($types[$field['id']]) === null
                || !is_string($field['scope'] ?? FieldScope::Document->value) || FieldScope::tryFrom($field['scope'] ?? FieldScope::Document->value) === null
                || !is_string($field['cardinality'] ?? CaptureCardinality::One->value) || CaptureCardinality::tryFrom($field['cardinality'] ?? CaptureCardinality::One->value) === null
                || !is_string($field['sourceMode'] ?? CaptureSourceMode::Raw->value) || CaptureSourceMode::tryFrom($field['sourceMode'] ?? CaptureSourceMode::Raw->value) === null
                || !is_string($field['selector'] ?? null) || $field['selector'] === ''
                || !is_bool($field['required'] ?? false) || !is_bool($field['normalizeWhitespace'] ?? false)
                || !in_array($field['onFalse'] ?? null, [null, FactState::NotApplicable->value], true)
                || !is_array($field['transforms'] ?? []) || !array_is_list($field['transforms'] ?? [])) {
                throw new InvalidArgumentException('Invalid fact field declaration.');
            }
            if ($scopes[$field['id']] !== FieldScope::Document->value && (!is_string($field['container'] ?? null) || !is_string($field['groupKey'] ?? null))) {
                throw new InvalidArgumentException('Scoped field needs container and group key selectors.');
            }
            foreach (['container', 'groupKey'] as $selectorName) {
                if (isset($field[$selectorName])) {
                    try {
                        (new CssSelectorConverter())->toXPath($field[$selectorName]);
                    } catch (\Throwable $error) {
                        throw new InvalidArgumentException('Invalid scoped selector.', previous: $error);
                    }
                }
            }
            try {
                (new CssSelectorConverter())->toXPath($field['selector']);
            } catch (\Throwable $error) {
                throw new InvalidArgumentException('Invalid fact selector.', previous: $error);
            }
            if (isset($field['pattern']) && (!is_string($field['pattern']) || @preg_match($field['pattern'], '') === false)) {
                throw new InvalidArgumentException('Invalid capture pattern.');
            }
            foreach ($field['transforms'] ?? [] as $transform) {
                if (!is_array($transform) || !is_string($transform['op'] ?? null)) {
                    throw new InvalidArgumentException('Invalid transform.');
                }
                $operation = TransformOperation::tryFrom($transform['op']);
                if ($operation === TransformOperation::Registered) {
                    $registry->require($transform['id'] ?? '', $transform['version'] ?? '');
                } elseif ($operation === null) {
                    throw new InvalidArgumentException('Unknown transform.');
                }
                if ($operation === TransformOperation::Number && (!is_string($transform['decimal'] ?? DecimalSeparator::Dot->value) || DecimalSeparator::tryFrom($transform['decimal'] ?? DecimalSeparator::Dot->value) === null || !is_string($transform['unit'] ?? ''))) {
                    throw new InvalidArgumentException('Invalid numeric transform.');
                }
                if ($operation === TransformOperation::Enum && (!is_array($transform['map'] ?? null) || $transform['map'] === [])) {
                    throw new InvalidArgumentException('Invalid enum transform.');
                }
                if ($operation === TransformOperation::Url && (!is_string($transform['base'] ?? null) || filter_var($transform['base'], FILTER_VALIDATE_URL) === false)) {
                    throw new InvalidArgumentException('Invalid URL transform.');
                }
            }
        }
        foreach ($data['fields'] as $field) {
            foreach (['when', 'requiredWhen'] as $conditionName) {
                if (!isset($field[$conditionName])) {
                    continue;
                }
                $condition = new Condition($field[$conditionName], $types);
                foreach ($condition->fields() as $reference) {
                    if ($scopes[$reference] !== $scopes[$field['id']] && $scopes[$reference] !== FieldScope::Document->value) {
                        throw new InvalidArgumentException('Condition references a field outside its scope.');
                    }
                }
            }
        }
        if (!is_array($data['tables'] ?? []) || !array_is_list($data['tables'] ?? []) || count($data['tables'] ?? []) > 20) {
            throw new InvalidArgumentException('Invalid table projections.');
        }
        foreach ($data['tables'] ?? [] as $table) {
            if (!is_array($table) || !is_string($table['name'] ?? null) || !is_string($table['selector'] ?? null)
                || !is_string($table['field'] ?? null) || !is_int($table['headerRow'] ?? null)
                || !is_int($table['keyColumn'] ?? null) || $table['headerRow'] < 0 || $table['keyColumn'] < 0) {
                throw new InvalidArgumentException('Invalid table projection.');
            }
            foreach ($table['transforms'] ?? [] as $transform) {
                if (!is_array($transform) || !is_string($transform['op'] ?? null)) {
                    throw new InvalidArgumentException('Invalid table transform.');
                }
                $operation = TransformOperation::tryFrom($transform['op']);
                if ($operation === TransformOperation::Registered) {
                    $registry->require($transform['id'] ?? '', $transform['version'] ?? '');
                } elseif ($operation === null) {
                    throw new InvalidArgumentException('Unknown table transform.');
                }
            }
        }
        $this->fields = $data['fields'];
        $this->tables = $data['tables'] ?? [];
        $this->definition = $data;
        $this->hash = CanonicalConfig::hash($data);
        $this->registryFingerprint = $registry->fingerprint();
    }

    public static function fromJson(string $json, OperationRegistry $registry = new OperationRegistry()): self
    {
        $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new InvalidArgumentException('Invalid document fact config.');
        }
        return new self($data, $registry);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->definition;
    }
}
