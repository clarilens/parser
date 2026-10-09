<?php

declare(strict_types=1);

use Clarilens\Parser\Condition\Condition;
use Clarilens\Parser\Condition\ConditionResult;
use Clarilens\Parser\Config\CrawlConfig;
use Clarilens\Parser\Config\DocumentFactConfig;
use Clarilens\Parser\Config\OperationRegistry;
use Clarilens\Parser\Crawl\CrawlRunner;
use Clarilens\Parser\Parser\DocumentFactParser;
use Clarilens\Parser\Parser\SavedDocumentValidator;
use Clarilens\Parser\Result\CoverageStatus;
use Clarilens\Parser\Result\CrawlFailureCode;
use Clarilens\Parser\Result\FactState;
use PHPUnit\Framework\TestCase;

final class DeclarativeTest extends TestCase
{
    public function testConditionUnknownAndCaptureEvidence(): void
    {
        $condition = new Condition(['equals' => ['field' => 'rate', 'value' => 5]], ['rate' => 'number']);
        self::assertSame(ConditionResult::Unknown, $condition->evaluate([]));
        self::assertSame(ConditionResult::True, $condition->evaluate(['rate' => 5]));
        self::assertSame(ConditionResult::True, $condition->evaluate(['rate' => 5.0]));
        self::assertSame(ConditionResult::False, $condition->evaluate(['rate' => 4]));
        $config = new DocumentFactConfig(['version' => 1, 'fields' => [[
            'id' => 'rate', 'selector' => 'p', 'pattern' => '/(?<value>\d+,\d+)\s*%/u',
            'type' => 'number', 'transforms' => [['op' => 'number', 'decimal' => ',']],
        ]]]);
        $batch = (new DocumentFactParser())->parse($config, '<p>Ставка  12,5 %</p>', 'test', 'https://example.org/rate', new DateTimeImmutable('2026-10-09T00:00:00Z'));
        self::assertTrue($batch->complete);
        self::assertSame('12.5', $batch->facts[0]->value);
        self::assertSame('12,5', $batch->facts[0]->evidence->wording);
        self::assertSame(8, $batch->facts[0]->evidence->location->offset);
    }

    public function testOfflineCrawlRetainsParentContexts(): void
    {
        $config = new CrawlConfig(['crawlVersion' => 1, 'start' => 'index', 'url' => 'https://example.org/index', 'allowedHosts' => ['example.org'], 'stages' => [
            'index' => ['records' => ['selector' => '.parent', 'type' => 'parent', 'namespace' => 'site', 'key' => 'id', 'fields' => ['id' => ['selector' => '.id']]]],
            'child' => ['records' => ['selector' => '.child', 'type' => 'child', 'namespace' => 'site', 'key' => 'id', 'fields' => ['id' => ['selector' => '.id']]]],
        ]]);
        $runner = new CrawlRunner(static fn (string $url, string $mode): string => '<div class="parent" id="one"><span class="id">one</span></div>');
        $outcome = $runner->run($config);
        self::assertTrue($outcome->traversalComplete);
        self::assertSame(CoverageStatus::Unconfirmed, $outcome->coverage);
        self::assertCount(1, $outcome->records);
    }

    public function testNestedJsonAndBrowserSequence(): void
    {
        $metrum = new CrawlConfig(['crawlVersion' => 1, 'start' => 'catalog', 'url' => 'https://example.org/catalog', 'allowedHosts' => ['example.org'], 'stages' => [
            'catalog' => ['records' => ['jsonAttribute' => 'header-main', 'attribute' => ':projects', 'path' => [], 'type' => 'project', 'namespace' => 'site', 'key' => 'id', 'fields' => ['id' => 'id', 'slug' => 'slug'], 'children' => [
                ['path' => ['child_projects'], 'type' => 'project', 'namespace' => 'site-child', 'key' => 'id', 'fields' => ['id' => 'id', 'slug' => 'slug']],
            ]]],
        ]]);
        $source = file_get_contents(__DIR__ . '/Fixtures/declarative/metrum.html');
        $result = (new CrawlRunner(static fn (string $url, string $mode): string => $source))->run($metrum);
        self::assertCount(2, $result->records);
        self::assertNotSame($result->records[0]->key, $result->records[1]->key);
        self::assertSame($result->records[0]->key, $result->records[1]->parentKey);

        $arm = new CrawlConfig(['crawlVersion' => 1, 'start' => 'floor', 'url' => 'https://example.org/floor', 'allowedHosts' => ['example.org'], 'stages' => [
            'floor' => ['render' => 'browser', 'repeat' => ['click' => '.more', 'items' => '.item'], 'expectedCountPattern' => '/(\d+) items/', 'records' => [
                'selector' => '.item', 'type' => 'unit', 'namespace' => 'site', 'key' => 'id', 'fields' => ['id' => ['selector' => '.id']],
            ]],
        ]]);
        $initial = file_get_contents(__DIR__ . '/Fixtures/declarative/arm-initial.html');
        $final = file_get_contents(__DIR__ . '/Fixtures/declarative/arm-final.html');
        $complete = (new CrawlRunner(static fn (string $url, string $mode): array => [$initial, $final]))->run($arm);
        self::assertCount(3, $complete->records);
        self::assertSame(CoverageStatus::Confirmed, $complete->coverage);
        self::assertTrue($complete->traversalComplete);
        $stalled = (new CrawlRunner(static fn (string $url, string $mode): array => [$initial, $initial]))->run($arm);
        self::assertSame(CrawlFailureCode::NoProgress, $stalled->failures[0]->code);
        self::assertFalse($stalled->traversalComplete);
    }

