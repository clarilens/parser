# Round 17 — answered

Date: 2026-10-09. Prerequisites: Q22–Q24.

## Q25 — Fact batch serialization

Should adding scoped absence/not-applicable/error results and rule failures advance `ObservedFactBatch` serialization to schema version 2, while version 1 remains readable?

Recommendation: yes. Keep v1 decoding for existing consumers; write v2 only when the new result contract is used. Do not silently reinterpret v1 `complete`.

Decision: yes. User answer on 2026-10-09.

## Q26 — Evidence for not-applicable

Must a `not_applicable` resolution include the condition ID and the source locations of observations that made its condition false?

Recommendation: yes. A source-backed false condition is the proof; absent source data yields an error, per Q22.

Decision: yes. User answer on 2026-10-09.

## Q27 — Ambiguous variant assembly

If two source rows or axes map to the same configured variant group key but disagree on a required field, should the batch retain both source observations and report a conflict, without choosing one value?

Recommendation: yes. Preserve evidence and mark that variant unresolved. Identical duplicate observations may be deduplicated with both locations retained.

Decision: yes. User answer on 2026-10-09.
