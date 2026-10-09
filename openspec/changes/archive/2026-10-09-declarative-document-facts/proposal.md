## Why

Consumers currently supplement one-document extraction with bespoke PHP to capture phrases, derive values, project table cells, and assemble related variants. The agreed first delivery makes these operations declarative while preserving the library's domain independence and existing low-level parser.

## What Changes

- Add named HTML text captures with exact quotes and source offsets, safe typed transforms, table-cell projection, and grouped variant assembly.
- Add a bounded typed condition language for applicability and checks; make absence, not-applicability, conflicts, and failures explicit.
- Extend `ObservedFactBatch` to schema v2 with multiple evidence items, scoped resolutions, and configuration provenance. Preserve v1 reads and existing PHP access during the first release.
- Add compiled configuration validation and saved-document fact diffs.

## Capabilities

### New Capabilities

- `document-fact-assembly`: Declarative capture, transformation, table projection, condition evaluation, variant assembly, and evidence-backed fact results.

### Modified Capabilities

None. No existing OpenSpec capabilities exist in this repository.

## Impact

Touches parser configuration, extraction, table projection, result serialization, and validation. `ParseOutcome` remains available. Banks can consume universal facts and map them to its own product types; no banking rules or revision storage move into this library. PDF geometry and OCR are outside this change. Decisions: `docs/grilling/01`–`30` and `docs/feedback/banks-parser-0.0.1.md`.
