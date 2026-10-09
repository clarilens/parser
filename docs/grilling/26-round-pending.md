# Round 26 — answered

Date: 2026-10-09. Prerequisites: Q49–Q51.

## Q52 — Compile artifact identity

Should compilation resolve and pin every registered operation's ID and version in an immutable execution plan, then include that registry fingerprint separately from `configHash` in the result?

Recommendation: yes. The config hash identifies declared content; the registry fingerprint identifies the code semantics that executed it.

Decision: yes. User answer on 2026-10-09.

## Q53 — Replay without registry

If a saved config refers to an operation unavailable in the current runtime, should dry-run and activation fail at compile time rather than skip that operation or substitute a newer version?

Recommendation: yes. Explicit migration or revalidation is required for a new operation version.

Decision: yes. User answer on 2026-10-09.

## Q54 — Stable group key across revisions

If the configured fields used to construct a variant group key change between config revisions, should the fact diff report old and new keys as identity changes, rather than automatically matching by row position or similar values?

Recommendation: yes. Source positions are evidence, not identity; a consumer may supply a separate migration mapping if needed.

Decision: yes. User answer on 2026-10-09.
