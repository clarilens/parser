# Round 20 — answered

Date: 2026-10-09. Prerequisites: Q31–Q33.

## Q34 — Scoped failure identity

Should every document-rule, condition, variant, and crawl failure carry a stable configured rule ID plus source location and group/record key when available, so two failures with the same message remain distinguishable?

Recommendation: yes. Keep messages explanatory; use IDs and scope keys for machine comparison.

Decision: yes. User answer on 2026-10-09.

## Q35 — Configuration provenance

Should document fact batches and crawl outcomes carry the exact config schema version and canonical content hash used for the run, while storage and activation of revisions remain with consumers?

Recommendation: yes. This makes offline diffs reproducible without moving revision management into the library.

Decision: yes. User answer on 2026-10-09.

## Q36 — Repeated observation

When the same group key and field are observed repeatedly with identical normalized values but different source locations, should the result retain all evidence locations, rather than arbitrarily keeping the first?

Recommendation: yes. A distinct value remains a conflict under Q27; identical values may be one resolved fact with multiple evidence items.

Decision: yes. User answer on 2026-10-09.
