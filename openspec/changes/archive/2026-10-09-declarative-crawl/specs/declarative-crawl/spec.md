## Purpose

Enable bounded, domain-neutral traversal of related sources into structured records with explicit traversal and coverage evidence, including partial outcomes.

## ADDED Requirements

### Requirement: Compiled crawl graph
The system SHALL validate a versioned graph of stages, scoped record fields, transitions, generated URLs, bounds, and referenced operations before the first request. Generated and redirected URLs SHALL obey host and network safety policy after substitution.

#### Scenario: Invalid transition
- **WHEN** a stage references a missing stage or a URL template can escape the allowed hosts
- **THEN** compilation fails before fetching a source

### Requirement: Structured record traversal
The system SHALL extract records relative to a declared container, preserve typed record keys and parent keys, and traverse nested JSON arrays or objects from HTML attributes as well as linked pages. Entity type and declared namespace SHALL distinguish otherwise identical source IDs.

#### Scenario: Nested source identifiers
- **WHEN** a source contains the same numeric ID for a parent and child record of different declared types
- **THEN** the records remain distinct and retain their parent relation

#### Scenario: Conflicting parent
- **WHEN** one normalized record key appears under different parent keys
- **THEN** the system reports an identity conflict and retains the underlying observations

### Requirement: Bounded repeated retrieval
The system SHALL support declared next-link, page-parameter, and browser-repeat traversal with explicit page, click, item, and duration limits. Browser repetition SHALL wait for declared progress and stop at a terminal condition; no progress while the action remains available SHALL fail the branch. Retry exhaustion and limit exhaustion SHALL return typed partial results.

#### Scenario: Browser action stalls
- **WHEN** a load-more action remains available but the declared item count does not increase
- **THEN** the branch reports no progress and retains records collected so far

#### Scenario: Limit exhausted
- **WHEN** a run reaches a declared limit before completing all discovered work
- **THEN** the outcome retains observations and identifies the limit and incomplete branch

### Requirement: Traversal and coverage evidence
The system SHALL report traversal completion separately from coverage confirmation. A source count or explicit comparison check MAY confirm coverage; exhausting declared traversal without an expected count SHALL not silently confirm coverage. Checks against a prior successful run SHALL be explicitly configured with a threshold. The library SHALL not decide whether to publish consumer snapshots.

#### Scenario: No expected count
- **WHEN** all declared links are processed but the source provides no expected count or other explicit coverage check
- **THEN** traversal is complete and coverage remains unconfirmed

#### Scenario: Count mismatch after terminal action
- **WHEN** a load-more button disappears but distinct items remain below the source-declared count
- **THEN** loop traversal ends, coverage fails with expected and observed counts, and the branch is incomplete

### Requirement: Partial outcome and provenance
The system SHALL return successful records even when a sibling or child branch fails, with typed failures, incomplete subtree markers, record/group keys, source locations, attempt counts when relevant, config schema version and hash, and operation registry fingerprint. Identical URLs MAY share a fetch but SHALL be evaluated in each parent context.

#### Scenario: Child fetch fails
- **WHEN** a parent record is extracted and one discovered child page cannot be fetched
- **THEN** the parent and successful siblings remain in the outcome and the parent's subtree is marked incomplete

### Requirement: Reproducible offline contracts
The system SHALL support deterministic saved-source execution covering nested JSON and a browser update sequence. Fixtures SHALL retain source bytes and execution mode without credentials.

#### Scenario: Offline browser replay
- **WHEN** a saved load-more sequence is replayed without network access
- **THEN** the resulting records, failures, traversal state, and coverage evidence match the expected contract