    public function testTableProjectionConflictAndV1Read(): void
    {
        $config = new DocumentFactConfig(['version' => 1, 'fields' => [['id' => 'title', 'selector' => 'h1', 'required' => true]], 'tables' => [[
            'name' => 'rates', 'selector' => 'table', 'field' => 'rate', 'headerRow' => 0, 'keyColumn' => 0,
        ]]]);
        $html = '<h1>Terms</h1><table><tr><th>Term</th><th>AMD</th></tr><tr><td>12m</td><td>5%</td></tr><tr><td>12m</td><td>6%</td></tr></table>';
        $batch = (new DocumentFactParser())->parse($config, $html, 'sample', 'https://example.org/terms', new DateTimeImmutable('2026-10-09T00:00:00Z'));
        self::assertFalse($batch->complete);
        self::assertSame(FactState::Conflict, $batch->resolutions[0]->state);
        self::assertSame(FactState::Conflict->value, $batch->toArray()['resolutions'][0]['state']);
        self::assertSame(FactState::Conflict, Clarilens\Parser\Result\ObservedFactBatch::fromJson($batch->toJson())->resolutions[0]->state);
        self::assertCount(2, $batch->resolutions[0]->evidences);
        self::assertSame('AMD', $batch->resolutions[0]->evidences[0]->location->header);
        $v1 = $batch->toArray();
        $v1['schemaVersion'] = 1;
        unset($v1['resolutions'], $v1['groupCompleteness'], $v1['configHash'], $v1['registryFingerprint'], $v1['configSchemaVersion']);
        foreach ($v1['facts'] as &$fact) {
            unset($fact['evidences'], $fact['rawInput'], $fact['operations']);
        }
        $read = Clarilens\Parser\Result\ObservedFactBatch::fromArray($v1);
        self::assertCount(1, $read->facts[0]->allEvidence());
    }

    public function testSharedFetchKeepsParentsAndReportsPartialBranch(): void
    {
        $config = new CrawlConfig(['crawlVersion' => 1, 'start' => 'index', 'url' => 'https://example.org/index', 'allowedHosts' => ['example.org'], 'limits' => ['maxPages' => 3], 'stages' => [
            'index' => ['records' => ['selector' => '.parent', 'type' => 'parent', 'namespace' => 'site', 'key' => 'id', 'fields' => [
                'id' => ['selector' => '.id'], 'url' => ['selector' => 'a', 'attribute' => 'href'],
            ]], 'follow' => ['stage' => 'child', 'field' => 'url']],
            'child' => ['records' => ['selector' => '.child', 'type' => 'child', 'namespace' => 'site', 'key' => 'id', 'fields' => ['id' => ['selector' => '.id']]]],
        ]]);
        $calls = 0;
        $runner = new CrawlRunner(static function (string $url, string $mode) use (&$calls): string {
            ++$calls;
            return str_ends_with($url, '/index')
                ? '<div class="parent"><span class="id">a</span><a href="/child">go</a></div><div class="parent"><span class="id">b</span><a href="/child">go</a></div>'
                : '<div class="child"><span class="id">c</span></div>';
        });
        $outcome = $runner->run($config);
        self::assertSame(2, $calls);
        self::assertCount(4, $outcome->records);
        self::assertSame(CrawlFailureCode::IdentityConflict, $outcome->failures[0]->code);
        self::assertCount(1, $outcome->incompleteSubtrees);
    }

