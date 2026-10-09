# Clarilens Parser

Extract string values from saved HTML, XML, and text-bearing PDF documents, or acquire HTML and PDF over HTTP. Browser rendering is available for HTML pages that need JavaScript.

## Configuration

Each JSON config declares a `parser` and at least one `rules` or `tables` entry. Add `source` when the library should acquire the document. Rules have a type, field identifier, and required flag. Example:

```json
{
  "source": { "type": "file", "path": "page.html" },
  "parser": { "type": "HTML" },
  "rules": [
    { "type": "xpath", "field": "title", "required": true, "selector": "(//h1)[1]" }
  ]
}
```

Built-in rules:

- `xpath`: `selector`; works with HTML and XML, including attributes.
- `css`: `selector`; works with HTML. `css` and `xpath` support `textMode: all|visible|own`.
- `table`: `rows` XPath, `label`, zero-based `labelColumn`, and explicit zero-based `valueColumns`; works with HTML. Every matching row contributes one value.
- `pdfText`: one value per nonempty page.
- `pdfRegex`: `pattern` containing a PHP regular expression; the first capture group is used when present.
- `jsonScript`: `selector` selecting JSON-bearing `<script>` elements and a `path` list; `*` traverses array items. Values carry a JSON pointer location.

`tables` extracts HTML tables as coordinate-preserving grids: `[{"name":"rates","selector":"table.rates"}]`. `rowspan` and `colspan` are expanded while each cell retains its DOM path. `checks` can enforce `minCount`, `maxCount`, `pattern`, or `equals` for a field. Set `strict: true` to raise `SourceDriftException` when a required rule or check fails.

XML uses `parser.type: "XML"` and optional `parser.namespaces`, a prefix-to-URI map shared by its XPath rules. Unknown prefixes and DTDs are rejected. PDF extracts embedded text; OCR and table layout reconstruction are not supported.

For a web source use `{"type":"web","url":"https://...","render":"http"}`. Set `render` to `browser` for JavaScript-driven HTML; browser sources may include `actions` such as `[{"type":"click","selector":"#show"}]`, `ready: {"selector":"#content"}`, and `timeoutMs`. Web hosts must be allowed by `SourcePolicy`. File sources remain inside its configured root.

`ParserFactory::fromConfigFile($path, $policy, $registry)` returns a `ConfiguredParser`; call `$configured->parse()`. Its `parser` and `command` properties are available when needed. The `ParseOutcome` contains `values`, `tables`, and `failures` collections. Each value has `field`, string `value`, optional raw wording, and a DOM, JSON, or PDF location. A missing required rule adds a failure; optional rules may return nothing. Malformed configs, inaccessible sources, and invalid documents throw exceptions.

For already acquired content, omit `source` from the JSON config and use `new DocumentParser()` with `new ParseRequest($config, $content)`. PDF rules can receive pre-extracted page text through `new ParseRequest($config, pdfPages: $pages)`.

`ParserConfig::toJson()` serializes a config for database storage; `ParserConfig::fromJson()` restores it. The format is versioned. Custom rule types need a `RuleRegistry` encoder as well as a factory for round trips.

## Declarative facts

`DocumentFactConfig` is a separate version 1 declaration for evidence-backed facts. It compiles selectors, captures, typed conditions, and versioned registered operations before reading a document. `DocumentFactParser::parse($config, $savedHtml, $sourceId, $url, $observedAt)` returns `ObservedFactBatch` schema v2. Its `facts` contain present values and plural evidence; `resolutions` contain absent, not-applicable, conflict, and error states. The old `DocumentParser` API and v1 batch decoder remain available.

```json
{
  "version": 1,
  "fields": [{
    "id": "rate", "selector": "#terms", "type": "number", "required": true,
    "pattern": "/(?<value>\\d+,\\d+)\\s*%/u",
    "transforms": [{"op": "number", "decimal": ","}]
  }],
  "tables": [{"name": "rates", "selector": "table.rates", "field": "rate", "headerRow": 0, "keyColumn": 0}]
}
```

Captures use exact raw quotes and Unicode code-point offsets. `cardinality` is `one` (default), `first`, or `all`. `sourceMode` defaults to `raw`; explicit `visible` refuses hidden descendants when exact mapping cannot be established. `normalizeWhitespace` retains the raw span. Built-in transforms are `trim`, `number`, `enum`, and `url`. A registered transform uses `{"op":"registered","id":"...","version":"..."}` and must be supplied by an `OperationRegistry`. The registry owner must keep each implementation pure and keep a version's behavior stable. `SavedDocumentValidator` compares revisions against the same saved bytes; `FactDiff` reports added and removed group identities instead of matching table positions.

Closed PHP values use backed enums, including `FactState`, `CoverageStatus`, `CrawlFailureCode`, field scope/type, capture cardinality/mode, and transform and condition operators. Config JSON and serialized results keep their existing string values. `CrawlFailure` separates its enum `code` from optional free-text `detail`; `Condition::evaluate()` returns `ConditionResult::True`, `False`, or `Unknown`.

## Declarative crawl

`CrawlConfig` version 1 describes a bounded stage graph. `CrawlRunner::live($config)->run($config)` uses the guarded HTTP and browser clients. For offline replay, pass a fetch closure returning saved HTML or a list of browser frames. The runner returns partial `CrawlOutcome` records and failures, traversal state, coverage state, incomplete subtrees, and config provenance.

```json
{
  "crawlVersion": 1, "start": "floor", "url": "https://example.org/floor",
  "allowedHosts": ["example.org"], "limits": {"maxPages": 10, "maxClicks": 5, "maxItems": 100},
  "stages": {"floor": {
    "render": "browser", "repeat": {"click": ".more", "items": ".item"},
    "expectedCountPattern": "/(\\d+) items/",
    "records": {"selector": ".item", "type": "unit", "namespace": "site", "key": "id",
      "fields": {"id": {"selector": ".id"}}}
  }}
}
```

Stages may use `records.jsonAttribute` with `attribute`, `path`, and recursive `children`; `follow` with a field or safe URL template; `nextLink`; and bounded `pageParameter`. A source count or configured `priorCount` plus `priorThreshold` can confirm coverage. Completed traversal without a check remains coverage-unconfirmed. The application decides whether to publish a partial crawl.

Register a custom JSON rule type through `RuleRegistry::register($identifier, $factory)`, then pass the registry to `ParserFactory`. The factory constructs an `ExtractionRule`; the rule implements `extract(RuleContext)` and declares supported parser types. JSON can reference registered identifiers only.

## Agent guidance and checks

For task-oriented examples, security constraints, and an API decision guide, read [AI usage guide](docs/AI_USAGE.md). For versioned, evidence-backed extracted facts, read [observed facts](docs/OBSERVED_FACTS.md).

```bash
composer test
composer psalm
composer cs:check
```

The test suite uses local data and a fake browser client. For actual browser rendering, run `npm install` and `npm run browser:install` in the library directory, including when it is installed under `vendor/`. Composer does not install the Node dependencies or provide the generated browser script. `npm install` builds `dist/render-page.js`; after changing `scripts/**/*.ts`, run `npm run build`. HTTP redirects are rejected. Browser rendering restricts hosts, blocks downloads and WebSockets, and runs Chromium with its sandbox enabled.
