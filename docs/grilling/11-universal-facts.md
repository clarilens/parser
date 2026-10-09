# Universal fact boundary

Decision: do not move Banks' application `SourceFact` type into the parser. Keep the library universal for Metrics, Banks, and other consumers. Reuse and extend the existing `ObservedFactBatch`, `ObservedFact`, and `FactEvidence` where general behavior warrants it.

Source: user confirmation and correction on 2026-10-09 to question 11.
