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

Register a custom JSON rule type through `RuleRegistry::register($identifier, $factory)`, then pass the registry to `ParserFactory`. The factory constructs an `ExtractionRule`; the rule implements `extract(RuleContext)` and declares supported parser types. JSON can reference registered identifiers only.

## Agent guidance and checks

For task-oriented examples, security constraints, and an API decision guide, read [AI usage guide](docs/AI_USAGE.md). For versioned, evidence-backed extracted facts, read [observed facts](docs/OBSERVED_FACTS.md).

```bash
composer test
composer psalm
composer cs:check
```

The test suite uses local data and a fake browser client. For actual browser rendering, run `npm install` and `npm run browser:install` in the library directory, including when it is installed under `vendor/`. Composer does not install the Node dependencies or provide the generated browser script. `npm install` builds `dist/render-page.js`; after changing `scripts/**/*.ts`, run `npm run build`. HTTP redirects are rejected. Browser rendering restricts hosts, blocks downloads and WebSockets, and runs Chromium with its sandbox enabled.