    public function testCountMismatchAndLimitRemainDistinct(): void
    {
        $config = new CrawlConfig(['crawlVersion' => 1, 'start' => 'floor', 'url' => 'https://example.org/floor', 'allowedHosts' => ['example.org'], 'stages' => [
            'floor' => ['render' => 'browser', 'repeat' => ['click' => '.more', 'items' => '.item'], 'expectedCountPattern' => '/(\d+) items/', 'records' => [
                'selector' => '.item', 'type' => 'unit', 'namespace' => 'site', 'key' => 'id', 'fields' => ['id' => ['selector' => '.id']],
            ]],
        ]]);
        $initial = file_get_contents(__DIR__ . '/Fixtures/declarative/arm-initial.html');
        $terminal = str_replace('<button class="more">More</button>', '', $initial);
        $mismatch = (new CrawlRunner(static fn (string $url, string $mode): array => [$terminal]))->run($config);
        self::assertSame(CoverageStatus::Failed, $mismatch->coverage);
        self::assertSame(CrawlFailureCode::CountMismatch, $mismatch->failures[0]->code);
        self::assertSame(CoverageStatus::Failed->value, $mismatch->toArray()['coverage']);
        self::assertSame(CrawlFailureCode::CountMismatch->value, $mismatch->toArray()['failures'][0]['code']);
        self::assertSame('expected 3, observed 2', $mismatch->failures[0]->detail);
        self::assertCount(2, $mismatch->records);
        $limitedConfig = new CrawlConfig(['crawlVersion' => 1, 'start' => 'floor', 'url' => 'https://example.org/floor', 'allowedHosts' => ['example.org'], 'limits' => ['maxClicks' => 1], 'stages' => $config->stages]);
        $middle = str_replace('</body>', '<div class="item"><span class="id">103</span></div></body>', $initial);
        $limited = (new CrawlRunner(static fn (string $url, string $mode): array => [$initial, $middle, file_get_contents(__DIR__ . '/Fixtures/declarative/arm-final.html')]))->run($limitedConfig);
        self::assertSame(CrawlFailureCode::ClickLimitExhausted, $limited->failures[0]->code);
        self::assertCount(3, $limited->records);
    }

    public function testInvalidTableCellProducesRowFailure(): void
    {
        $config = new DocumentFactConfig(['version' => 1, 'fields' => [['id' => 'title', 'selector' => 'h1']], 'tables' => [[
            'name' => 'rates', 'selector' => 'table', 'field' => 'rate', 'headerRow' => 0, 'keyColumn' => 0,
            'transforms' => [['op' => 'number', 'unit' => '%']],
        ]]]);
        $batch = (new DocumentFactParser())->parse($config, '<h1>Terms</h1><table><tr><th>Term</th><th>AMD</th></tr><tr><td>12m</td><td>unknown</td></tr></table>', 'sample', 'https://example.org/terms', new DateTimeImmutable('2026-10-09T00:00:00Z'));
        self::assertFalse($batch->complete);
        self::assertSame(FactState::Error, $batch->resolutions[0]->state);
        self::assertStringContainsString('row 1', $batch->resolutions[0]->reason);
        self::assertSame('unknown', $batch->resolutions[0]->evidences[0]->wording);
    }

    public function testConfigValidationAndOperationPinning(): void
    {
        $registry = new OperationRegistry();
        $registry->register('uppercase', '1', static fn (string $value): string => strtoupper($value));
        $data = ['version' => 1, 'fields' => [['id' => 'title', 'selector' => 'h1', 'transforms' => [['op' => 'registered', 'id' => 'uppercase', 'version' => '1']]]]];
        $config = new DocumentFactConfig($data, $registry);
        self::assertSame($config->hash, (new DocumentFactConfig($data, $registry))->hash);
        self::assertNotSame($config->hash, $config->registryFingerprint);
        $data['fields'][0]['transforms'][0]['version'] = '2';
        $this->expectException(InvalidArgumentException::class);
        new DocumentFactConfig($data, $registry);
    }

    public function testInvalidGraphRejectsBeforeFetch(): void
    {
        $calls = 0;
        $fetch = static function () use (&$calls): string {
            ++$calls;
            return '';
        };
        try {
            new CrawlConfig(['crawlVersion' => 1, 'start' => 'first', 'url' => 'https://example.org/', 'allowedHosts' => ['example.org'], 'stages' => [
                'first' => ['records' => ['selector' => '.item', 'type' => 'item', 'namespace' => 'site', 'key' => 'id', 'fields' => ['id' => ['selector' => '.id']]], 'follow' => ['stage' => 'missing', 'field' => 'id']],
            ]]);
            self::fail('Expected invalid graph.');
        } catch (InvalidArgumentException) {
            self::assertSame(0, $calls);
        }
        self::assertNotNull($fetch);
    }

