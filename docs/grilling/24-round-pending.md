# Round 24 — answered

Date: 2026-10-09. Prerequisites: Q43–Q45.

## Q46 — Optional absence

When an optional declared field is absent, should its scoped result be `absent` without a failure and leave group/batch completeness unchanged?

Recommendation: yes. Completeness means declared checks ran and required obligations were met, not that every optional field was present.

Decision: yes. User answer on 2026-10-09.

## Q47 — Condition evaluation trace

Should a failed or unknown condition report which input/reference caused the outcome, in addition to the condition ID and source evidence, without serializing every intermediate successful operation?

Recommendation: yes. This gives actionable diagnostics while keeping results bounded.

Decision: yes. User answer on 2026-10-09.

## Q48 — Crawl retry exhaustion

When a bounded fetch or browser retry policy is exhausted, should the crawl report a source failure for that branch with attempt count and final error category, preserving sibling records?

Recommendation: yes. Retry policy is generic source behavior; the library must not infer whether the consumer publishes siblings.

Decision: yes. User answer on 2026-10-09.
