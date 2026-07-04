# MTG Collection App - Developer Guidelines

## Setup Requirements

- Minimum runtime: PHP 8.2 and MySQL 8+.
- Docker/runtime target: PHP 8.4 and MySQL 8+.
- Required PHP extensions: `mysqli`, `mbstring`, `intl`, `gd`, `curl`.
- CLI tools: `git` and `composer` installed and on `PATH`.
- Critical app config lives in `/opt/mtg/mtg_new.ini` and contains secrets. Keep real values out of VCS.

## Build/Run Commands

- Scryfall workflow wrapper, network access required:
  `setup/data_updates.sh new`, `setup/data_updates.sh nightly`, `setup/data_updates.sh weekly`, or
  `setup/data_updates.sh refresh --confirm`.
- Database init: `php setup/initial.php username password`.
- Focused script entrypoints are still available for debugging:
  `php bulk/scryfall_sets.php`, `php bulk/scryfall_bulk.php all|default|refresh`,
  `php bulk/scryfall_rulings.php`, `php bulk/scryfall_migrations.php`,
  `php bulk/scryfall_manifest.php`, `php bulk/scryfall_sync_state.php data-backfill`,
  and `php bulk/weekly_exports.php`.

## Autoloading And Bootstrap

- Composer autoload is loaded in `bootstrap.php` and `bulk/bulk_ini.php`; third-party dependencies live in `vendor/`.
- App classes live under `src/MTG/` and are PSR-4 autoloaded via Composer.
- Use namespaced app classes under `MTG\*`, such as `MTG\Auth`, `MTG\Cards`, and `MTG\Core`.
- Shared helpers belong in Core/Admin classes, e.g. `MTG\Core\Validation`, `MTG\Core\Http\UrlHelper`, and
  `MTG\Core\Http\AjaxResponse`.
- Every entrypoint should `require` a bootstrap and receive `$ctx`.
- Do not introduce new ambient globals. Add data to `$ctx` meta/accessors and read it from there.
- In entrypoints, assign local `$appConfig`, `$db`, `$msg`, and `$gameRules` from `$ctx` as needed.
- Prefix bootstrap-internal locals with `$_` and keep them scoped to bootstrap files.

## Code Style

- PHP style is PSR-12-ish with project exceptions captured in `phpcs.xml`.
- Keep alternative control-structure syntax in PHP/templates: `if/else/endif`, `while/endwhile`,
  `foreach/endforeach`.
- Use PHP 8.4-compatible code while preserving PHP 8.2 compatibility where configured.
- Use direct `mysqli` queries. Do not introduce an ORM.
- Do not split SQL statements with string concatenation unless dynamic fragments are genuinely required; prefer one
  literal with embedded newlines.
- Code control structures should have useful DEBUG-level logging where flow is non-obvious or operationally important.
- All file edits should increment the file information header version and keep the header standardized.
- For docs-only files without headers, do not invent a PHP-style header.

## Security Defaults

- State-changing actions must use POST unless there is a deliberate documented exception.
- POST mutations must validate CSRF before reading or applying action parameters.
- Use `SessionManager::generateCsrfToken()` for forms and `SessionManager::validateCsrfToken()` for validation.
- AJAX endpoints should use `SessionManager::validateAjaxRequest()` unless there is a clear reason not to.
- Treat referrer checks as defense in depth. CSRF tokens are the primary protection.
- Validate ownership at the action boundary before deck/user/collection mutations.
- Validate UUIDs before using values in card-detail links or card mutation actions.
- Dynamic SQL identifiers, such as per-user collection table names, must be validated with
  `MTG\Core\Validation::validTableName()` and quoted consistently.
- External redirects must go through `UrlHelper::normalizeRedirectUrl()` or equivalent same-origin validation.

## Rendering And Escaping

