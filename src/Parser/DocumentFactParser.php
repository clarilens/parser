<?php

declare(strict_types=1);

namespace Clarilens\Parser\Parser;

use Clarilens\Parser\Condition\Condition;
use Clarilens\Parser\Condition\ConditionResult;
use Clarilens\Parser\Config\CaptureCardinality;
use Clarilens\Parser\Config\CaptureSourceMode;
use Clarilens\Parser\Config\DocumentFactConfig;
use Clarilens\Parser\Config\FieldScope;
use Clarilens\Parser\Config\FieldType;
use Clarilens\Parser\Config\OperationRegistry;
use Clarilens\Parser\Config\TransformOperation;
use Clarilens\Parser\Extraction\DomContext;
use Clarilens\Parser\Extraction\FactTransform;
use Clarilens\Parser\Extraction\TableFactProjector;
use Clarilens\Parser\Extraction\TableGridRule;
use Clarilens\Parser\Extraction\TextCapture;
use Clarilens\Parser\Result\FactAssembler;
use Clarilens\Parser\Result\FactEvidence;
use Clarilens\Parser\Result\FactResolution;
use Clarilens\Parser\Result\FactState;
use Clarilens\Parser\Result\ObservedFact;
use Clarilens\Parser\Result\ObservedFactBatch;
use Clarilens\Parser\Result\TextLocation;
use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;
use Symfony\Component\CssSelector\CssSelectorConverter;

