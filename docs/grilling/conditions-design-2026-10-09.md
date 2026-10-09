# Declarative conditions: investigation and proposal

Source inspection: Parser `FieldCheck` supports count, regex and equality for one field. `ObservedFact` requires a nonempty value; `ObservedFactBatch` has only batch-level `complete`. Banks `ConfiguredCreditFixedTerms` uses title text to select a profile and rejects a missing configured availability selector as drift. `ConfiguredDepositTableRates` skips rows without recognized currency and cells without parsed rate. Banks' `DepositRateParseStatus` distinguishes parsed, not applicable, no fixed rate, unverified, and source drift. These behaviors need explicit policy before they can be moved into declarations.

Proposed bounded condition AST, shared by document facts and crawl checks. Conditions evaluate over typed, scoped observations (`document`, `table`, `row`, `variant`, `record`, `crawl`); references cannot escape their scope except through explicit parent fields. Operators: `all`, `any`, `not`, `exists`, `equals`, `in`, `matches`, numeric `compare`. No arbitrary PHP, JavaScript, or expression strings. Validate field references, types, regex, and scope at config compilation. Evaluate with three outcomes: true, false, unknown. Missing or failed prerequisite yields unknown; unknown cannot silently become false or justify `not_applicable`.

Uses remain distinct: `when` decides whether extraction/assembly applies; `requiredWhen` checks expected presence; `assert` checks a relationship; `coverageCheck` evaluates run evidence. Each records condition ID, scope/group key, source evidence, and failure reason. A false `when` may yield `not_applicable` only if its inputs were observed successfully and the configuration explicitly requests that state. Otherwise preserve `absent` or `error`. Product meanings such as `no_fixed_rate` remain with Banks unless backed by an explicitly configured source assertion.

Illustrative shape, not accepted syntax:

```yaml
conditions:
  - id: fixed_rate_applicable
    scope: variant
    when: {all: [{exists: currency}, {in: {field: rateKind, values: [fixed]}}]}
    onFalse: not_applicable
    onUnknown: error
  - id: rate_required
    scope: variant
    requiredWhen: {equals: {field: rateKind, value: fixed}}
    require: nominalRate
```

Open decisions are in `15-round-pending.md`.

Boundary confirmed by user on 2026-10-09: the library remains universal. Names such as `rateKind`, `currency`, and `nominalRate` above are example configuration fields only; they are not built-in domain types or operators. The condition engine knows field values, types, scopes, evidence, and failures, not banks, loans, property, offers, or publication policy.

Q22–Q24 confirmed: unknown condition inputs always fail; `ObservedFact` remains a present value, while non-present states belong in separate generic batch results; every configured table skip needs positive source evidence.
