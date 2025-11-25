# MTG Collection App - Developer Guidelines

## Setup Requirements
- PHP 8.2 and MySQL 8+.
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
- Composer autoload is loaded in `includes/ini.php`; third-party deps live in `vendor/`.
- App classes are in `classes/` using `lowercase.class.php` naming. After adding classes, ensure autoload picks them up (run `composer dump-autoload` if adding composer autoload rules).
- Shared functions belong in `includes/functions.php`.

## Code Style Guidelines
- PHP 8.2 with direct `mysqli` queries (no ORM).
- Session handling through `sessionmanager.class.php`.
- Error handling: use `mtg_error` for user-facing errors and `mtg_exception` for exception paths/logging.
- Mobile-responsive design uses jQuery on the frontend.
- Follow existing PSR-12-ish formatting and class/function placement conventions.
- Exception to PSR-12 formatting: Always use if/else/endif, while/endwhile, foreach/endforeach formats
- All file edits should increment file information header/version history (where present)
- Do not split SQL statements with string concatenation; keep them as single literals (with embedded newlines if needed)
- App is not namespaced. All classes are global.

## Logging
- Log file path is set in `/opt/mtg/mtg_new.ini`; ensure the process user can write to it.
- Use `Loglevel` in the ini to control verbosity (dev: higher; prod: lower). Set up log rotation for the configured file to avoid disk bloat.

## Testing
- PHPUnit suite: `vendor/bin/phpunit` (requires composer install).
- Point `/opt/mtg/mtg_new.ini` to a test database/config when running tests to avoid clobbering production data.
