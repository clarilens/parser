## 1. Configuration and conditions

- [x] 1.1 Add versioned document-fact declarations and compile-time validation for fields, scopes, selectors, cardinality, and types; verify invalid saved configs fail before extraction.
- [x] 1.2 Implement the shared typed condition core with true/false/unknown evaluation and scoped diagnostics; verify missing inputs fail instead of becoming false.
- [x] 1.3 Add pure registered operations with version pinning, canonical config hash, and registry fingerprint; verify missing or mismatched operations reject compilation.

## 2. Extraction and assembly

- [x] 2.1 Implement named raw-DOM and explicit visible-text captures with exact quotes, Unicode offsets, and reversible normalization mapping; verify ambiguous and unmappable cases fail.
- [x] 2.2 Implement bounded typed transforms with raw-input and operation provenance; verify locale numbers, units, and ambiguous input cases.
- [x] 2.3 Project table cells with headers, coordinates, merged origins, and evidenced skip rules; verify source drift produces row-scoped failures.
- [x] 2.4 Assemble declared variants and stable group keys; verify identical observations retain all evidence and conflicting values leave only their field unresolved.

## 3. Results and acceptance

- [x] 3.1 Add schema v2 fact batches with scoped resolutions, group completeness, plural evidence, and v1/PHP compatibility; verify round trips and existing caller access.
- [x] 3.2 Add saved-document validation and identity-aware fact diff; verify key changes are reported as identity changes.
- [x] 3.3 Add offline text-credit and multi-variant-table fixtures with expected universal batches and failure evidence; verify a separate Banks consumer mapping check reaches its existing product types.
- [x] 3.4 Run `composer test`, `composer psalm`, and `composer cs:check`; fix introduced failures and record any pre-existing failure scope.
