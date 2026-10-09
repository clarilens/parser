## Why

The parser handles one document, while consumers still write site-specific PHP to traverse nested JSON, follow pages, repeat browser actions, and determine whether a crawl covered its source. A bounded declarative crawl can return reusable records and evidence without taking over application publication policy.

## What Changes

- Add a compiled versioned crawl graph with typed stages, scoped records, parent links, and safe URL construction.
- Support nested JSON from HTML attributes and bounded browser `loadMore` repetition, plus generic pagination.
- Return partial `CrawlOutcome` records, typed failures, traversal completion, coverage checks, incomplete subtrees, and config provenance.
- Reuse the document change's condition core, keeping coverage actions distinct from fact assembly.

## Capabilities

### New Capabilities

- `declarative-crawl`: Bounded source traversal, structured records, coverage evidence, and partial crawl outcome.

### Modified Capabilities

None. No existing OpenSpec capabilities exist in this repository.

## Impact

Touches source loading, browser execution, JSON extraction, configuration validation, and result types. Existing one-document APIs remain usable. Consumers retain scheduling, snapshot publication, revision storage, and domain typing. First-release acceptance includes offline Metrum nested JSON and Arm Construct `loadMore` scenarios; persistent resume is deferred. Decisions: `docs/grilling/01`–`30` and `docs/feedback/metrics-declarative-audit.md`.
