# Bulk Update Workflow

This document describes the production bulk-update wiring for the MTG Collection application. Update it whenever a
bulk entrypoint, workflow order, schema prerequisite, source-tracking rule, or notification contract changes.

## Entry Point And Schedules

Use [`setup/data_updates.sh`](../setup/data_updates.sh) as the supported orchestration entrypoint. It runs from the
application root and stops on the first failing command (`set -euo pipefail`).

| Mode | Intended use | Sequence |
| --- | --- | --- |
| `new` | New installation after schema creation | sets → card refresh → rulings → tags → migrations → manifest → sync-state backfill |
| `refresh --confirm` | Manual destructive reset and full reload | reset data → same sequence as `new` |
| `nightly` | Normal daily update | sets → default cards → rulings → tags → migrations → manifest → collection snapshots → token cleanup |
| `weekly` | Full weekly card update and exports | sets → all cards → default cards → rulings → tags → migrations → manifest → weekly exports → collection snapshots → token cleanup |

The `refresh` mode requires the explicit `--confirm` argument because `scryfall_reset_data.php` removes imported data.

All PHP bulk entrypoints load `bulk/bulk_ini.php`, which returns the shared `AppContext`. The context supplies the
database connection, `AppConfig`, `GameRules`, and `Message` logger. Secrets remain in `/opt/mtg/mtg_new.ini`.

## Scryfall Workflows

### Cards: `bulk/scryfall_bulk.php`

The script is a thin wrapper around `MTG\Bulk\ScryfallBulkCommand`.

| Command | Effect |
| --- | --- |
| no argument / `default` | Imports Scryfall Default Cards; establishes primary-language data and downloads WebP images only for newly inserted rows. |
| `all` | Imports All Cards without bulk image download. |
| `refresh` | Downloads and imports All Cards, then Default Cards, without file-age reuse. |
| `test` | Recreates `cards_scry_test` and imports the two test fixtures. |
| `tags`, `oracle-tags`, `art-tags` | Runs the tag workflow. |
| `sync-state` | Runs the Scryfall data sync-state backfill. |

Card record flow is:

`ScryfallBulkCommand` → `ScryfallImport` facade → `ScryfallBulkFiles` →
`ScryfallCardRecordMapper` → `ScryfallCardImportPolicy` →
`ScryfallCardImportStatement` → `ScryfallCardImportRunner`.

The runner writes `cards_scry`, detects content/price changes using hashes, and calls `ScryfallSyncStateUpdater` for
new cards and content changes. `ScryfallSchemaGuard` verifies required card columns before writing.

Card and face image mapping selects Scryfall `grid` WebP URLs, falling back to
legacy `normal` JPEG URLs when `grid` is absent. Existing cache files are
resolved WebP first and JPEG second. Bulk image download remains limited to new
rows in a `default` import. UI cache misses download WebP on demand, while
ordinary checks leave existing JPEGs in place. Explicit Card Detail and Sets
refreshes are the phase-one migration paths. See
[`docs/scryfall_images.md`](../docs/scryfall_images.md).

### Rulings: `bulk/scryfall_rulings.php`

The wrapper calls `ScryfallRulingsImport`. It fetches rulings bulk metadata, maintains a local JSONL file, upserts
rows in `rulings_scry`, and uses a temporary key table to remove stale rulings only after successful processing. The
`RulingsHasher` builds the ruling content hash. Required table, column, and index checks go through
`ScryfallSchemaGuard`.

### Tags: `scryfall_bulk.php tags`

`ScryfallBulkCommand` delegates to `ScryfallTagImport`. The tag importer fetches Oracle and/or art tag metadata,
downloads the JSONL source, upserts `scryfall_tag_definitions` and `scryfall_tag_assignments`, then removes stale rows
for the imported tag type using temporary key tables. It does not alter the other tag type when running a single-type
mode.

### Sets: `bulk/scryfall_sets.php`

This remains a procedural importer. It downloads Scryfall set data, replaces the `sets` table contents, and downloads
missing set icons. On Saturdays it clears cached set icons so they are refreshed. It is intentionally scheduled before
card imports.

### Manifest: `bulk/scryfall_manifest.php`

The wrapper calls `ScryfallManifestImport`. It discovers languages from `cards_scry`, downloads paginated manifest
responses per language with a ten-second request interval, verifies that rows exist, then clears and repopulates
`scryfall_manifest` in transaction batches. An empty result must never clear the table.

### Migrations: `bulk/scryfall_migrations.php`

This is still procedural and paginated. It can delete or migrate card data, so treat it as deletion-sensitive. Do not
add skip logic or change its page handling unless the complete page set is known to be available and tests cover the
no-delete-on-empty/failed-source case.

### Sync State: `bulk/scryfall_sync_state.php`

Runs the `data-backfill` mode through the `ScryfallImport` facade. It is scheduled after a new/refresh load and is also
available via `scryfall_bulk.php sync-state`.

## Source Tracking And Skip Rules

`ScryfallBulkSourceTracker` records source state in `scryfall_bulk_sources`.

| Tracked workflow | Source type |
| --- | --- |
| Default cards | `default_cards` |
| All cards | `all_cards` |
| Rulings | `rulings` |
| Sets | `sets` |
| Oracle tags | `oracle_tags` |
| Art tags | `art_tags` |

The tracker computes a SHA-256 hash of the local downloaded file and records the download URI, local path, file size,
mtime, state, and import timestamps. A workflow skips processing only when the source type, URI, local path, and
content hash match a prior `completed` row. `running` and `failed` rows are retried.

Manifest and migrations are not source-tracked yet: their inputs are page sets, not a single file. Add run-level
fingerprints only after modelling a complete page set and failure/empty-result safety conditions.

`scryfall_bulk_sources` is required by the tracked workflows. Fresh installs receive it from `setup/mtg_new.sql`; an
existing database must be migrated before deploying code that uses these workflows.

## Email, CLI Output, And Logging

Bulk workflows log through `Message`. Card and tag modes send summaries through `ScryfallBulkCommand` when email is
enabled. Rulings, sets, and manifest send their own completion emails. Email-disabled runs log that no alert was sent.

CLI output is a short operator-facing completion or skip summary. Treat log messages and email summaries as
operational status, not as a substitute for checking exit status: `data_updates.sh` stops on a non-zero command.

## Non-Scryfall Scheduled Work

- `collection_snapshots.php` records one daily value snapshot per active user.
- `weekly_exports.php` generates opted-in users' collection and deck exports and emails them.
- `cleanup_tokens.php` removes expired trusted-device tokens.

## Change Checklist

When modifying bulk code:

1. Preserve the orchestration order unless downstream data dependencies are reviewed.
2. Update this document and `CHANGELOG.md` when behavior changes. Track unimplemented follow-up work in the active
   local work backlog.
3. Add or update the fresh schema in `setup/mtg_new.sql`; provide a live migration statement when a new table or
   column is required.
4. For source-tracked single files, mark the source `running` before mutation, `completed` only after all writes and
   stale cleanup succeed, and leave failures retryable.
5. For paginated/deletion-sensitive imports, prove that the complete source set is present before destructive writes.
6. Run focused PHPUnit tests, PHP lint, and `git diff --check`.
