# Banks feedback on clarilens/parser 0.0.1

Source: full feedback supplied by the user on 2026-10-09. Preserve these requirements for planning; verify implementation details against Banks and parser source before coding.

## Correction to earlier short feedback

The library already has `ObservedFactBatch`, `ObservedFact`, `FactEvidence`, `groupKey`, HTML/JSON/PDF locations, and serialization. Banks' `BankFactBatchBuilder.php` creates a parallel contract because the automatic library path does not assemble all needed fact relationships.

## Existing strengths

CSS, XPath, script JSON, PDF text and regex, HTML tables with rowspan/colspan coordinates, required rules and checks, strict drift handling, raw wording and location, versioned configuration, HTTP and browser sources with host restrictions. The extraction base should be retained.

## P0 gaps

1. Declarative named captures within selected HTML text, each with value, exact quote, and location. Banks currently re-searches `page.text` in `CreditTermWording.php`.
2. Source-derived values and limited safe transforms. The credit profile currently stores known rate and term values as constants, forcing manual updates when the source changes.
3. Declarative table-cell facts with row group key, header relationship, coordinates, and original cell evidence through rowspan/colspan. `ParseOutcome` currently turns only values into facts.
4. Declarative assembly of variants from rows, headers, term columns, and channels, with required fields. Banks currently implements this in `ConfiguredDepositTableRates.php` and `ConfiguredCreditFixedTerms.php`.

## P1 gaps

1. Cross-field conditions, mutual requirements, units, and ranges, with precise failures.
2. A fact result that separates document completeness from source crawl completeness and carries rule failures and configuration identity.
3. A standard fetch → parse → facts path that supplies URL and observation time without manual `DocumentOrigin` assembly.

## P2 gap

PDF tables, phrase geometry, and OCR need a separate decision.

## API requirements

- Make `ObservedFactBatch` the convenient fact result while preserving low-level `ParseOutcome`.
- Support named captures and safe parsing of NBSP/space, thousand separators, decimal comma, percent, currency, and day/month terms. Preserve raw wording and reject ambiguity.
- Project tables declaratively without changing `TableGrid`: headers, axes, rows, empty and merged cells, variant key.
- Distinguish absent, error, and explicit not-applicable values.
- Attach failures to rule, group, and source location.
- Validate config on saved documents before activation and produce a comparable fact diff. Revision storage and activation may stay in Banks.

## Boundary and acceptance

Do not move Offer types, financial calculations, admission, editorial publication, whole bank catalog crawl, or API freshness rules into the parser. The parser emits configured observed facts and evidence; Banks types them and decides what to publish. Prove P0 with one real text credit source and one multi-variant deposit table. Banks must receive the same typed Offer without a bank-specific PHP parser class.
