# MTG Collection App - Developer Guidelines

## Setup Requirements
- Minimum:
-- PHP 8.2 and MySQL 8+.
- Docker install:
-- PHP 8.4 and MySQL 8+.
- PHP extensions: mysqli, mbstring, intl, gd, curl.
- CLI tools: git and composer installed and on PATH.
- Critical app config lives in `/opt/mtg/mtg_new.ini` (contains secrets). Keep it out of VCS; use a local copy or a sample template and never commit real values.

## Build/Run Commands
- Data workflow order (Scryfall network access required): `php bulk/scryfall_sets.php` → `php bulk/scryfall_bulk.php all` → `php bulk/scryfall_rulings.php` → `php bulk/scryfall_migrations.php` → `php bulk/weekly_exports.php`.
- Database init: `php setup/initial.php username password`.
- Bulk data import: `php bulk/scryfall_bulk.php all`.
- Sets import: `php bulk/scryfall_sets.php`.
- Rulings import: `php bulk/scryfall_rulings.php`.
- Migrations: `php bulk/scryfall_migrations.php`.
- Weekly exports: `php bulk/weekly_exports.php`.

## Autoloading
- Composer autoload is loaded in `bootstrap.php` and `bulk/bulk_ini.php`; third-party deps live in `vendor/`.
- App classes live under `src/MTG/` and are PSR-4 autoloaded via Composer. Use namespaced classes (e.g., `MTG\Auth`, `MTG\Cards`, `MTG\Core`).
- Shared helpers now live in Core classes (e.g., `MTG\Core\Validation`, `MTG\Core\Http\UrlHelper`).

## Code Style Guidelines
- PHP 8.4 with direct `mysqli` queries (no ORM).
- Session handling through `\MTG\Auth\SessionManager`.
- Error handling: use `mtgError` for user-facing errors and `mtgException` for exception paths/logging.
- Mobile-responsive design uses jQuery on the frontend.
- Follow existing PSR-12-ish formatting and class/function placement conventions.
- Exception to PSR-12 formatting: Always use if/else/endif, while/endwhile, foreach/endforeach formats
- All file edits should increment file information header version
- Changes should also check that header is standardised (see ## Header section)
- Changes should be tracked in CHANGELOG.md, with the following rules:
-- Do not track minor typo fixes or other changes which do not alter flow, logic, behaviour or function
-- Classify changes as Added / Changed / Fixed / Removed / Security / Infrastructure / Deprecated
-- If someone asked “what changed since last time?” — would this help them? If yes, include it
- Do not split SQL statements with string concatenation; keep them as single literals (with embedded newlines if needed)
- App classes are namespaced under `MTG\*`; shared helpers are now in Core/Admin classes, and AJAX responses use `MTG\Core\Http\AjaxResponse`.
- All code changes should result in clean phpcs runs with PHP 8.4 compatibility
- phpcbf can be used to find and automatically resolve simple style issues, e.g. indentation
- Code control structures should contain suitable DEBUG-level logging to track code flow

## Logging
- Log file path is set in `/opt/mtg/mtg_new.ini`; ensure the process user can write to it.
- Use `Loglevel` in the ini to control verbosity (dev: higher; prod: lower). Set up log rotation for the configured file to avoid disk bloat.

## Testing
- PHPUnit suite: `vendor/bin/phpunit` (requires composer install).
- PHPUnit test directory: `tests`
- Point `/opt/mtg/mtg_new.ini` to a test database/config when running tests to avoid clobbering production data.
- New functions added to the app should, where possible, be object-oriented to allow for new tests to be written easily.

## AI actions
- Standard Tidyup: No approval needed. Remove end of line whitespaces, apply phpcs automatic tidyup, split long lines to be <=120 characters, apply standard header format (see below). Do not change logic, program flow, or control structures. Must not change output.
- Advanced Tidyup: Standard Tidyup plus also conduct a basic review of logic and possible optimisation. Make no logic code changes or optimisation without approval.

## Bootstrap context rules
- Make `$ctx` the only object promised to callers; treat ambient variables as deprecated.
- Keep `$appConfig`, `$db`, `$msg`, and `$gameRules` ambient for now; do not introduce new ambient globals beyond those.
- Prefer reading values via `$ctx->...()` or local assignments as files are edited.
- Avoid introducing new ambient variables in `bootstrap.php`; add data to `$ctx` instead.

## Problem-Solving Expectations
- For complex or contentious issues, insist on a minimal reproducible test with fixed inputs and expected outputs before concluding.
- Validate the full data path end-to-end (inputs → transforms → storage → readback) rather than assuming any step.
- Treat logs/diagnostics as hypotheses; verify with direct checks when they conflict with observed results.
- Prefer small, reversible instrumentation gated to test mode and remove it once the root cause is confirmed.

## Header
Standard Header format example:

/*
Version:     2.2
Date:        25/11/25
Name:        help.php
Purpose:     Provides a help submission form and place for help notes.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/
