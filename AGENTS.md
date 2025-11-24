# MTG Collection App - Developer Guidelines

## Build/Run Commands
- Database init: `php setup/initial.php username password`
- Bulk data import: `php bulk/scryfall_bulk.php all`
- Sets import: `php bulk/scryfall_sets.php`
- Rulings import: `php bulk/scryfall_rulings.php` 
- Migrations: `php bulk/scryfall_migrations.php`
- Weekly exports: `php bulk/weekly_exports.php`

## Code Style Guidelines
- PHP 8.2 with MySQL 8+
- Codebase uses class-based architecture with autoloading
- Class files use lowercase.class.php naming convention
- Functions are defined in includes/functions.php
- Critical app config in /opt/mtg/mtg_new.ini (never commit secrets)
- Error handling via mtg_error and mtg_exception functions
- Session handling through sessionmanager.class.php
- Database: Direct mysqli queries (no ORM)
- Currency conversion handled through FreecurrencyAPI
- Log to file specified in ini with loglevel control
- Mobile-responsive design with JQuery for frontend