## 1. Graph and records

- [x] 1.1 Add versioned crawl configuration and compile stage graph, field scopes, limits, generated URLs, and operation references; verify invalid graphs fail before a request.
- [x] 1.2 Add nested JSON extraction from HTML attributes and scoped record assembly with typed namespaced keys and parent links; verify repeated numeric IDs across record types remain distinct.
- [x] 1.3 Add follow and pagination traversal with concrete URL/redirect safety checks; verify same-URL fetch reuse preserves separate parent contexts and conflicting keys fail.

## 2. Bounded execution and outcomes

- [x] 2.1 Add bounded browser repetition with progress and terminal checks; verify no-progress and count-mismatch scenarios produce distinct failures.
- [x] 2.2 Add page, click, item, duration, and retry limits; verify exhaustion retains partial records and identifies the affected branch.
- [x] 2.3 Add traversal, coverage, incomplete-subtree, failure, and config-provenance fields to `CrawlOutcome`; verify no-count traversal remains coverage-unconfirmed.
- [x] 2.4 Reuse the shared condition core for explicit count and prior-run coverage checks; verify a configured threshold is required for prior-run comparison.

## 3. Consumer contracts

- [x] 3.1 Save sanitized nested-JSON and browser-update sequences for offline replay; verify source bytes and execution mode suffice without network or credentials.
- [x] 3.2 Run offline Metrum and Arm Construct contract scenarios, including partial branches, key conflicts, and coverage; verify resulting records match consumer needs without domain types in the library.
- [x] 3.3 Stabilize and publish `CrawlConfig` v1 only after both scenarios pass; verify old one-document parsing remains callable.
- [x] 3.4 Run `composer test`, `composer psalm`, and `composer cs:check`; fix introduced failures and record any pre-existing failure scope.
