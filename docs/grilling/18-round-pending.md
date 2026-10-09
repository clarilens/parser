# Round 18 — answered

Date: 2026-10-09. Prerequisites: Q25–Q27 and separate delivery decision Q12.

## Q28 — Delivery dependency

May the document-level change introduce the shared typed condition core and fact batch v2 before the crawl change, with crawl later reusing that core?

Recommendation: yes. The document change owns the core; the crawl change adds scope-specific references and coverage actions without a second condition language.

Decision: yes. User answer on 2026-10-09.

## Q29 — Banks acceptance fixture

For the first document-level release, must acceptance include saved source documents for one text credit case and one multi-variant deposit table, with an exact expected universal fact batch and failure evidence, before Banks maps them to its product types?

Recommendation: yes. Keep the fixtures and expected output free of Bank domain classes; a separate consumer check can verify the Banks mapping.

Decision: yes. User answer on 2026-10-09.

## Q30 — Metrics acceptance fixture

For the first crawl release, must offline contract tests cover both nested JSON traversal and browser `loadMore` response sequence, including partial failure and coverage evidence, before relying on live-site counts?

Recommendation: yes. Existing Metrics fixtures do not capture the browser update sequence; capture or construct a reproducible sequence as part of acceptance.

Decision: yes. User answer on 2026-10-09.
