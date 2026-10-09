# Source inspection, 2026-10-09

Read-only inspection of `/Users/farkhatsakibaev/Projects/metrics`:

- `src/Infrastructure/Parsing/ConfiguredProjectSource.php::discover()` recursively walks Metrum `child_projects` and keys projects by source ID; a duplicate overwrites the earlier entry.
- `ConfiguredProjectSource::fetch()` constructs Arm Construct floor URLs from project links or a bounded fallback range. It throws when a floor's advertised count exceeds distinct offer URLs extracted from the initial response.
- `ConfiguredProjectSource::unit()` maps the first matching status word and otherwise emits `unknown`.
- `src/Application/CrawlDeveloper.php::run()` catches per-project fetch failures and retains earlier database data. After a successful fetch, it marks previously stored offers absent from that project's `seen` list as `missing`.
- `tests/Fixtures/parser/README.md` documents synthetic fragments for nested JSON, count mismatch with `loadMore`, sold-out status, and duplicate keys. It says no browser update sequence is captured for offline replay.
- `/private/tmp/arm-floor-page.html` contains an observed count of 29 and Livewire markup. The initial HTML alone cannot prove the update response or browser click result.

These are observations about the current application and fixtures, not decisions about the new library API.
