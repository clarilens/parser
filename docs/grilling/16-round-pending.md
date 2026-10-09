# Round 16 — answered

Date: 2026-10-09. Prerequisites: Q19–Q21; universal library boundary confirmed.

## Q22 — Condition unknown policy

Should configuration be allowed to turn an unknown condition into false, or must unknown always produce an explicit failure?

Recommendation: unknown always fails. Any fallback must be expressed as an explicit `any` branch over observable evidence, keeping the failure detectable.

Decision: yes. Unknown always fails. User answer on 2026-10-09.

## Q23 — Fact states in the universal API

Should `ObservedFact` represent only present values, with separate scoped outcomes for absent, not-applicable, and error, or should one fact object carry all states with an optional value?

Recommendation: keep `ObservedFact` as an observed value; add separate generic resolution entries to `ObservedFactBatch`. This preserves existing serialization and avoids calling an absence an observation.

Decision: yes. User answer on 2026-10-09.

## Q24 — Explicit skip evidence

For configured non-data rows or cells, must a skip rule identify source evidence (text, marker, or structural selector) rather than relying only on row indexes?

Recommendation: yes. Indexes may locate candidates, but a positive source predicate should justify skipping them, so table shifts are visible.

Decision: yes. User answer on 2026-10-09.
