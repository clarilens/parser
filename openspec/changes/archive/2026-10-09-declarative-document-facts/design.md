## Context

See `proposal.md` and `specs/document-fact-assembly/spec.md`. Today `DocumentParser` returns `ParseOutcome`; `ObservedFact` holds one `FactEvidence`; `ObservedFactBatch` schema v1 has batch-level `complete`. `FieldCheck` handles only single-field count, pattern, and equality. Table grids already expose cell structure. Banks' consumer code currently performs phrase searching, table variant assembly, and product typing. The design must preserve existing one-document APIs and prevent consumer domain vocabulary from entering the library.

## Goals / Non-Goals

**Goals:** Compile reusable declarations before execution; expose precise evidence and typed failures; make a universal batch convenient for consumers; support deterministic saved-document validation and diffs.

**Non-Goals:** Product typing, financial calculations, source revision storage/activation, editorial decisions, PDF OCR/geometry, and a general-purpose expression evaluator.

## Decisions

1. **Compile declarations to a typed plan.** Resolve rule IDs, field references, scopes, cardinality, regexes, transforms, condition operators, and custom operation versions before reading a document. Alternative: interpret free-form JSON at each step. Compilation catches errors before side effects and provides stable failure IDs. Canonical config content hash excludes runtime inputs; a separate registry fingerprint pins executable semantics.
2. **Use one small condition AST across both changes.** `all`, `any`, `not`, existence, equality, membership, regex, and numeric comparison operate on typed scoped values. Evaluation returns true/false/unknown; missing or failed inputs become unknown and fail. Document actions are `when`, `requiredWhen`, and assertions. Alternative: ad hoc PHP callbacks or a string expression language. The AST is portable, auditable, and bounded. Registered operations remain pure and explicitly versioned.
3. **Separate observation from resolution.** Present `ObservedFact` stays nonempty; a separate generic resolution entry records absent, not-applicable, or error, including condition/rule IDs and scope. Batch/group completeness derives from required obligations. Alternative: nullable values in every fact; this blurs evidence and breaks the current meaning of `ObservedFact`.
4. **Version serialized facts independently of PHP compatibility.** Schema v2 stores a nonempty evidence list per present fact and scoped resolutions. The v1 decoder wraps one evidence item. Preserve current constructor and `$fact->evidence` access in the first release through compatibility behavior while adding plural evidence access. Alternative: immediate PHP break; that would block incremental adoption by both consumers.
5. **Keep exact text mapping.** Capture over concatenated raw descendant text, with Unicode-code-point offsets, exact raw quote, and selected-node path. A bounded normalization layer maintains a map to the raw span. Browser-visible mode is explicit and fails when mapping cannot be exact. Alternative: store normalized offsets or approximate rendered locations; neither supports source audit.
6. **Project cells, then assemble variants.** Existing table grid remains the structural source. A declarative projection yields cell facts with header, coordinates, and merged-cell origin; scoped assembly constructs group keys from declared source fields. Skip rules require observed predicates. Different values in a single-valued field conflict only that field, while all evidence is retained. Alternative: row-index alignment or silent row skips; both hide drift.
7. **Compare saved runs by declared identity.** Offline validation executes the compiled plan on saved bytes. Fact diff compares keys, values, states, and evidence; it never guesses identity from row position. The application owns config storage and activation.

## Risks / Trade-offs

- [Exact text mapping across nested or rendered nodes can be impossible] → Fail the capture with precise reason; never fabricate offset evidence.
- [A v2 evidence list conflicts with the existing single-evidence PHP property] → Add plural access and explicit compatibility behavior; test v1 read and current caller code.
- [Conditions can grow into a general expression language] → Keep the closed typed AST; require versioned pure registration for exceptional operations.
- [Partial batches can be misread as complete] → Surface batch and group completeness, scoped failures, and resolution states together.

## Migration Plan

Release additive config and API support; retain legacy extraction and v1 reads. Validate saved Banks cases and compare universal results before changing consumer mapping. Consumers opt into v2 declarations per source. Roll back by using prior configs and existing `ParseOutcome` path; serialized v2 results must not be decoded by old library versions.
