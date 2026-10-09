# Round 19 — answered

Date: 2026-10-09. Prerequisites: Q28–Q30.

## Q31 — Text capture offset

For named HTML text captures, should the offset index the exact raw text of the selected DOM node, with any normalized value stored separately? In nested elements, the selected text may be assembled from multiple text nodes.

Recommendation: yes, but define the searchable text as the selected node's concatenated raw descendant text and state the offset unit (Unicode code points). Retain DOM node path plus exact quote; if a quote spans text nodes, record that span explicitly rather than inventing one child-node location.

Decision: yes. User answer on 2026-10-09.

## Q32 — Batch completion semantics

Should `ObservedFactBatch.complete` mean that every declared document rule and condition reached a resolved result, while the separate resolution entries still report absent or not-applicable outcomes? A required absence or error would make it false.

Recommendation: yes. Keep this strictly document-level; crawl traversal and coverage have their own states.

Decision: yes. User answer on 2026-10-09.

## Q33 — Browser repeat terminal state

If `loadMore` disappears while the observed item count remains below a source-declared expected count, should the branch fail coverage even though the repeat loop ended normally?

Recommendation: yes. Record traversal completion for that loop and failed coverage with expected and observed counts; the containing branch is incomplete for publication decisions.

Decision: yes. User answer on 2026-10-09.
