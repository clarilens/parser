<?php

declare(strict_types=1);

namespace Clarilens\Parser\Crawl;

use Clarilens\Parser\Condition\Condition;
use Clarilens\Parser\Condition\ConditionOperator;
use Clarilens\Parser\Condition\ConditionResult;
use Clarilens\Parser\Condition\NumericComparator;
use Clarilens\Parser\Config\CrawlConfig;
use Clarilens\Parser\Config\FieldType;
use Clarilens\Parser\Result\CoverageStatus;
use Clarilens\Parser\Result\CrawlFailure;
use Clarilens\Parser\Result\CrawlFailureCode;
use Clarilens\Parser\Result\CrawlOutcome;
use Clarilens\Parser\Result\CrawlRecord;
use Clarilens\Parser\Source\BrowserRequest;
use Clarilens\Parser\Source\BrowserWebClient;
use Clarilens\Parser\Source\HttpFetchRequest;
use Clarilens\Parser\Source\HttpWebClient;
use Clarilens\Parser\Source\RenderType;
use Clarilens\Parser\Source\Url;
use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;
use Symfony\Component\CssSelector\CssSelectorConverter;

/** One fetch per URL; each parent context is evaluated separately. */
final class CrawlRunner
{
    /** @var array<string, list<string>> */
    private array $cache = [];
    /** @var list<CrawlRecord> */
    private array $records = [];
    /** @var list<CrawlFailure> */
    private array $failures = [];
    /** @var list<string> */
    private array $incomplete = [];
    /** @var array<string, string|null> */
    private array $parents = [];
    private int $processed = 0;
    private int $clicks = 0;
    private ?int $expected = null;
    private ?string $expectedStage = null;

    /** @param \Closure(string, string, array): (string|list<string>) $fetch */
    public function __construct(private readonly \Closure $fetch)
    {
    }

    public static function live(CrawlConfig $config, ?HttpWebClient $http = null, ?BrowserWebClient $browser = null): self
    {
        $http ??= new HttpWebClient();
        $browser ??= new BrowserWebClient(dirname(__DIR__, 2) . '/dist/render-page.js');
        return new self(static function (string $url, string $mode, array $stage) use ($config, $http, $browser): string|array {
            if (RenderType::from($mode) !== RenderType::Browser) {
                return $http->fetch(new HttpFetchRequest($url, $config->allowedHosts, 5_000_000, 30_000));
            }
            $request = new BrowserRequest($url, allowedHosts: $config->allowedHosts);
            if (isset($stage['repeat'])) {
                return $browser->renderSequence($request, $stage['repeat']['click'], $stage['repeat']['items'], $config->limits['maxClicks']);
            }
            return $browser->render($request);
        });
    }

