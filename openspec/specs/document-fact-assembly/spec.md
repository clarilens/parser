# document-fact-assembly Specification

## Purpose

Enable domain-neutral, declarative extraction of evidence-backed facts and related groups from one document, with explicit resolution and failure states.

## Requirements

### Requirement: Compiled portable declaration
The system SHALL validate field references, scopes, types, selectors, regular expressions, cardinality, and operation versions before document processing. Built-in declarations SHALL require no consumer PHP; registered custom operations SHALL be pure and version-pinned.

#### Scenario: Missing custom operation
- **WHEN** a saved declaration references an unavailable operation or different version
- **THEN** compilation fails before extraction without substituting another implementation

### Requirement: Exact named text capture
The system SHALL capture named substrings within selected HTML text, returning a value, exact raw quote, DOM location, and Unicode-code-point offset into concatenated raw descendant text. Rules SHALL declare cardinality `one`, `first`, or `all`, defaulting to `one`; source mode SHALL default to raw DOM text and MAY explicitly select browser-visible text.

#### Scenario: Normalized match
- **WHEN** a rule matches text after declared whitespace normalization and the match maps to one contiguous raw span
- **THEN** the result contains the normalized value, exact raw quote, and raw-text offset

#### Scenario: Ambiguous or unmappable match
- **WHEN** a `one` capture matches twice or a visible-text match cannot map to an exact raw span
- **THEN** the rule reports a scoped failure without an approximate quote or offset

### Requirement: Typed source-derived transformations
The system SHALL offer bounded, declared transformations for source text, numeric and unit parsing, enum mapping, and safe URL construction. It SHALL retain raw input, exact source evidence, and operation IDs and versions; ambiguous parsing SHALL fail explicitly.

#### Scenario: Locale-formatted value
- **WHEN** a configured numeric field contains a decimal comma and declared unit
- **THEN** the result contains the typed normalized value and original source wording

### Requirement: Table cell facts and variants
The system SHALL project table cells with header relation, row and column coordinates, merged-cell origin, group key, and original cell evidence. It SHALL assemble variants from declared axes and required fields without importing consumer domain types. A configured skip SHALL require positive source evidence.

#### Scenario: Invalid candidate row
- **WHEN** a data row has a value that its declared parser cannot recognize and no evidenced skip rule applies
- **THEN** the system reports a row-scoped failure rather than silently omitting it

#### Scenario: Variant key collision
- **WHEN** two variant candidates share a configured group key but disagree on a single-valued field
- **THEN** that field is unresolved, both observations remain available, and independent fields remain resolved

### Requirement: Scoped condition evaluation
The system SHALL evaluate a bounded typed condition tree with `true`, `false`, and `unknown` outcomes. Unknown inputs SHALL fail. A false applicability condition SHALL produce `not_applicable` only when explicitly declared and supported by observed input evidence. Required absence SHALL produce an `absent` resolution and linked failure; optional absence SHALL not fail completeness.

#### Scenario: Missing condition input
- **WHEN** an applicability condition needs a field whose extraction failed
- **THEN** the condition reports an unknown-input failure and does not mark the dependent fact not applicable

### Requirement: Universal fact batch v2
The system SHALL return present facts separately from scoped absent, not-applicable, and error resolutions. Each present single-valued fact SHALL retain all evidence items from identical normalized observations. The batch SHALL report group and batch completeness, stable failure IDs, source/group locations, config schema version, canonical config hash, and operation registry fingerprint. Schema v1 input SHALL remain readable and existing first-release PHP access SHALL remain usable.

#### Scenario: Partial document
- **WHEN** one group fails a required rule and another resolves
- **THEN** the batch is incomplete, retains the resolved group, and reports the failed group's reason

#### Scenario: Existing serialized batch
- **WHEN** a schema v1 batch is read
- **THEN** its single evidence item is available in the v2 evidence representation without changing the meaning of its original completeness flag

### Requirement: Reproducible validation and diff
The system SHALL support validation against saved source documents and compare fact results by configured identity, normalized value, resolution state, and evidence. A change in group-key fields SHALL be reported as an identity change rather than matched by source position.

#### Scenario: Config revision comparison
- **WHEN** two revisions run against the same saved source and generate different group keys
- **THEN** the diff reports removed and added identities with supporting evidence
