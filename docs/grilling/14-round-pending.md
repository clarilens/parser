# Round 14 — answered

Date: 2026-10-09. Prerequisites: decisions Q13–Q15 in `13-round-pending.md`.

## Q16 — Fact absence states

When a configured fact is absent, who declares whether it is legitimately not applicable: the declarative parser configuration or Banks after receiving an absent fact?

Recommendation: configuration can declare explicit applicability conditions and produce not-applicable with evidence; otherwise absence remains absence. Banks may apply product-specific policy afterward.

Decision: agreed. User answer on 2026-10-09; requested investigation of declarative conditions.

## Q17 — Variant identity

For table variants, should the parser generate a stable group key from configured source fields and fail on collisions, or expose raw row/column coordinates and let Banks assign identity?

Recommendation: configured source fields define the stable group key; coordinates remain evidence, and collisions fail explicitly.

Decision: agreed. User answer on 2026-10-09.

## Q18 — No source count

What generic evidence may confirm coverage when a source has no expected item count: only a declared terminal condition (last page, absent next link, exhausted button), or also a comparison with a prior successful crawl?

Recommendation: terminal condition confirms traversal only. A prior crawl comparison may be an explicit coverage check with a configured threshold, but never silently establishes coverage.

Decision: agreed. User answer on 2026-10-09.