- Escape HTML text and attributes with `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- Precompute `...Esc` variables near row extraction when rendering long legacy echo blocks.
- Only allow intentionally generated HTML from trusted helpers; document that intent in the local code shape.
- Validate UUIDs before rendering `carddetail.php?id=...`.
- Sanitize or whitelist CSS class suffixes such as set codes, rarities, and icon names before injecting into class
  attributes.
- Legacy render files such as `carddetail.php`, `index.php`, and `includes/fragments/deckdetail_decklist.php` are the
  highest-risk areas for stored-XSS regressions. Harden one section at a time and add focused tests.

## Variable And Contract Typing

- Initialize variables before conditional branches when they are read later.
- Use native parameter, return, and property types for stable application contracts after auditing callers.
- Prefer narrow native scalar/array/callable/bool/int/string/float/null union types where they reflect runtime values.
- Use PHPDoc array shapes/generics for arrays where useful, e.g. `array<string, mixed>`.
- Keep `mysqli` and mysqli-compatible database seams documented with PHPDoc instead of native types when native typing
  would make tests or adapters brittle.
- Add local `@var` annotations for values populated by `bind_result()` or similarly dynamic APIs when static analysis
  cannot infer their type.
- Do not chase zero IDE warnings blindly. Fix possible undefined variables and nullable contract issues first, then
  type stable service/helper contracts.

## Error Handling And Logging

- Use `mtgError` for user-facing errors and `mtgException` for exception paths/logging where legacy code expects them.
- Newer classes should prefer exceptions and `Message` logging over direct output.
- Log file path is set in `/opt/mtg/mtg_new.ini`; ensure the process user can write to it.
- Use `Loglevel` in the ini to control verbosity. Dev can be higher; production should be lower.
- Set up log rotation for the configured log file.

## Testing And Quality Gates

- PHPUnit suite: `vendor/bin/phpunit`.
- PHPUnit test directory: `tests`.
- PHPCS: `vendor/bin/phpcs --report=summary` or run PHPCS on touched files.
- PHPCBF can be used for simple style fixes, e.g. indentation.
- Point `/opt/mtg/mtg_new.ini` to a test database/config when running integration-style tests.
- New behavior should be testable through objects or small helpers where practical.
- When adding/tightening native types, run focused tests for affected callers and update test doubles to match
  production signatures.
- Security fixes should include regression coverage for the gate being added: method, CSRF, ownership, validation, or
  escaping.
- Source-level tests are acceptable for legacy pages that are difficult to execute safely, but prefer request/fixture
  tests when the bootstrap allows it.
- Full suite should pass before declaring broad changes complete.

## Changelog And Release Notes

- Track meaningful changes in `CHANGELOG.md`.
- Do not track minor typo fixes or changes that do not alter flow, logic, behavior, security posture, or function.
- Classify changes as `Added`, `Changed`, `Fixed`, `Removed`, `Security`, `Infrastructure`, or `Deprecated`.
- If someone asked "what changed since last time?" and the entry would help, include it.
- Keep release entries user/reviewer oriented. Avoid listing every touched file when a theme-level entry is clearer.

## AI Actions

- Standard Tidyup: no approval needed. Remove trailing whitespace, apply PHPCS automatic tidyup, split long lines to
  <=120 characters where practical, and apply standard header format. Do not change logic, program flow, control
  structures, or output.
- Advanced Tidyup: Standard Tidyup plus a basic review of logic and possible optimization. Make no logic or
  optimization changes without approval.

## Problem-Solving Expectations

- For complex or contentious issues, insist on a minimal reproducible test with fixed inputs and expected outputs
  before concluding.
- Validate the full data path end to end: inputs -> transforms -> storage -> readback.
- Treat logs/diagnostics as hypotheses; verify with direct checks when they conflict with observed behavior.
- Prefer small, reversible instrumentation gated to test mode and remove it once the root cause is confirmed.
- Before major hardening work, check `CODEBASE_REVIEW_FINDINGS.md` for the current risk priorities.

## Header

Standard header format example:

```php
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
```
