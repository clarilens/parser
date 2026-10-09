# Round 21 — answered

Date: 2026-10-09. Prerequisites: Q34–Q36.

## Q37 — What is hashed

Should `configHash` identify canonical validated configuration content only, excluding runtime inputs such as start URL overrides, timestamps, credentials, and retry limits? Runtime inputs would be recorded separately when relevant.

Recommendation: yes. Otherwise the same declared behavior gets a different hash on each run; never include secrets in a serialized result.

Decision: yes. User answer on 2026-10-09.

## Q38 — Evidence aggregation boundary

If two identical normalized values come from different raw wording, should they merge into one resolved fact with multiple evidence items, or remain separate facts because the quoted wording differs?

Recommendation: merge within the same group and field only when the configuration declares that field single-valued. Keep every distinct quote and location; multi-valued fields retain separate facts.

Decision: yes. User answer on 2026-10-09.

## Q39 — Partial document batch

When one group fails but others resolve, should `ObservedFactBatch` retain the successful groups with `complete=false`, leaving any use of partial results to the consumer?

Recommendation: yes, matching the partial `CrawlOutcome` principle. The failing group and rule remain explicit.

Decision: yes. User answer on 2026-10-09.
