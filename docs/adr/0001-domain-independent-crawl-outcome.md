# Keep crawling independent of source domains

The crawl layer executes bounded, recursive traversal over related sources without built-in concepts such as projects, floors, or offers. It returns flat, typed records with explicit parent keys plus coverage evidence and failures, including partial records when a branch fails or a limit is reached. This keeps the document parser usable on its own and lets applications decide whether a crawl outcome is safe to publish.

The first version uses limited declarative normalization before comparing observations and assembling records. An explicit field-level source priority preserves every observation and records the chosen value; a missing parent produces a link failure and prevents a complete outcome. A later extension may allow callers to supply a Symfony denormalizer class for hydration. Hydration by caller-supplied classes is outside the first version.

The traversal rules declare which links are candidates. An unparseable candidate is a stage failure; absence of an optional coverage count leaves coverage unconfirmed while traversal may still finish. Observations from one response share its fetch time and response hash. The outcome retains extracted source values and response metadata, not full responses.

A declared range traversal may expand a bounded set of child sources when direct links are absent or incomplete. Ignored links and generated sources remain visible in coverage evidence; exceeding the range limit is a failure. Source counts are evidence until their meaning and comparison rule are explicitly declared. Repeated record keys under different parents are conflicts even when the remaining fields agree.

When direct links cover only part of a declared range, traversal generates the missing child sources. The same normalized URL at the same stage is fetched once while retaining every discovery path. Every fetched URL, including generated and linked URLs, must pass the source policy.

A missing parent hint on one discovery path does not override a known parent from another observation. Distinct known parents conflict. One fetched response can support every discovery path without being fetched again. Response limits are enforced while reading, and an exceeded limit records a source failure.

The configuration may declare mappings between differently expressed identifiers for the same parent, such as a building filter ID and its displayed name. Comparable parent observations are checked after normalization. A global response-size ceiling and optional lower per-stage ceilings apply during fetch.

The crawl configuration has its own version and is validated before any fetch. Unknown configuration keys, invalid stage references, invalid transforms, and missing required limits are configuration errors. A required parent mapping that cannot resolve an observed identifier is a crawl failure, with the original values retained.

Mappings may be extracted from a parent source for use by child stages. Conflicting values for one mapping key are failures, not first- or last-wins selections. Status normalization must produce one unambiguous value; overlapping matches are failures.

An extracted mapping is scoped to its source parent. A stage that does not declare an ID-to-name check does not fail merely because an ID is absent. Individual extracted values have explicit size limits; exceeding one records a failure without truncating it.

Traversal may iterate nested arrays and objects within one response to emit flat records with their JSON locations and parent keys. Separate limits bound extracted elements and emitted records per stage and run. A required JSON path whose value has the wrong shape is a stage failure; an explicitly optional path may be empty.
