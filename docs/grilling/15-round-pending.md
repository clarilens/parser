# Round 15 — answered

Date: 2026-10-09. See `conditions-design-2026-10-09.md` for evidence and proposal.

## Q19 — Unknown condition inputs

If a condition needs `rateKind` but extraction did not find it or failed, should the result be an explicit condition error rather than treating the condition as false and marking the rate not applicable?

Recommendation: yes. Use true/false/unknown evaluation; unknown is an error unless the declaration explicitly allows a narrower fallback.

Decision: yes. User answer on 2026-10-09.

## Q20 — Condition scope

Should version 1 use a bounded, typed condition tree shared across document fact assembly and crawl coverage, with scope-specific operators, or separate condition languages for each change?

Recommendation: one small shared core (`all`, `any`, `not`, `exists`, `equals`, `in`, `matches`, numeric comparison), with separate document and crawl actions. Ship document conditions first; add crawl-specific references later.

Decision: yes. User answer on 2026-10-09.

## Q21 — Skipped table rows

If a configured table row has an unrecognized currency or an unparseable rate, should it be recorded as a rule failure tied to that row rather than silently skipped? Explicitly configured headings or separators may be skipped.

Recommendation: yes. This prevents source drift from masquerading as fewer variants.

Decision: yes. User answer on 2026-10-09.
