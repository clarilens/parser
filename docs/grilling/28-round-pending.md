# Round 28 — answered

Date: 2026-10-09. Prerequisites: Q55–Q57.

## Q58 — Unsupported text mapping

If browser-visible text cannot be mapped back to a stable DOM/raw-source span, should the capture fail explicitly instead of emitting an approximate quote or offset?

Recommendation: yes. Exact evidence was selected in Q10 and Q31; an approximation would violate that contract.

Decision: yes. User answer on 2026-10-09.

## Q59 — PDF boundary

Should the first document-level release keep PDF extraction at its existing text/page evidence level, leaving PDF table geometry, phrase offsets, and OCR for a separate change?

Recommendation: yes. The accepted Banks fixture cases are HTML; adding PDF geometry would expand the first release substantially.

Decision: yes. User answer on 2026-10-09.

## Q60 — Live capture portability

Should offline capture fixtures include the source bytes and parser/browser rendering mode needed to reproduce a match, while excluding cookies and credentials?

Recommendation: yes. This permits deterministic acceptance without leaking runtime secrets.

Decision: yes. User answer on 2026-10-09.
