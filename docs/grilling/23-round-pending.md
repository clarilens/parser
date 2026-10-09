# Round 23 — answered

Date: 2026-10-09. Prerequisites: Q40–Q42.

## Q43 — Backward-compatible PHP API

Should the existing `ObservedFact` constructor and `$fact->evidence` property remain usable through the first document release, while new multiple-evidence access is additive, even though v2 serialization uses a list?

Recommendation: yes. A hard PHP API break in the first release would force both consumers to migrate before they can try the new declarations. Internal storage can change behind compatibility methods.

Decision: yes. User answer on 2026-10-09.

## Q44 — Completeness aggregation

If a document contains several independent groups and one required field fails in one group, should batch `complete=false` while each group also reports its own resolved/unresolved state, so consumers can use only sound groups?

Recommendation: yes. This makes Q39 and Q41 actionable without guessing from the batch flag.

Decision: yes. User answer on 2026-10-09.

## Q45 — Partial crawl parent

If a parent project record is extracted but one discovered child page fails, should the parent record remain in `CrawlOutcome` with an incomplete subtree marker and the child's failure, rather than be dropped?

Recommendation: yes. Preserve observations while making the incomplete subtree explicit; the consumer decides whether to replace its snapshot.

Decision: yes. User answer on 2026-10-09.