final readonly class DocumentFactParser
{
    public function __construct(private OperationRegistry $registry = new OperationRegistry())
    {
    }

    public function parse(DocumentFactConfig $config, string $html, string $sourceId, string $url, DateTimeImmutable $observedAt): ObservedFactBatch
    {
        if (strlen($html) > 5_000_000) {
            throw new InvalidArgumentException('Document is too large.');
        }
        $dom = new DOMDocument();
        $old = libxml_use_internal_errors(true);
        try {
            if (!$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET)) {
                throw new InvalidArgumentException('Invalid HTML document.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($old);
        }
        $xpath = new DOMXPath($dom);
        $facts = [];
        $resolutions = [];
        $values = [];
        $observed = [];
        $types = [];
        foreach ($config->fields as $field) {
            $types[$field['id']] = $field['type'] ?? 'string';
        }
        foreach ($config->fields as $field) {
            $id = $field['id'];
            $contexts = [[$dom, null]];
            if (FieldScope::from($field['scope'] ?? FieldScope::Document->value) !== FieldScope::Document) {
                $contexts = [];
                $containers = $xpath->query((new CssSelectorConverter())->toXPath($field['container']));
                foreach ($containers ?: [] as $container) {
                    if (!$container instanceof DOMElement) {
                        continue;
                    }
                    $keyNodes = $xpath->query((new CssSelectorConverter())->toXPath($field['groupKey']), $container);
                    $group = trim($keyNodes?->item(0)?->textContent ?? '');
                    if ($group === '') {
                        $resolutions[] = new FactResolution($id, FactState::Error, $id, $container->getNodePath(), 'missing scoped group key');
                        continue;
                    }
                    $contexts[] = [$container, $group];
                }
            }
            foreach ($contexts as [$contextNode, $group]) {
                $scopedValues = array_merge($values[''] ?? [], $values[$group ?? ''] ?? []);
                if (isset($field['when'])) {
                    $condition = new Condition($field['when'], $types);
                    $state = $condition->evaluate($scopedValues);
                    if ($state !== ConditionResult::True) {
                        $evidence = [];
                        foreach ($condition->fields() as $reference) {
                            array_push($evidence, ...($observed[$group ?? ''][$reference] ?? $observed[''][$reference] ?? []));
                        }
                        $resolutions[] = new FactResolution($id, $state === ConditionResult::False && ($field['onFalse'] ?? null) === FactState::NotApplicable->value ? FactState::NotApplicable : FactState::Error, $id, $group, $state === ConditionResult::Unknown ? 'unknown condition input' : 'applicability false', $evidence);
                        continue;
                    }
                }
                $required = $field['required'] ?? false;
                if (isset($field['requiredWhen'])) {
                    $requiredState = (new Condition($field['requiredWhen'], $types))->evaluate($scopedValues);
                    if ($requiredState === ConditionResult::Unknown) {
                        $resolutions[] = new FactResolution($id, FactState::Error, $id, $group, 'unknown required condition input');
                        continue;
                    }
                    $required = $requiredState === ConditionResult::True;
                }
                try {
                    $selector = (new CssSelectorConverter())->toXPath($field['selector']);
                    $nodes = $xpath->query($selector, $contextNode);
                    if ($nodes === false) {
                        throw new InvalidArgumentException('Invalid selector.');
                    }
                    $found = [];
                    foreach ($nodes as $node) {
                        if (!$node instanceof DOMElement) {
                            continue;
                        }
                        $raw = $node->textContent;
                        if (CaptureSourceMode::from($field['sourceMode'] ?? CaptureSourceMode::Raw->value) === CaptureSourceMode::Visible && $this->hasHiddenDescendant($node)) {
                            throw new InvalidArgumentException('Visible text cannot map to raw source.');
                        }
                        foreach (TextCapture::find($raw, $field['pattern'] ?? null, $field['normalizeWhitespace'] ?? false) as $capture) {
                            $value = FactTransform::apply($capture['value'], $field['transforms'] ?? [], $this->registry);
                            if (FieldType::from($field['type'] ?? FieldType::String->value) === FieldType::Number && !is_numeric($value)) {
                                throw new InvalidArgumentException('Invalid numeric fact.');
                            }
                            if (FieldType::from($field['type'] ?? FieldType::String->value) === FieldType::Url && filter_var($value, FILTER_VALIDATE_URL) === false) {
                                throw new InvalidArgumentException('Invalid URL fact.');
                            }
                            $operations = array_map(static fn (array $transform): string => TransformOperation::from($transform['op']) === TransformOperation::Registered ? $transform['id'] . '@' . $transform['version'] : $transform['op'] . '@1', $field['transforms'] ?? []);
                            $found[] = new ObservedFact($id, $value, new FactEvidence($url, $observedAt, $capture['quote'], new TextLocation($node->getNodePath(), $capture['offset']), $sourceId), $group, rawInput: $capture['value'], operations: $operations);
                        }
                    }
                    if (CaptureCardinality::from($field['cardinality'] ?? CaptureCardinality::One->value) === CaptureCardinality::One && count($found) > 1) {
                        throw new InvalidArgumentException('Ambiguous capture.');
                    }
                    if (CaptureCardinality::from($field['cardinality'] ?? CaptureCardinality::One->value) === CaptureCardinality::First) {
                        $found = array_slice($found, 0, 1);
                    }
                    if ($found === []) {
                        if ($required) {
                            $resolutions[] = new FactResolution($id, FactState::Absent, $id, $group, 'required capture absent');
                        }
                        continue;
                    }
                    foreach ($found as $fact) {
                        $facts[] = $fact;
                        $observed[$group ?? ''][$id][] = $fact->evidence;
                    }
                    $values[$group ?? ''][$id] = FieldType::from($types[$id]) === FieldType::Number ? (float) $found[0]->value : $found[0]->value;
                } catch (\Throwable $error) {
                    $resolutions[] = new FactResolution($id, FactState::Error, $id, $group, $error->getMessage());
                }
            }
        }
        foreach ($config->tables as $table) {
            try {
                $grids = (new TableGridRule($table['name'], $table['selector']))->extract(new DomContext(ParserType::HTML, $dom));
                foreach ($grids as $grid) {
                    [$cellFacts, $cellResolutions] = TableFactProjector::project($grid, $table, $url, $observedAt, $sourceId, $this->registry);
                    array_push($facts, ...$cellFacts);
                    array_push($resolutions, ...$cellResolutions);
                }
            } catch (\Throwable $error) {
                $resolutions[] = new FactResolution($table['field'], FactState::Error, $table['name'], reason: $error->getMessage());
            }
        }
        $required = [];
        foreach ($config->fields as $field) {
            if (($field['required'] ?? false) && FieldScope::from($field['scope'] ?? FieldScope::Document->value) === FieldScope::Document) {
                $required[] = $field['id'];
            }
        }
        [$facts, $resolutions, $groups] = FactAssembler::assemble($facts, $resolutions, $required);
        return new ObservedFactBatch($sourceId, hash('sha256', $html), 'declarative-facts/1', $observedAt, !in_array(false, $groups, true), $facts, $resolutions, $groups, $config->hash, $config->registryFingerprint, 1);
    }

    private function hasHiddenDescendant(DOMElement $node): bool
    {
        if ($node->hasAttribute('hidden') || preg_match('/display\s*:\s*none/i', $node->getAttribute('style'))
            || in_array(strtolower($node->tagName), ['script', 'style'], true)) {
            return true;
        }
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && $this->hasHiddenDescendant($child)) {
                return true;
            }
        }
        return false;
    }
}
