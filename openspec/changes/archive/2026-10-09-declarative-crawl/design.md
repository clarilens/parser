## Context

See `proposal.md` and `specs/declarative-crawl/spec.md`. The existing parser loads and parses one source. Metrics currently traverses nested `child_projects` and page links in application PHP, while an Arm Construct page exposes a bounded `loadMore` action. The document-facts change supplies the typed condition core and operation registry. This change adds orchestration without making the library responsible for consumer snapshots.

## Goals / Non-Goals

**Goals:** Compile a safe bounded stage graph; preserve source/parent provenance; report partial traversal and coverage; replay representative cases offline.

**Non-Goals:** Publication, scheduling, database snapshots, asset storage, persistent checkpoint/resume, or site-specific domain types.

## Decisions

1. **Separate `CrawlConfig` from one-document config.** Versioned stages describe fetch, extract, record selection, follow, repeat, and checks. Compile graph references, typed field paths, URL templates, limits, and allowlists before any request. Keep the document parser callable alone. Alternative: fold graph keys into `ParserConfig`; this would complicate simple extraction and make versioning harder.
2. **Represent records as typed flat entries with parent keys.** A stage emits scoped observations, then records with declared namespaced keys. Nested JSON traversal and DOM container selection use the same parent context. Duplicate keys across parent contexts are conflicts. Alternative: parallel arrays or implicit tree positions; missing values and duplicate IDs make these unsafe.
3. **Keep source acquisition and record evaluation distinct.** Canonical URL plus fetch mode can deduplicate network work, but the same response is evaluated separately for each parent context. Generated URLs and redirects pass the existing host/private-network policy after substitution. Alternative: first-parent ownership of a URL; that silently loses relationships.
4. **Model browser repeat as a bounded state machine.** Every iteration evaluates action availability, progress predicate, terminal condition, count, and limits. No progress with an available action fails; a terminal action with a source count mismatch fails coverage. Return captured records and explicit reason. Alternative: fixed click count or unbounded loop; neither proves completion.
5. **Keep traversal and coverage separate.** The engine records discovered/processed sources, distinct items, source counts, skips, and reasons. Source count checks and explicitly configured prior-run thresholds can confirm coverage; terminal pagination alone establishes traversal completion only. `CrawlOutcome` can be partial, with incomplete subtrees. The consumer decides whether a complete project is publishable and may keep a failed project's previous snapshot.
6. **Reuse condition and provenance infrastructure.** Crawl conditions use the document change's typed core but expose crawl-specific references and actions. Include config content hash and operation registry fingerprint in outcomes. Alternative: a separate crawl expression syntax; it would drift and complicate saved config validation.
7. **Develop against replayable source sequences.** Save sanitized source bytes and browser update steps for Metrum and Arm Construct contract tests. Live counts serve as observations, not stable assertions. Persistent resume is deferred; bounded restart from the beginning is sufficient for this release.

## Risks / Trade-offs

- [Browser updates are hard to replay faithfully] → Define a deterministic sequence adapter and compare DOM/item counts at each step; retain a separate live smoke check.
- [Generated URLs can escape source restrictions] → Validate every concrete URL and redirect at fetch time, in addition to compile-time template checks.
- [A partial run may be mistaken for full coverage] → Distinct traversal, coverage, branch, and subtree states plus typed failure reasons.
- [Full restart can repeat costly requests] → Use bounded limits and in-run fetch deduplication; defer persistent checkpoint design until evidence shows need.

## Migration Plan

Add crawl API alongside the document parser. Stabilize `CrawlConfig` v1 only after both offline Metrics scenarios pass. Consumers migrate one source definition at a time and compare records and coverage with existing application traversal. Roll back by retaining prior application traversal and stored snapshots; the library does not mutate published data.
