# Banks source inspection, 2026-10-09

Read-only inspection of `/Users/farkhatsakibaev/Projects/Banks`:

- `backend/composer.json` requires `clarilens/parser:^0.0.1`.
- `backend/lib/HTML/Adapter/HtmlFactExtractor.php` maps `ExtractedValue` and table cells into project-owned `HtmlFact` objects, preserving field, value, wording, and DOM/JSON location.
- `backend/lib/PDF/Adapter/PdfFactExtractor.php` maps PDF values into project-owned `PdfFact` objects and requires a page location.
- `backend/packages/market-acquisition/src/Infrastructure/Adapter/Acquisition/BankSite/CreditTermWording.php` checks configured wording against a `page.text` fact, then matches the original source text and returns the full matched phrase. It uses `preg_match(..., $matches)[0]`, not named capture groups.
- Parser `XPathRule` selects nodes; `DomValue` returns normalized node text with raw node text and `NodeLocation`. It has no configured substring capture with an exact matched quote and offset.
- Banks `SourceFact` includes application fields such as URL, observation time, group key, and applicability. Those are not intrinsic to a single-document extraction rule.

Interpretation: configured text capture is a distinct library capability candidate. Banks' canonical fact DTO and product applicability belong to its application boundary unless separately chosen for the parser API.
