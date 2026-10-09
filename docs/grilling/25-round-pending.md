# Round 25 — answered

Date: 2026-10-09. Prerequisites: Q46–Q48.

## Q49 — Extensibility boundary

When a consumer needs a transformation or condition absent from the built-in declarative set, should the library accept registered, versioned named operations implemented in PHP, or require a new library release for every operation?

Recommendation: allow registered named operations, but mark configs using them as dependent on that registry and reject missing/version-mismatched operations at compile time. Pure JSON configs remain portable only when they use built-ins.

Decision: yes. User answer on 2026-10-09.

## Q50 — Runtime operation safety

If custom operations are allowed, may they perform network I/O or read application services during evaluation, or must they be pure over supplied values and context?

Recommendation: require pure operations. Fetching is an explicit crawl stage with separate host, time, and size limits.

Decision: agreed. User answer on 2026-10-09.

## Q51 — Operation provenance

Should each normalized value retain the raw input and the declared transformation chain (operation IDs and versions), with exact source evidence, so a later config change can explain a fact diff?

Recommendation: yes. Store compact provenance, not a full execution trace; failures identify the failing operation.

Decision: yes. User answer on 2026-10-09.
