# Round 27 — answered

Date: 2026-10-09. Prerequisites: Q31 and Q52–Q54.

## Q55 — Matching normalized text

Some source wording differs only by NBSP or spacing. Should named captures be allowed to match against normalized text while still returning an exact raw-source quote and raw-text offset through a maintained character mapping, or should matching be raw-only in version 1?

Recommendation: support a small declared normalization set with reversible offset mapping. Reject a capture if the normalized match cannot map to one contiguous raw span. Preserve raw quote and normalized value separately.

Decision: yes. User answer on 2026-10-09.

## Q56 — Capture cardinality

If a named capture finds two matches within its selected scope, should the declaration specify `one`, `first`, or `all`, with `one` failing on ambiguity rather than silently choosing the first?

Recommendation: yes. Default to `one`; `first` must be explicit and deterministic in DOM order.

Decision: yes. User answer on 2026-10-09.

## Q57 — Source text across hidden nodes

Should the capture scope use DOM text content including hidden nodes, or rendered visible text? These can differ, especially on responsive pages.

Recommendation: make the source explicit per rule: raw DOM text or browser-visible text. Default to raw DOM text for reproducibility; visible text requires browser-rendered source and corresponding provenance.

Decision: yes. User answer on 2026-10-09.