    public function testSavedCreditAndVariantContracts(): void
    {
        $at = new DateTimeImmutable('2026-10-09T00:00:00Z');
        $creditConfig = new DocumentFactConfig(['version' => 1, 'fields' => [[
            'id' => 'annualRate', 'selector' => '#credit p:first-child', 'pattern' => '/(?<value>\d+,\d+)\s*%/u',
            'type' => 'number', 'required' => true, 'transforms' => [['op' => 'number', 'decimal' => ',']],
        ]]]);
        $credit = (new DocumentFactParser())->parse($creditConfig, file_get_contents(__DIR__ . '/Fixtures/declarative/text-credit.html'), 'credit', 'https://example.org/credit', $at);
        self::assertTrue($credit->complete);
        self::assertSame('12.5', $credit->facts[0]->value);
        self::assertSame('12,5', $credit->facts[0]->evidence->wording);
        self::assertSame('12,5', $credit->facts[0]->rawInput);
        self::assertSame(['number@1'], $credit->facts[0]->operations);

        $tableConfig = new DocumentFactConfig(['version' => 1, 'fields' => [['id' => 'title', 'selector' => 'h1', 'required' => true]], 'tables' => [[
            'name' => 'rates', 'selector' => '#rates', 'field' => 'annualRate', 'headerRow' => 0, 'keyColumn' => 0,
            'transforms' => [['op' => 'number', 'unit' => '%']],
        ]]]);
        $table = (new DocumentFactParser())->parse($tableConfig, file_get_contents(__DIR__ . '/Fixtures/declarative/variant-table.html'), 'deposit', 'https://example.org/deposit', $at);
        self::assertTrue($table->complete);
        self::assertCount(5, $table->facts);
        self::assertSame('12 months:AMD', $table->facts[1]->groupKey);
        self::assertSame('8.5', $table->facts[1]->value);
        self::assertSame('AMD', $table->facts[1]->evidence->location->header);
    }

    public function testSavedDiffReportsIdentityChange(): void
    {
        $base = ['version' => 1, 'fields' => [['id' => 'title', 'selector' => 'h1']], 'tables' => [[
            'name' => 'rates', 'selector' => '#rates', 'field' => 'rate', 'headerRow' => 0, 'keyColumn' => 0,
        ]]];
        $before = new DocumentFactConfig($base);
        $base['tables'][0]['keyColumn'] = 1;
        $after = new DocumentFactConfig($base);
        $diff = (new SavedDocumentValidator())->compare($before, $after, file_get_contents(__DIR__ . '/Fixtures/declarative/variant-table.html'), 'deposit', 'https://example.org/deposit', new DateTimeImmutable('2026-10-09T00:00:00Z'));
        self::assertNotEmpty($diff->removed);
        self::assertNotEmpty($diff->added);
    }

    public function testPriorCoverageNeedsExplicitThreshold(): void
    {
        $base = ['crawlVersion' => 1, 'start' => 'index', 'url' => 'https://example.org/', 'allowedHosts' => ['example.org'], 'stages' => [
            'index' => ['records' => ['selector' => '.item', 'type' => 'item', 'namespace' => 'site', 'key' => 'id', 'fields' => ['id' => ['selector' => '.id']]]],
        ], 'priorCount' => 10];
        try {
            new CrawlConfig($base);
            self::fail('Expected missing threshold rejection.');
        } catch (InvalidArgumentException) {
            self::assertTrue(true);
        }
        $base['priorThreshold'] = 0.8;
        $outcome = (new CrawlRunner(static fn (string $url, string $mode): string => '<div class="item"><span class="id">1</span></div>'))->run(new CrawlConfig($base));
        self::assertSame(CoverageStatus::Failed, $outcome->coverage);
        self::assertSame(CrawlFailureCode::PriorCountThreshold, $outcome->failures[0]->code);
    }

