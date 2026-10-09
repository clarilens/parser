<?php

declare(strict_types=1);

use Clarilens\Parser\Config\ParserConfig;
use Clarilens\Parser\Config\RuleRegistry;
use Clarilens\Parser\Exception\SourceDriftException;
use Clarilens\Parser\Extraction\ExtractionRule;
use Clarilens\Parser\Extraction\PdfContext;
use Clarilens\Parser\Extraction\PdfRegexRule;
use Clarilens\Parser\Extraction\RuleContext;
use Clarilens\Parser\Extraction\RuleRunner;
use Clarilens\Parser\Parser\DocumentParser;
use Clarilens\Parser\Parser\ParseRequest;
use Clarilens\Parser\Parser\ParserFactory;
use Clarilens\Parser\Parser\ParserType;
use Clarilens\Parser\Result\DocumentOrigin;
use Clarilens\Parser\Result\ExtractedValue;
use Clarilens\Parser\Result\FactEvidence;
use Clarilens\Parser\Result\ObservedFact;
use Clarilens\Parser\Result\ObservedFactBatch;
use Clarilens\Parser\Result\PageLocation;
use Clarilens\Parser\Source\BrowserRequest;
use Clarilens\Parser\Source\HttpFetchRequest;
use Clarilens\Parser\Source\SourceLoader;
use Clarilens\Parser\Source\SourcePolicy;
use Clarilens\Parser\Source\Url;
use Clarilens\Parser\Source\WebClient;

final readonly class ConstantTestRule implements ExtractionRule
{
    public function __construct(public string $field, public bool $required, public string $constant)
    {
    }

    public function field(): string
    {
        return $this->field;
    }

    public function required(): bool
    {
        return $this->required;
    }

    public function supports(ParserType $type): bool
    {
        return $type === ParserType::HTML;
    }

    /** @return list<ExtractedValue> */
    public function extract(RuleContext $context): array
    {
        return [new ExtractedValue($this->field, $this->constant)];
    }
}

function same(mixed $expected, mixed $actual): void
{
    PHPUnit\Framework\Assert::assertSame($expected, $actual);
}

function rejects(callable $operation, string $exception = InvalidArgumentException::class): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        PHPUnit\Framework\Assert::assertInstanceOf($exception, $error);
        return;
    }
    PHPUnit\Framework\Assert::fail("Expected $exception to be thrown.");
}

/** @param array<string, mixed> $overrides */
function config(array $overrides = []): ParserConfig
{
    return ParserConfig::fromJson(array_replace_recursive([
        'source' => ['type' => 'file', 'path' => 'page.html'],
        'parser' => ['type' => 'HTML'],
        'rules' => [['type' => 'xpath', 'field' => 'title', 'required' => true, 'selector' => '//h1']],
    ], $overrides));
}

