# Observed facts

A `ParseOutcome` contains extracted strings. To preserve provenance, supply `DocumentOrigin($url, $observedAt)` in `ParseRequest`:

```php
use Clarilens\Parser\Config\ParserConfig;
use Clarilens\Parser\Parser\DocumentParser;
use Clarilens\Parser\Parser\ParseRequest;
use Clarilens\Parser\Result\DocumentOrigin;
use Clarilens\Parser\Result\ObservedFactBatch;

$config = ParserConfig::fromJson([
    'parser' => ['type' => 'HTML'],
    'rules' => [['type' => 'xpath', 'field' => 'title', 'required' => true, 'selector' => '//h1']],
]);
$html = '<h1>Example item</h1>';
$origin = new DocumentOrigin(
    'https://example.com/item',
    new DateTimeImmutable('2026-10-07T12:00:00Z'),
);
$outcome = (new DocumentParser())->parse(
    new ParseRequest($config, $html, origin: $origin),
);
$batch = ObservedFactBatch::fromOutcome(
    $outcome,
    'catalog',
    'source-v1',
    'parser-v1',
);
$json = $batch->toJson();
```

The batch has `schemaVersion: 1`, source and parser versions, observation time, a `complete` flag, and a list of facts. `fromJson()` restores the batch and rejects unknown schema versions. `complete` defaults to `false`; the caller must determine source coverage and set it explicitly. Rule failures do not set this flag.

Each fact has a field, extracted value, and evidence containing source URL, observation time, source wording, and optional DOM path, JSON pointer, or one-based PDF page. The evidence can also carry optional source ID and applicability. Repeated fields are valid. A caller can assign `groupKey` when several facts belong to one source item; `fromOutcome()` does not infer groups.

`DocumentOrigin` is required for `ParseOutcome::facts()` and `ObservedFactBatch::fromOutcome()`. `ParserFactory::fromConfigFile()` does not add it automatically, even for a configured web source. Supply an origin when constructing `ParseRequest` directly.

The parser does not interpret facts as products, prices, offers, or published records. Applications map observed facts into their own models.
