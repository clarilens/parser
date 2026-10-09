<?php

declare(strict_types=1);

namespace Clarilens\Parser\Config;

use Clarilens\Parser\Source\RenderType;
use Clarilens\Parser\Source\Url;
use InvalidArgumentException;
use Symfony\Component\CssSelector\CssSelectorConverter;

final readonly class CrawlConfig
{
    public const int VERSION = 1;

    /** @var array<string, array<string, mixed>> */
    public array $stages;
    /** @var list<string> */
    public array $allowedHosts;
    /** @var array<string, int> */
    public array $limits;
    public string $hash;
    public string $registryFingerprint;
    /** @var array<string, mixed> */
    public array $definition;

    /** @param array<string, mixed> $data */
    public function __construct(array $data, OperationRegistry $registry = new OperationRegistry())
    {
        if (($data['crawlVersion'] ?? null) !== self::VERSION || !is_string($data['start'] ?? null)
            || !is_string($data['url'] ?? null) || Url::parts($data['url']) === null
            || !is_array($data['allowedHosts'] ?? null) || !array_is_list($data['allowedHosts']) || $data['allowedHosts'] === []
            || !is_array($data['stages'] ?? null) || $data['stages'] === [] || count($data['stages']) > 32) {
            throw new InvalidArgumentException('Invalid crawl config.');
        }
        $hosts = $data['allowedHosts'];
        foreach ($hosts as $host) {
            if (!is_string($host) || !preg_match('/^[a-z0-9.-]+$/i', $host)) {
                throw new InvalidArgumentException('Invalid allowed host.');
            }
        }
        if (!in_array(Url::parts($data['url'])['host'], $hosts, true) || !isset($data['stages'][$data['start']])) {
            throw new InvalidArgumentException('Invalid crawl start.');
        }
        $limits = $data['limits'] ?? [];
        foreach (['maxPages' => 500, 'maxClicks' => 50, 'maxItems' => 20000, 'maxDurationSeconds' => 3600, 'maxRetries' => 2] as $name => $default) {
            $limits[$name] ??= $default;
            if (!is_int($limits[$name]) || $limits[$name] < 1 || $limits[$name] > 100000) {
                throw new InvalidArgumentException('Invalid crawl limit: ' . $name);
            }
        }
        foreach ($data['stages'] as $id => $stage) {
            if (!is_string($id) || !preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $id) || !is_array($stage)
                || !is_array($stage['records'] ?? null) || !is_string($stage['records']['type'] ?? null)
                || !is_string($stage['records']['namespace'] ?? null) || !is_string($stage['records']['key'] ?? null)) {
                throw new InvalidArgumentException('Invalid crawl stage: ' . $id);
            }
            $records = $stage['records'];
            if (!is_string($stage['render'] ?? RenderType::Http->value) || RenderType::tryFrom($stage['render'] ?? RenderType::Http->value) === null) {
                throw new InvalidArgumentException('Invalid crawl render mode.');
            }
            $this->validateRecord($records, 0);
            if ($records['type'] === '' || $records['namespace'] === '' || $records['key'] === ''
                || !is_array($records['fields'] ?? []) || !array_key_exists($records['key'], $records['fields'])) {
                throw new InvalidArgumentException('Invalid crawl record fields.');
            }
            foreach ($records['fields'] as $name => $field) {
                if (!is_string($name) || !preg_match('/^[A-Za-z][A-Za-z0-9_.-]*$/', $name)
                    || !(is_string($field) || (is_array($field) && is_string($field['selector'] ?? null)))) {
                    throw new InvalidArgumentException('Invalid crawl field path.');
                }
            }
            if (!isset($records['selector']) && !isset($records['jsonAttribute'])) {
                throw new InvalidArgumentException('Missing record selector.');
            }
            foreach (['selector', 'jsonAttribute', 'nextLink', 'repeat.click', 'repeat.items'] as $path) {
                $selector = match ($path) {
                    'selector', 'jsonAttribute' => $records[$path] ?? null,
                    'nextLink' => $stage['nextLink'] ?? null,
                    'repeat.click' => $stage['repeat']['click'] ?? null,
                    'repeat.items' => $stage['repeat']['items'] ?? null,
                };
                if ($selector !== null) {
                    if (!is_string($selector) || $selector === '') {
                        throw new InvalidArgumentException('Invalid crawl selector.');
                    }
                    try {
                        (new CssSelectorConverter())->toXPath($selector);
                    } catch (\Throwable $error) {
                        throw new InvalidArgumentException('Invalid crawl selector.', previous: $error);
                    }
                }
            }
            if (isset($stage['follow']) && (!is_array($stage['follow']) || !isset($data['stages'][$stage['follow']['stage'] ?? ''])
                || (!is_string($stage['follow']['field'] ?? null) && !is_string($stage['follow']['template'] ?? null)))) {
                throw new InvalidArgumentException('Invalid crawl transition.');
            }
            if (isset($stage['follow']['field']) && !isset($records['fields'][$stage['follow']['field']])) {
                throw new InvalidArgumentException('Follow references unknown record field.');
            }
            if (isset($stage['follow']['template'])) {
                $template = $stage['follow']['template'];
                if (!str_starts_with($template, 'https://' . Url::parts($data['url'])['host'] . '/')) {
                    throw new InvalidArgumentException('Unsafe follow URL template.');
                }
                preg_match_all('/\{([^{}]+)\}/', $template, $references);
                foreach ($references[1] as $reference) {
                    if (!isset($records['fields'][$reference])) {
                        throw new InvalidArgumentException('Follow template references unknown field.');
                    }
                }
            }
            if (isset($stage['expectedCountPattern']) && (!is_string($stage['expectedCountPattern']) || @preg_match($stage['expectedCountPattern'], '') === false)) {
                throw new InvalidArgumentException('Invalid source count pattern.');
            }
            if (isset($stage['repeat']) && (!is_array($stage['repeat']) || !isset($stage['repeat']['click'], $stage['repeat']['items']) || RenderType::tryFrom($stage['render'] ?? RenderType::Http->value) !== RenderType::Browser)) {
                throw new InvalidArgumentException('Invalid browser repeat.');
            }
            if (isset($stage['pageParameter']) && (!is_array($stage['pageParameter']) || !is_string($stage['pageParameter']['name'] ?? null)
                || !is_int($stage['pageParameter']['last'] ?? null) || $stage['pageParameter']['last'] < 1 || $stage['pageParameter']['last'] > $limits['maxPages'])) {
                throw new InvalidArgumentException('Invalid page parameter.');
            }
            foreach ($stage['operations'] ?? [] as $operation) {
                $registry->require($operation['id'] ?? '', $operation['version'] ?? '');
            }
        }
        $this->stages = $data['stages'];
        $this->definition = $data;
        $this->allowedHosts = $hosts;
        $this->limits = $limits;
        $this->hash = CanonicalConfig::hash($data);
        $this->registryFingerprint = $registry->fingerprint();
        $this->start = $data['start'];
        $this->url = $data['url'];
        $this->priorCount = $data['priorCount'] ?? null;
        $this->coverageType = $data['coverageType'] ?? null;
        if ($this->coverageType !== null && (!is_string($this->coverageType) || !in_array($this->coverageType, array_map(static fn (array $stage): string => $stage['records']['type'], $data['stages']), true))) {
            throw new InvalidArgumentException('Invalid coverage record type.');
        }
        $threshold = $data['priorThreshold'] ?? null;
        if ($this->priorCount !== null && (!is_int($this->priorCount) || !is_numeric($threshold) || $threshold <= 0 || $threshold > 1)) {
            throw new InvalidArgumentException('Prior-run comparison needs a threshold.');
        }
        $this->priorThreshold = $threshold === null ? null : (float) $threshold;
    }

    public string $start;
    public string $url;
    public ?int $priorCount;
    public ?string $coverageType;
    public ?float $priorThreshold;

    public static function fromJson(string $json, OperationRegistry $registry = new OperationRegistry()): self
    {
        $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new InvalidArgumentException('Invalid crawl config.');
        }
        return new self($data, $registry);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->definition;
    }

    /** @param array<string, mixed> $record */
    private function validateRecord(array $record, int $depth): void
    {
        if ($depth > 8 || !is_string($record['type'] ?? null) || !is_string($record['namespace'] ?? null)
            || !is_string($record['key'] ?? null) || !is_array($record['fields'] ?? null)
            || !array_key_exists($record['key'], $record['fields'])
            || !is_array($record['path'] ?? []) || !array_is_list($record['path'] ?? []) || count($record['path'] ?? []) > 32) {
            throw new InvalidArgumentException('Invalid nested record declaration.');
        }
        foreach ($record['path'] ?? [] as $segment) {
            if ((!is_string($segment) && !is_int($segment)) || $segment === '' || (is_int($segment) && $segment < 0)) {
                throw new InvalidArgumentException('Invalid nested JSON path.');
            }
        }
        if (!is_array($record['children'] ?? []) || !array_is_list($record['children'] ?? [])) {
            throw new InvalidArgumentException('Invalid nested children.');
        }
        foreach ($record['children'] ?? [] as $child) {
            if (!is_array($child)) {
                throw new InvalidArgumentException('Invalid nested child.');
            }
            $this->validateRecord($child, $depth + 1);
        }
    }
}
