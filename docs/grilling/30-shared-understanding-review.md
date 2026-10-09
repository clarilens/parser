# Shared understanding review — confirmed

Date: 2026-10-09. This file indexes the complete interview: numbered decisions Q1–Q63 in `01`–`29` files in this directory. Each question, recommendation, and user answer is preserved there. Consumer evidence is in `docs/feedback/` and source-inspection notes here.

## Delivery

Two separate changes: document-level declarative extraction first, then declarative crawl. Both must remain universal; no Banks or Metrics domain types, calculations, publication rules, storage, or scheduling enter Parser. Existing `ParseOutcome` and simple one-document API remain available. The document change adds named HTML captures, safe source-derived transformations, table-cell facts, variant assembly, and a shared typed condition core. The crawl change handles nested JSON, bounded browser repetition, records, traversal, and coverage. The crawl config schema stabilizes after both Metrics scenarios pass. PDF geometry/OCR and persistent resume are deferred.

## Results and evidence

`ObservedFactBatch` v2 extends the universal fact result with scoped absent/not-applicable/error resolutions, rule failures, grouping, config identity, and multiple evidence items; v1 remains readable and the first release preserves existing PHP access. `ObservedFact` still denotes a present value. Partial successful groups survive failures with explicit completeness at group and batch level. Exact quotes, source locations, offsets, raw inputs, and operation provenance support audit. `CrawlOutcome` retains records from successful branches, typed failures, traversal completion, coverage evidence, and incomplete subtrees. Applications decide publication; Metrics requires confirmed coverage for automatic project snapshot replacement.

## Declarative semantics

Conditions form a small typed, scoped tree. Unknown inputs fail; false conditions can establish not-applicability only with explicit source-backed evidence. Configured table skips require positive source evidence. Single-valued fact conflicts leave that field unresolved; identical observations retain all evidence. Limits and exhausted retries return partial outcomes. Fetch deduplication must preserve each parent context. Registered versioned custom operations are pure; compilation rejects missing or mismatched versions. Config content hash and operation registry fingerprint are separate.

## Acceptance

Offline saved HTML cases cover Banks text credit and multi-variant deposit table, with universal expected facts and failures; a separate consumer check verifies Banks typing. Offline nested JSON and browser `loadMore` sequences cover Metrics, including partial failure and coverage. Live counts are observations, not fixed assertions. Exact capture offsets are Unicode code points into raw concatenated DOM text; declared normalization must retain a reversible mapping. Unsupported mapping fails explicitly.

## Confirmation request

Please confirm this shared understanding before changing implementation or formalizing the plan. Any correction should identify the affected decision number; the interview records will be updated in place.

Confirmed by user on 2026-10-09. Authorized next step: formal specifications.
