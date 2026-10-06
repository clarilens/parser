# Agent guide: using Clarilens Parser

Use this guide when generating parser configuration, integrating the library, or diagnosing an extraction result. Match the configuration to the document format and source before writing selectors.

## Choose the entry point

| Input | API | Result |
| --- | --- | --- |
| JSON file and a file source | `ParserFactory::fromConfigFile($path, $policy)` | `ConfiguredParser`; call `parse()` |
| JSON string from a database | `ParserConfig::fromJson($json)` and `DocumentParser` | Build `ParseRequest`, then call `parse()` |
| PHP objects | `new ParserConfig(...)` and `DocumentParser` | Build `ParseRequest`, then call `parse()` |

`ParserConfig::fromJson()` loads a config; `toJson()` and `toArray()` serialize it for storage. Serialized configs include `version: 1`; unknown versions are rejected. Register custom JSON rule factories and encoders with `RuleRegistry` before loading or saving their configs. Omit `source` when supplying content directly.

## Parse a saved document

The config file can contain:

```json
{
  "source": { "type": "file", "path": "page.html" },
  "parser": { "type": "HTML" },
  "rules": [
    { "type": "xpath", "field": "title", "required": true, "selector": "//h1" }
  ]
}
```

`path` is relative to the config file. Set `SourcePolicy::fileRoot` to a directory that contains the permitted source files:

```php
use Clarilens\Parser\Parser\ParserFactory;
use Clarilens\Parser\Source\SourcePolicy;

$configured = ParserFactory::fromConfigFile(
    __DIR__ . '/config.json',
    new SourcePolicy(__DIR__),
);
$outcome = $configured->parse();
foreach ($outcome->values as $value) {
    echo $value->field . ': ' . $value->value . PHP_EOL;
}
foreach ($outcome->failures as $failure) {
    echo $failure->field . ': ' . $failure->reason . PHP_EOL;
}
```

The factory reads file sources while constructing `ConfiguredParser`. A missing file, path outside `fileRoot`, or oversized file fails at that point. Web content is fetched when `parse()` runs. The factory does not set `DocumentOrigin`; supply one explicitly when you need observed facts.

## Parse JSON loaded from storage

```php
use Clarilens\Parser\Config\ParserConfig;
use Clarilens\Parser\Parser\DocumentParser;
use Clarilens\Parser\Parser\ParseRequest;
use Clarilens\Parser\Source\SourceLoader;
use Clarilens\Parser\Source\SourcePolicy;

$config = ParserConfig::fromJson($jsonFromDatabase);
$parser = new DocumentParser(new SourceLoader(
    new SourcePolicy(__DIR__ . '/allowed-files', ['example.com']),
    __DIR__,
));
$outcome = $parser->parse(new ParseRequest($config));
```

`SourceLoader` resolves file paths relative to its `baseDir` argument. Pass a known base directory when the JSON does not come from a file. To parse content already in memory, pass it as the second `ParseRequest` argument: `new ParseRequest($config, $content)`. This bypasses source acquisition, but document size and format checks still apply.

When the JSON config has no `source`, construct `new DocumentParser()` and pass content in `ParseRequest`. For PDF text extracted elsewhere, pass a list of page strings as `new ParseRequest($config, pdfPages: $pages)`. PDF pages are one-based in result locations.

For an existing acquisition pipeline, keep the fetched URL and timestamp with the result:

```php
use Clarilens\Parser\Result\DocumentOrigin;

$config = ParserConfig::fromJson($jsonWithoutSource);
$origin = new DocumentOrigin($fetchedUrl, $fetchedAt); // DateTimeImmutable
$outcome = (new DocumentParser())->parse(new ParseRequest($config, $fetchedHtml, origin: $origin));
```

## Select rules and source mode

| Need | Configuration |
| --- | --- |
| HTML or XML node or attribute | `xpath` with `selector` |
| HTML node by CSS | `css` with `selector`; choose `textMode: visible` to skip script/style text or `own` for direct text |
| HTML table row values | `table` with `rows`, `label`, `labelColumn`, `valueColumns` |
| Text from each PDF page | `pdfText` |
| Pattern in PDF text | `pdfRegex` with a PHP regex; capture group 1 is preferred |
| Nested JSON in HTML | `jsonScript` with XPath `selector` for a `<script>` and a `path` list; `*` traverses items |
| Structured HTML table | `tables` with `name` and CSS `selector`; result is a grid of cells with row, column, text, and DOM path |
| HTTP fetch | `source.type: web`, `render: http` |
| JavaScript-rendered HTML | `source.type: web`, `render: browser` |

An embedded JSON rule can traverse repeated items and preserve their JSON pointers:

```json
{
  "parser": { "type": "HTML" },
  "rules": [
    { "type": "jsonScript", "field": "title", "required": true,
      "selector": "//script[@id='__NEXT_DATA__']",
      "path": ["props", "pageProps", "data", "items", "*", "title"] }
  ],
  "checks": [{ "field": "title", "minCount": 1 }],
  "strict": true
}
```

Use uppercase `HTML`, `XML`, or `PDF` for `parser.type`. XML rules may use prefixes declared in `parser.namespaces`. PDF rules operate on embedded text; scanned pages need OCR before this library can extract text. Mark a rule `required: true` when absence should appear in `ParseOutcome::failures`. An optional missing rule contributes no value or failure.

For browser mode, `actions` accepts up to five click actions; `ready.selector` waits for a selector. `timeoutMs` must be between 1 and 30000. Browser mode supports HTML only. Authorize web hosts with `SourcePolicy::allowedWebHosts`; a URL in JSON does not grant its own network permission.

## Inspect and handle failures

`ParseOutcome::values`, `tables`, and `failures` are typed collections. Each extracted value has a `field`, a string `value`, optional `raw` wording, and a location: `nodePath` for DOM extraction, a JSON pointer for embedded JSON, or one-based `page` for PDF extraction. Pass `DocumentOrigin($url, $observedAt)` as `ParseRequest::origin` to carry source metadata into the outcome. `ParseOutcome::facts()` and `ObservedFactBatch::fromOutcome()` require this origin; the loader does not infer it from the source URL. A rule that finds no values produces a `RuleFailure` only when required. Add `checks` to the config for field count and content assertions; `strict: true` throws `SourceDriftException` on failures. Invalid configuration, unsafe sources, and malformed documents throw exceptions before an outcome is returned. Handle those exceptions at the application boundary. See [observed facts](OBSERVED_FACTS.md) for the versioned fact batch and a complete example.

Before relying on a selector, test it against a saved representative document. Cover a matching value, a missing required value, and any namespace or table column assumptions. Run `composer test`, `composer psalm`, and `composer cs:check` after changing library PHP code.
