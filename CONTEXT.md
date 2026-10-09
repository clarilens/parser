# Parsing

The library extracts structured values from HTML, XML, and text-bearing PDF documents.

## Language

**Source**:
The document supplied to a parser: acquired from a local file or web URL, or provided in memory.

**Parsing scenario**:
A concrete source document, extraction intent, and expected result used to define supported behavior.

**Extraction rule**:
A declaration of how to find values in a source. A required rule fails when it finds no values; an optional rule yields an empty result.
_Avoid_: Needle

**Extracted value**:
One value found by an extraction rule, identified by its field and accompanied by a source location when available.

**Parse outcome**:
The extracted values, table grids, rule failures, and optional document origin collected during one pass over a source.

**Crawl**:
A bounded traversal of related sources that extracts records and tracks what was visited. Its stages and records are independent of any site's business domain.

**Crawl outcome**:
The records, failures, and coverage evidence produced by one crawl, including partial results when the crawl cannot finish.

**Crawl observation**:
A value extracted from one source at a known time, with its stage and source location. Multiple observations may contribute to one crawl record.

**Crawl record**:
A typed item assembled from crawl observations, identified by a declared key and linked to an existing parent record by key when applicable. Crawl records form a flat result even when their sources contain nested data.

**Traversal completion**:
The state in which every source discovered by the crawl's declared traversal rules has been processed within its limits.

**Coverage confirmation**:
Evidence that the crawl found the expected sources or records, based on declared counts or other explicit checks. Traversal completion alone does not confirm coverage.

**Observed fact**:
A source value with its exact published wording, origin, observation time, and document location. It may be incomplete or require interpretation by the caller.
_Avoid_: Verified claim
