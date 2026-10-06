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

**Observed fact**:
A source value with its exact published wording, origin, observation time, and document location. It may be incomplete or require interpretation by the caller.
_Avoid_: Verified claim