$cases = [
    'URL validation rejects ambiguous destinations' => static function (): void {
        same(['scheme' => 'https', 'host' => 'example.com'], Url::parts('https://EXAMPLE.COM/path'));
        foreach ([
            'https://example.com\\@127.0.0.1/',
            "https://example.com/\nanything",
            'https://example.com./',
            'https://-example.com/',
        ] as $url) {
            same(null, Url::parts($url));
        }
        rejects(static fn () => new HttpFetchRequest('https://example.com@127.0.0.1/', ['127.0.0.1'], 1000));
    },
    'HTML values, locations, and required failures' => static function (): void {
        $configured = config(['rules' => [
            ['type' => 'xpath', 'field' => 'title', 'required' => true, 'selector' => '//h1'],
            ['type' => 'xpath', 'field' => 'missing', 'required' => true, 'selector' => '//h2'],
            ['type' => 'xpath', 'field' => 'optional', 'required' => false, 'selector' => '//h3'],
        ]]);
        $parser = new DocumentParser(new SourceLoader(new SourcePolicy(__DIR__), __DIR__));
        $result = $parser->parse(new ParseRequest($configured, '<h1>Alpha</h1>'));
        same(1, count($result->values));
        same('Alpha', $result->values->items[0]->value);
        same('title', $result->values->items[0]->field);
        same(true, $result->values->items[0]->location->nodePath !== '');
        same(1, count($result->failures));
        same('missing', $result->failures->items[0]->field);
    },
    'In-memory config needs no source or loader' => static function (): void {
        $config = ParserConfig::fromJson([
            'parser' => ['type' => 'HTML'],
            'rules' => [['type' => 'xpath', 'field' => 'title', 'required' => true, 'selector' => '//h1']],
        ]);
        same(null, $config->source);
        same('From memory', (new DocumentParser())->parse(new ParseRequest($config, '<h1>From memory</h1>'))->values->items[0]->value);
        rejects(static fn () => (new DocumentParser())->parse(new ParseRequest($config)));
    },
    'CSS rule chooses visible or own text' => static function (): void {
        $config = ParserConfig::fromJson([
            'parser' => ['type' => 'HTML'],
            'rules' => [
                ['type' => 'css', 'field' => 'visible', 'required' => true, 'selector' => '.product', 'textMode' => 'visible'],
                ['type' => 'css', 'field' => 'own', 'required' => true, 'selector' => '.product', 'textMode' => 'own'],
            ],
        ]);
        $html = '<div class="product">Main <span>child</span><script>hidden</script></div>';
        $values = (new DocumentParser())->parse(new ParseRequest($config, $html))->values->items;
        same('Main child', $values[0]->value);
        same('Main', $values[1]->value);
    },
    'Field checks catch count and content drift' => static function (): void {
        $config = ParserConfig::fromJson([
            'parser' => ['type' => 'HTML'],
            'rules' => [['type' => 'xpath', 'field' => 'title', 'required' => true, 'selector' => '//h1']],
            'checks' => [['field' => 'title', 'minCount' => 1, 'maxCount' => 1, 'pattern' => '/^Example /']],
            'strict' => true,
        ]);
        $parser = new DocumentParser();
        same('Example Product', $parser->parse(new ParseRequest($config, '<h1>Example Product</h1>'))->values->items[0]->value);
        rejects(static fn () => $parser->parse(new ParseRequest($config, '<h1>Other</h1>')), SourceDriftException::class);
        rejects(static fn () => $parser->parse(new ParseRequest($config, '<h1>Example A</h1><h1>Example B</h1>')), SourceDriftException::class);
    },
    'Result retains document origin and raw wording' => static function (): void {
        $config = ParserConfig::fromJson([
            'parser' => ['type' => 'HTML'],
            'rules' => [['type' => 'xpath', 'field' => 'title', 'required' => true, 'selector' => '//h1']],
        ]);
        $origin = new DocumentOrigin('https://example.com/product', new DateTimeImmutable('2026-10-06T12:00:00+04:00'));
        $result = (new DocumentParser())->parse(new ParseRequest($config, '<h1>  Mortgage   terms </h1>', origin: $origin));
        same($origin, $result->origin);
        same('Mortgage terms', $result->values->items[0]->value);
        same('  Mortgage   terms ', $result->values->items[0]->raw);
    },
    'Extraction yields versioned, evidence-backed facts' => static function (): void {
        $config = ParserConfig::fromJson([
            'parser' => ['type' => 'HTML'],
            'rules' => [['type' => 'xpath', 'field' => 'price', 'required' => true, 'selector' => '//strong']],
        ]);
        $origin = new DocumentOrigin('https://example.com/item', new DateTimeImmutable('2026-10-06T12:00:00+04:00'));
        $outcome = (new DocumentParser())->parse(new ParseRequest($config, '<strong>  7.5%  </strong>', origin: $origin));
        $batch = ObservedFactBatch::fromOutcome($outcome, 'catalog-a', 'source-1', 'parser-2');
        same(false, $batch->complete);
        same('7.5%', $batch->facts[0]->value);
        same('  7.5%  ', $batch->facts[0]->evidence->wording);
        same('https://example.com/item', $batch->facts[0]->evidence->url);
        same('catalog-a', $batch->facts[0]->evidence->sourceId);
        same(true, $batch->facts[0]->evidence->location->nodePath !== '');
        same($batch->toArray(), ObservedFactBatch::fromJson($batch->toJson())->toArray());
        $unknownVersion = $batch->toArray();
        $unknownVersion['schemaVersion'] = 3;
        rejects(static fn () => ObservedFactBatch::fromArray($unknownVersion));
        rejects(static fn () => ObservedFactBatch::fromOutcome((new DocumentParser())->parse(new ParseRequest($config, '<strong>7%</strong>')), 'catalog-a', 'v1', 'v1'));
    },
    'PDF fact evidence retains its page number' => static function (): void {
        $fact = new ObservedFact('price', '7.5%', new FactEvidence(
            'https://example.com/terms.pdf',
            new DateTimeImmutable('2026-10-06T12:00:00Z'),
            'Price: 7.5%',
            new PageLocation(2),
            'catalog-a',
            'new subscribers',
        ));
        same($fact->toArray(), ObservedFact::fromArray($fact->toArray())->toArray());
        same(2, ObservedFact::fromArray($fact->toArray())->evidence->location->page);
        same('new subscribers', $fact->evidence->applicability);
    },
    'Table grid preserves coordinates and spans' => static function (): void {
        $config = ParserConfig::fromJson([
            'parser' => ['type' => 'HTML'],
            'tables' => [['name' => 'rates', 'selector' => '#rates']],
            'strict' => true,
        ]);
        $html = '<table id="rates"><tr><th rowspan="2">Term</th><th colspan="2">Rate</th></tr>'
            . '<tr><th>AMD</th><th>USD</th></tr><tr><td>12 months</td><td>7%</td><td>4%</td></tr></table>';
        $result = (new DocumentParser())->parse(new ParseRequest($config, $html));
        same(1, count($result->tables));
        $rows = $result->tables->items[0]->rows;
        same('Term', $rows[1][0]->text);
        same('Rate', $rows[0][2]->text);
        same('USD', $rows[1][2]->text);
        same('4%', $rows[2][2]->text);
        same(2, $rows[2][2]->row);
        rejects(static fn () => (new DocumentParser())->parse(new ParseRequest($config, '<table></table>')), SourceDriftException::class);
    },
    'Config JSON round trip preserves rules and checks' => static function (): void {
        $config = ParserConfig::fromJson([
            'source' => ['type' => 'web', 'url' => 'https://example.com/', 'render' => 'browser',
                'actions' => [['type' => 'click', 'selector' => '#open']], 'ready' => ['selector' => '#ready'], 'timeoutMs' => 1200],
            'parser' => ['type' => 'HTML'],
            'rules' => [['type' => 'css', 'field' => 'title', 'required' => true, 'selector' => 'h1', 'textMode' => 'visible']],
            'tables' => [['name' => 'rates', 'selector' => 'table.rates']],
            'checks' => [['field' => 'title', 'minCount' => 1, 'maxCount' => 1]],
            'strict' => true,
        ]);
        $json = $config->toJson();
        same($config->toArray(), ParserConfig::fromJson($json)->toArray());
        rejects(static fn () => ParserConfig::fromJson(str_replace('"version":1', '"version":2', $json)));
    },
    'Custom rule round trips through its registered encoder' => static function (): void {
        $registry = new RuleRegistry();
        $registry->register(
            'constant',
            static fn (array $data): ExtractionRule => new ConstantTestRule($data['field'], $data['required'], $data['value']),
            static fn (ExtractionRule $rule): ?array => $rule instanceof ConstantTestRule ? ['value' => $rule->constant] : null,
        );
        $config = ParserConfig::fromJson([
            'parser' => ['type' => 'HTML'],
            'rules' => [['type' => 'constant', 'field' => 'fixed', 'required' => true, 'value' => 'known']],
        ], $registry);
        $restored = ParserConfig::fromJson($config->toJson($registry), $registry);
        same('known', (new DocumentParser())->parse(new ParseRequest($restored, '<html></html>'))->values->items[0]->value);
        rejects(static fn () => $config->toJson());
    },
    'Embedded JSON path preserves item locations' => static function (): void {
        $config = ParserConfig::fromJson([
            'parser' => ['type' => 'HTML'],
            'rules' => [[
                'type' => 'jsonScript', 'field' => 'title', 'required' => true,
                'selector' => '//script[@id="__NEXT_DATA__"]',
                'path' => ['props', 'pageProps', 'data', 'items', '*', 'title'],
            ]],
        ]);
        $html = '<script id="__NEXT_DATA__">{"props":{"pageProps":{"data":{"items":[{"title":"First"},{"title":"Second"}]}}}}</script>';
        $values = (new DocumentParser())->parse(new ParseRequest($config, $html))->values->items;
        same(['First', 'Second'], array_map(static fn ($value): string => $value->value, $values));
        same('/props/pageProps/data/items/1/title', $values[1]->location->pointer);
        rejects(static fn () => (new DocumentParser())->parse(new ParseRequest($config, '<script id="__NEXT_DATA__">{bad}</script>')));
    },
    'XML namespaces and DTD protection' => static function (): void {
        $configured = config([
            'parser' => ['type' => 'XML', 'namespaces' => ['x' => 'urn:test']],
            'rules' => [['type' => 'xpath', 'field' => 'code', 'required' => true, 'selector' => '//x:item/@code']],
        ]);
        $parser = new DocumentParser(new SourceLoader(new SourcePolicy(__DIR__), __DIR__));
        $result = $parser->parse(new ParseRequest($configured, '<root xmlns="urn:test"><item code="A1"/></root>'));
        same('A1', $result->values->items[0]->value);
        rejects(static fn () => $parser->parse(new ParseRequest($configured, '<!DOCTYPE root><root/>')));
        rejects(static fn () => config(['parser' => ['type' => 'XML'], 'rules' => [['type' => 'xpath', 'field' => 'code', 'required' => true, 'selector' => '//x:item']]]));
    },
    'PDF regex captures page locations' => static function (): void {
        $result = RuleRunner::run(new PdfContext(['nothing', 'Price: 7.5%']), [new PdfRegexRule('price', true, '/Price: ([0-9.]+%)/')]);
        same('7.5%', $result->values->items[0]->value);
        same(2, $result->values->items[0]->location->page);
        rejects(static fn () => new PdfRegexRule('price', true, '['));
    },
    'PDF rules accept pre-extracted pages' => static function (): void {
        $config = ParserConfig::fromJson([
            'parser' => ['type' => 'PDF'],
            'rules' => [['type' => 'pdfRegex', 'field' => 'price', 'required' => true, 'pattern' => '/Price: ([0-9.]+%)/']],
        ]);
        $result = (new DocumentParser())->parse(new ParseRequest($config, pdfPages: ['intro', 'Price: 7.5%']));
        same('7.5%', $result->values->items[0]->value);
        same(2, $result->values->items[0]->location->page);
        rejects(static fn () => (new DocumentParser())->parse(new ParseRequest($config, pdfPages: [])));
    },
    'Configuration rejects incompatible rules' => static function (): void {
        rejects(static fn () => config(['rules' => [['type' => 'unknown', 'field' => 'x', 'required' => true]]]));
        rejects(static fn () => config(['parser' => ['type' => 'PDF']]));
        rejects(static fn () => ParserConfig::fromJson('{invalid'), JsonException::class);
    },
    'Browser options reach the client' => static function (): void {
        $client = new class () implements WebClient {
            public function render(BrowserRequest $request): string
            {
                same('#show', $request->actions[0]['selector']);
                same('#ready', $request->readySelector);
                same(1500, $request->timeoutMs);
                return '<h1>Rendered</h1>';
            }
        };
        $configured = config(['source' => [
            'type' => 'web', 'url' => 'https://example.com/', 'render' => 'browser',
            'actions' => [['type' => 'click', 'selector' => '#show']],
            'ready' => ['selector' => '#ready'], 'timeoutMs' => 1500,
        ]]);
        $parser = new DocumentParser(new SourceLoader(new SourcePolicy(__DIR__, ['example.com']), __DIR__, browserClient: $client));
        same('Rendered', $parser->parse(new ParseRequest($configured))->values->items[0]->value);
    },
    'Factory loads local files and rejects traversal' => static function (): void {
        $directory = sys_get_temp_dir() . '/Clarilens Parser-' . bin2hex(random_bytes(8));
        if (!mkdir($directory)) {
            throw new RuntimeException('Cannot create test directory.');
        }
        $html = $directory . '/page.html';
        $json = $directory . '/config.json';
        try {
            file_put_contents($html, '<h1>From disk</h1>');
            file_put_contents($json, json_encode([
                'source' => ['type' => 'file', 'path' => 'page.html'],
                'parser' => ['type' => 'HTML'],
                'rules' => [['type' => 'xpath', 'field' => 'title', 'required' => true, 'selector' => '//h1']],
            ], JSON_THROW_ON_ERROR));
            $configured = ParserFactory::fromConfigFile($json);
            same('From disk', $configured->parse()->values->items[0]->value);
            same('page.html', $configured->command->config->source->path);
            file_put_contents($json, str_replace('page.html', '../outside.html', file_get_contents($json)));
            rejects(static fn () => ParserFactory::fromConfigFile($json));
        } finally {
            unlink($html);
            unlink($json);
            rmdir($directory);
        }
    },
];

return $cases;