    public function run(CrawlConfig $config): CrawlOutcome
    {
        $this->cache = $this->records = $this->failures = $this->incomplete = $this->parents = [];
        $this->processed = $this->clicks = 0;
        $this->expected = null;
        $this->expectedStage = null;
        $queue = [[$config->start, $config->url, null]];
        $visited = [];
        $started = microtime(true);
        while ($queue !== []) {
            if ($this->processed >= $config->limits['maxPages'] || microtime(true) - $started > $config->limits['maxDurationSeconds']) {
                [$stage, , $parent] = $queue[0];
                $this->fail($stage, $parent ?? $config->url, $this->processed >= $config->limits['maxPages'] ? CrawlFailureCode::PageLimitExhausted : CrawlFailureCode::DurationLimitExhausted);
                break;
            }
            [$stageId, $url, $parent] = array_shift($queue);
            $visitKey = $stageId . "\0" . $url . "\0" . ($parent ?? '');
            if (isset($visited[$visitKey])) {
                continue;
            }
            $visited[$visitKey] = true;
            $stage = $config->stages[$stageId];
            try {
                $this->safe($url, $config);
                $frames = $this->frames($url, $stageId, $config);
                ++$this->processed;
                $frame = $this->repeat($frames, $stage, $stageId, $parent ?? $url, $config);
                $document = $this->document($frame);
                $xpath = new DOMXPath($document);
                $this->count($stage, $frame, $stageId, $parent ?? $url);
                foreach ($this->extract($xpath, $stage['records'], $url, $parent) as $record) {
                    if (count($this->records) >= $config->limits['maxItems']) {
                        $this->fail($stageId, $parent ?? $url, CrawlFailureCode::ItemLimitExhausted);
                        break 2;
                    }
                    if (array_key_exists($record->key, $this->parents) && $this->parents[$record->key] !== $record->parentKey) {
                        $this->fail($stageId, $record->key, CrawlFailureCode::IdentityConflict);
                    }
                    $this->parents[$record->key] ??= $record->parentKey;
                    $this->records[] = $record;
                    if (isset($stage['follow'])) {
                        $next = isset($stage['follow']['template']) ? $this->template($stage['follow']['template'], $record->fields) : ($record->fields[$stage['follow']['field']] ?? null);
                        if (is_string($next)) {
                            $queue[] = [$stage['follow']['stage'], $this->resolve($url, $next, $config), $record->key];
                        }
                    }
                }
                if (isset($stage['nextLink'])) {
                    foreach ($this->nodes($xpath, $stage['nextLink']) as $link) {
                        if ($link instanceof DOMElement && $link->hasAttribute('href')) {
                            $queue[] = [$stageId, $this->resolve($url, $link->getAttribute('href'), $config), $parent];
                        }
                    }
                }
                if (isset($stage['pageParameter'])) {
                    parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $query);
                    $page = (int) ($query[$stage['pageParameter']['name'] ?? 'page'] ?? 1);
                    $last = $stage['pageParameter']['last'] ?? 1;
                    $name = $stage['pageParameter']['name'] ?? 'page';
                    if ($page < $last) {
                        $query[$name] = $page + 1;
                        $parts = parse_url($url);
                        $nextUrl = $parts['scheme'] . '://' . $parts['host'] . ($parts['path'] ?? '/') . '?' . http_build_query($query);
                        $this->safe($nextUrl, $config);
                        $queue[] = [$stageId, $nextUrl, $parent];
                    }
                }
            } catch (\Throwable $error) {
                $this->fail($stageId, $parent ?? $url, $error instanceof RetryExhausted ? CrawlFailureCode::RetryExhausted : CrawlFailureCode::SourceError, $error->getMessage(), $error instanceof RetryExhausted ? $error->attempts : 1);
            }
        }
        $distinct = count(array_unique(array_map(static fn (CrawlRecord $record): string => $record->key, array_filter($this->records, static fn (CrawlRecord $record): bool => $config->coverageType === null || $record->type === $config->coverageType))));
        $counted = $this->expectedStage === null ? $distinct : count(array_unique(array_map(static fn (CrawlRecord $record): string => $record->key, array_filter($this->records, fn (CrawlRecord $record): bool => $record->type === $config->stages[$this->expectedStage]['records']['type']))));
        $coverage = CoverageStatus::Unconfirmed;
        if ($this->expected !== null) {
            $coverage = (new Condition([ConditionOperator::Equals->value => ['field' => 'observed', 'other' => 'expected']], ['observed' => FieldType::Number->value, 'expected' => FieldType::Number->value]))->evaluate(['observed' => $counted, 'expected' => $this->expected]) === ConditionResult::True ? CoverageStatus::Confirmed : CoverageStatus::Failed;
            if ($coverage === CoverageStatus::Failed) {
                $this->fail($config->start, $config->url, CrawlFailureCode::CountMismatch, 'expected ' . $this->expected . ', observed ' . $counted);
            }
        }
        if ($config->priorCount !== null) {
            if ((new Condition([ConditionOperator::Compare->value => ['field' => 'observed', 'operator' => NumericComparator::LessThan->value, 'value' => $config->priorCount * (float) $config->priorThreshold]], ['observed' => FieldType::Number->value]))->evaluate(['observed' => $distinct]) === ConditionResult::True) {
                $coverage = CoverageStatus::Failed;
                $this->fail($config->start, $config->url, CrawlFailureCode::PriorCountThreshold);
            } elseif ($coverage === CoverageStatus::Unconfirmed) {
                $coverage = CoverageStatus::Confirmed;
            }
        }
        return new CrawlOutcome($this->records, $this->failures, array_values(array_unique($this->incomplete)), $queue === [] && $this->failures === [], $coverage, $this->processed + count($queue), $this->processed, $this->expected, $distinct, CrawlConfig::VERSION, $config->hash, $config->registryFingerprint);
    }

    private function safe(string $url, CrawlConfig $config): void
    {
        $parts = Url::parts($url);
        if ($parts === null || !in_array($parts['scheme'], ['http', 'https'], true) || !in_array($parts['host'], $config->allowedHosts, true)
            || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null
            || (filter_var($parts['host'], FILTER_VALIDATE_IP) !== false && filter_var($parts['host'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false)) {
            throw new InvalidArgumentException('Unsafe crawl URL.');
        }
    }

    /** @return list<string> */
    private function frames(string $url, string $stage, CrawlConfig $config): array
    {
        $key = $url . '|' . ($config->stages[$stage]['render'] ?? RenderType::Http->value) . '|' . hash('sha256', json_encode($config->stages[$stage]['repeat'] ?? [], JSON_THROW_ON_ERROR));
        if (!isset($this->cache[$key])) {
            $last = null;
            for ($attempt = 1; $attempt <= $config->limits['maxRetries']; ++$attempt) {
                try {
                    $result = ($this->fetch)($url, $config->stages[$stage]['render'] ?? RenderType::Http->value, $config->stages[$stage]);
                    $frames = is_string($result) ? [$result] : $result;
                    if ($frames === [] || !array_is_list($frames)) {
                        throw new InvalidArgumentException('Empty source sequence.');
                    }
                    $this->cache[$key] = $frames;
                    break;
                } catch (\Throwable $error) {
                    $last = $error;
                }
            }
            if (!isset($this->cache[$key])) {
                throw new RetryExhausted($config->limits['maxRetries'], $last ?? new InvalidArgumentException('Unknown source failure.'));
            }
        }
        return $this->cache[$key];
    }

    /** @param list<string> $frames
     * @param array<string, mixed> $stage
     */
    private function repeat(array $frames, array $stage, string $stageId, string $branch, CrawlConfig $config): string
    {
        if (!isset($stage['repeat'])) {
            return $frames[0];
        }
        $repeat = $stage['repeat'];
        $previous = -1;
        foreach ($frames as $index => $frame) {
            $xpath = new DOMXPath($this->document($frame));
            $items = count($this->nodes($xpath, $repeat['items']));
            $available = count($this->nodes($xpath, $repeat['click'])) > 0;
            if ($items <= $previous) {
                $this->fail($stageId, $branch, CrawlFailureCode::NoProgress);
                return $frame;
            }
            $previous = $items;
            if (!$available) {
                return $frame;
            }
            if ($this->clicks >= $config->limits['maxClicks']) {
                $this->fail($stageId, $branch, CrawlFailureCode::ClickLimitExhausted);
                return $frame;
            }
            if ($index + 1 >= count($frames)) {
                $this->fail($stageId, $branch, CrawlFailureCode::NoProgress);
                return $frame;
            }
            ++$this->clicks;
        }
        return $frames[array_key_last($frames)];
    }

    private function document(string $html): DOMDocument
    {
        if (strlen($html) > 5_000_000) {
            throw new InvalidArgumentException('Crawl document is too large.');
        }
        $dom = new DOMDocument();
        $old = libxml_use_internal_errors(true);
        try {
            if (!$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET)) {
                throw new InvalidArgumentException('Invalid crawl document.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($old);
        }
        return $dom;
    }

    /** @return list<\DOMNode> */
    private function nodes(DOMXPath $xpath, string $selector, ?\DOMNode $context = null): array
    {
        $query = (new CssSelectorConverter())->toXPath($selector);
        $nodes = $xpath->query($query, $context);
        return $nodes === false ? [] : iterator_to_array($nodes);
    }

    /** @param array<string, mixed> $records
     * @return list<CrawlRecord>
     */
    private function extract(DOMXPath $xpath, array $records, string $url, ?string $parent): array
    {
        $out = [];
        if (isset($records['jsonAttribute'])) {
            foreach ($this->nodes($xpath, $records['jsonAttribute']) as $node) {
                if (!$node instanceof DOMElement || !$node->hasAttribute($records['attribute'] ?? 'data-json')) {
                    continue;
                }
                $data = json_decode($node->getAttribute($records['attribute'] ?? 'data-json'), true, 64, JSON_THROW_ON_ERROR);
                foreach ($this->path($data, $records['path'] ?? []) as $entry) {
                    $this->jsonRecords($out, $entry, $records, $url, $parent, $node->getNodePath());
                }
            }
            return $out;
        }
        foreach ($this->nodes($xpath, $records['selector']) as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            $fields = [];
            foreach ($records['fields'] ?? [] as $name => $field) {
                $selected = $this->nodes($xpath, $field['selector'], $node)[0] ?? null;
                $fields[$name] = $selected instanceof DOMElement ? ($field['attribute'] ?? null ? $selected->getAttribute($field['attribute']) : trim($selected->textContent)) : null;
            }
            $keyValue = $fields[$records['key']] ?? $node->getAttribute($records['key']);
            if (!is_string($keyValue) || $keyValue === '') {
                throw new InvalidArgumentException('Missing record key.');
            }
            $out[] = new CrawlRecord($records['namespace'] . ':' . $records['type'] . ':' . $keyValue, $records['type'], $parent, $fields, $url, $node->getNodePath());
        }
        return $out;
    }

    /** @param list<CrawlRecord> $out
     * @param array<string, mixed> $records
     */
    private function jsonRecords(array &$out, mixed $entry, array $records, string $url, ?string $parent, string $location): void
    {
        if (!is_array($entry) || !is_scalar($entry[$records['key']] ?? null)) {
            throw new InvalidArgumentException('Missing JSON record key.');
        }
        $key = $records['namespace'] . ':' . $records['type'] . ':' . $entry[$records['key']];
        $fields = [];
        foreach ($records['fields'] ?? [] as $name => $source) {
            $value = $entry[$source] ?? null;
            $fields[$name] = is_scalar($value) || $value === null ? $value : null;
        }
        $out[] = new CrawlRecord($key, $records['type'], $parent, $fields, $url, $location);
        foreach ($records['children'] ?? [] as $child) {
            foreach ($this->path($entry, $child['path'] ?? []) as $item) {
                $this->jsonRecords($out, $item, $child, $url, $key, $location);
            }
        }
    }

    /** @param list<string|int> $path
     * @return list<mixed>
     */
    private function path(mixed $value, array $path): array
    {
        foreach ($path as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return [];
            }
            $value = $value[$segment];
        }
        if (!is_array($value)) {
            return [];
        }
        return array_is_list($value) ? $value : [$value];
    }

    /** @param array<string, mixed> $stage */
    private function count(array $stage, string $html, string $id, string $branch): void
    {
        if (isset($stage['expectedCountPattern']) && preg_match($stage['expectedCountPattern'], $html, $match)) {
            $count = (int) ($match[1] ?? -1);
            if ($count >= 0) {
                $this->expected = $count;
                $this->expectedStage = $id;
            } else {
                $this->fail($id, $branch, CrawlFailureCode::InvalidExpectedCount);
            }
        }
    }

    private function resolve(string $base, string $target, CrawlConfig $config): string
    {
        if (Url::parts($target) !== null) {
            $url = $target;
        } else {
            $parts = parse_url($base);
            $root = $parts['scheme'] . '://' . $parts['host'];
            $url = $root . (str_starts_with($target, '/') ? $target : rtrim(dirname($parts['path'] ?? '/'), '/') . '/' . $target);
        }
        $this->safe($url, $config);
        return $url;
    }

    /** @param array<string, scalar|null> $fields */
    private function template(string $template, array $fields): string
    {
        return preg_replace_callback('/\{([^{}]+)\}/', static function (array $match) use ($fields): string {
            $value = $fields[$match[1]] ?? null;
            if (!is_scalar($value) || $value === '') {
                throw new InvalidArgumentException('Missing URL template field.');
            }
            return rawurlencode((string) $value);
        }, $template) ?? throw new InvalidArgumentException('Invalid URL template.');
    }

    private function fail(string $stage, string $branch, CrawlFailureCode $code, ?string $detail = null, int $attempts = 1): void
    {
        $this->failures[] = new CrawlFailure(hash('sha256', $stage . "\0" . $branch . "\0" . $code->value), $stage, $branch, $code, $detail, $attempts);
        $this->incomplete[] = $branch;
    }
}
