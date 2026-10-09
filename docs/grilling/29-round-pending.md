# Round 29 — answered

Date: 2026-10-09. Prerequisites: Q45, Q48, Q58–Q60.

## Q61 — Limit exhaustion

If a crawl reaches `maxPages`, `maxClicks`, `maxItems`, or `maxDuration`, should it return a partial `CrawlOutcome` with a typed limit reason and retained observations rather than throw away the run?

Recommendation: yes. The exhausted limit means traversal is incomplete; application publication policy remains separate.

Decision: yes. User answer on 2026-10-09.

## Q62 — URL deduplication

If two paths in the graph discover the same URL with different parent keys, should the fetch be shared but the resulting observations be evaluated separately in each parent context, rather than silently assigning the URL to the first parent?

Recommendation: yes. Deduplicate network work by canonical URL and fetch mode, not record identity; detect any resulting key conflict explicitly.

Decision: yes. User answer on 2026-10-09.

## Q63 — Resume boundary

Must the first crawl release support persistent checkpoint/resume, or is a bounded restart from the beginning acceptable when the run fails, provided output is deterministic and partial results are available?

Recommendation: defer persistent resume to a later release. It requires storage and invalidation rules; the first release should be restartable with explicit limits and source evidence.

Decision: yes, defer persistent resume. User answer on 2026-10-09.