    public function testRetryExhaustionHasAttempts(): void
    {
        $config = new CrawlConfig(['crawlVersion' => 1, 'start' => 'index', 'url' => 'https://example.org/', 'allowedHosts' => ['example.org'], 'limits' => ['maxRetries' => 3], 'stages' => [
            'index' => ['records' => ['selector' => '.item', 'type' => 'item', 'namespace' => 'site', 'key' => 'id', 'fields' => ['id' => ['selector' => '.id']]]],
        ]]);
        $calls = 0;
        $outcome = (new CrawlRunner(static function () use (&$calls): string {
            ++$calls;
            throw new RuntimeException('offline failure');
        }))->run($config);
        self::assertSame(3, $calls);
        self::assertSame(3, $outcome->failures[0]->attempts);
        self::assertSame(CrawlFailureCode::RetryExhausted, $outcome->failures[0]->code);
        self::assertCount(0, $outcome->records);
        self::assertFalse($outcome->traversalComplete);
    }

    public function testGeneratedUrlsStayWithinAllowedHost(): void
    {
        $config = new CrawlConfig(['crawlVersion' => 1, 'start' => 'index', 'url' => 'https://example.org/index', 'allowedHosts' => ['example.org'], 'stages' => [
            'index' => ['records' => ['selector' => '.item', 'type' => 'item', 'namespace' => 'site', 'key' => 'id', 'fields' => ['id' => ['selector' => '.id']]], 'follow' => ['stage' => 'detail', 'template' => 'https://example.org/detail/{id}']],
            'detail' => ['records' => ['selector' => '.detail', 'type' => 'detail', 'namespace' => 'site', 'key' => 'id', 'fields' => ['id' => ['selector' => '.id']]]],
        ]]);
        $seen = [];
        (new CrawlRunner(static function (string $url, string $mode) use (&$seen): string {
            $seen[] = $url;
            return str_ends_with($url, '/index') ? '<div class="item"><span class="id">other.example</span></div>' : '<div class="detail"><span class="id">d</span></div>';
        }))->run($config);
        self::assertSame(['https://example.org/index', 'https://example.org/detail/other.example'], $seen);
        $unsafe = $config->toArray();
        $unsafe['stages']['index']['follow']['template'] = 'https://evil.example/detail/{id}';
        $this->expectException(InvalidArgumentException::class);
        new CrawlConfig($unsafe);
    }

    public function testScopedFieldsKeepIndependentRows(): void
    {
        $config = new DocumentFactConfig(['version' => 1, 'fields' => [
            ['id' => 'enabled', 'selector' => '#enabled', 'required' => true],
            ['id' => 'rate', 'scope' => 'row', 'container' => '.row', 'groupKey' => '.key', 'selector' => '.rate', 'required' => true,
                'when' => ['equals' => ['field' => 'enabled', 'value' => 'yes']]],
        ]]);
        $html = '<span id="enabled">yes</span><div class="row"><b class="key">a</b><span class="rate">5%</span></div><div class="row"><b class="key">b</b></div>';
        $batch = (new DocumentFactParser())->parse($config, $html, 'source', 'https://example.org/', new DateTimeImmutable('2026-10-09T00:00:00Z'));
        self::assertSame('a', $batch->facts[1]->groupKey);
        self::assertTrue($batch->groupCompleteness['a']);
        self::assertFalse($batch->groupCompleteness['b']);
        self::assertSame('b', $batch->resolutions[0]->groupKey);
    }

    public function testIdenticalObservationsRetainPluralEvidence(): void
    {
        $config = new DocumentFactConfig(['version' => 1, 'fields' => [[
            'id' => 'rate', 'selector' => '.rate', 'cardinality' => 'all',
        ]]]);
        $batch = (new DocumentFactParser())->parse($config, '<p class="rate">5%</p><p class="rate">5%</p>', 'sample', 'https://example.org/', new DateTimeImmutable('2026-10-09T00:00:00Z'));
        self::assertCount(1, $batch->facts);
        self::assertCount(2, $batch->facts[0]->allEvidence());
        self::assertTrue($batch->complete);
    }

    public function testNextLinkAndPageParameterTraversal(): void
    {
        $config = new CrawlConfig(['crawlVersion' => 1, 'start' => 'page', 'url' => 'https://example.org/list', 'allowedHosts' => ['example.org'], 'stages' => [
            'page' => ['pageParameter' => ['name' => 'page', 'last' => 2], 'records' => [
                'selector' => '.item', 'type' => 'item', 'namespace' => 'site', 'key' => 'id', 'fields' => ['id' => ['selector' => '.id']],
            ]],
        ]]);
        $outcome = (new CrawlRunner(static fn (string $url, string $mode): string => '<div class="item"><span class="id">' . (str_contains($url, 'page=2') ? '2' : '1') . '</span></div>'))->run($config);
        self::assertCount(2, $outcome->records);
        self::assertTrue($outcome->traversalComplete);
        self::assertSame(CoverageStatus::Unconfirmed, $outcome->coverage);
    }
}
