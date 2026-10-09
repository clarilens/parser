# Round 22 — answered

Date: 2026-10-09. Prerequisites: Q37–Q39.

## Q40 — Multiple evidence API

Current `ObservedFact` has one `evidence: FactEvidence`. Q36 and Q38 require multiple evidence items for one resolved single-valued fact. Should v2 change it to a nonempty `evidence` list, with v1 decoding wrapping the old item, or keep the old property and store additional evidence elsewhere?

Recommendation: v2 nonempty evidence list. Provide a compatibility accessor or adapter for existing callers; v1 input wraps one item. An external schema version makes this structural change explicit.

Decision: yes. User answer on 2026-10-09.

## Q41 — Meaning of single-valued

If a field is declared single-valued within a group and two distinct normalized values appear, should the entire group become unresolved, or only that field while independent fields in the same group remain available?

Recommendation: only the conflicting field becomes unresolved; retain other fields and mark the batch incomplete. A configured group-level assertion may invalidate the whole group if needed.

Decision: yes. User answer on 2026-10-09.

## Q42 — Required absence

When a required field has no source match, should v2 emit one `absent` resolution plus a linked rule failure, rather than creating an `ObservedFact` with an empty value?

Recommendation: yes. This fits Q23 and keeps absence distinct from parse errors and not-applicable outcomes.

Decision: yes. User answer on 2026-10-09.
