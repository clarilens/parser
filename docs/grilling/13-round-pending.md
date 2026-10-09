# Round 13 — answered

Date: 2026-10-09. Previous decisions: `01`–`12` in this directory. Sources: `docs/feedback/banks-parser-0.0.1.md`, `docs/feedback/metrics-declarative-audit.md`, `docs/grilling/source-inspection-2026-10-09.md`, `docs/grilling/banks-source-inspection-2026-10-09.md`.

## Q13 — Scope of the first document-level change

Should its acceptance cover only named captures within HTML text, or also the other Banks P0 needs: source-derived transforms, table-cell facts, and declarative variant assembly?

Recommendation: include all four as acceptance criteria for the document-level change; separate internal milestones are fine. Otherwise the agreed requirement that Banks can remove bank-specific PHP parsers after upgrading is unmet.

Decision: yes, all four. User answer on 2026-10-09.

## Q14 — Fact result contract

Should declarative document extraction return `ObservedFactBatch` directly, while retaining `ParseOutcome` for low-level extraction, or should Banks assemble its own batch from a new intermediate result?

Recommendation: return an extended universal `ObservedFactBatch` with evidence, grouping, and explicit absent/error/not-applicable states. Keep product typing and publication in Banks.

Decision: yes, return the extended universal `ObservedFactBatch`; retain `ParseOutcome`. User answer on 2026-10-09.

## Q15 — Coverage proof for Metrics

If a source gives no expected count, can a project's result be considered publishable by Metrics when traversal is complete but coverage is unconfirmed? This is distinct from the library's `complete` and `coverage` fields.

Recommendation: Metrics requires coverage confirmation for automatic replacement of a project's snapshot; unconfirmed projects require explicit application review or an additional source-specific check. The parser still returns partial records and evidence.

Decision: agreed. User answer on 2026-10-09.
