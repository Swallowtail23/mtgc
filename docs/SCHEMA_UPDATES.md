# Schema Update Management

## Overview

This document describes how database schema updates are managed in the MTG Collection application.

## Schema Version Tracking

Schema version is stored in the `schema_metadata` table:

```sql
CREATE TABLE `schema_metadata` (
  `id` INT UNSIGNED NOT NULL DEFAULT 1,
  `schema_version` INT UNSIGNED NOT NULL DEFAULT 1,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `chk_schema_metadata_singleton` CHECK (`id` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

- The table uses `id=1` as a singleton primary key with a MySQL CHECK constraint to enforce at most one row.
- `schema_version` tracks the current database schema version.
- `updated_at` is automatically updated when the row changes.

## Migration File Convention

Migration files follow the naming pattern:

```
setup/schema_vNNN.sql
```

Where `NNN` is a zero-padded three-digit version number. Examples:

- `setup/schema_v001.sql` — bootstrap schema version 1 (adds `schema_metadata` table)
- `setup/schema_v002.sql` — upgrade to schema version 2 (adds new tables/features)

Each file is self-contained and may include multiple schema changes (multiple `CREATE TABLE`, `ALTER TABLE`, etc.).

### Version Numbering

- Version numbers are sequential integers starting from 1.
- Each version represents a logical unit of schema change, typically tied to a release.
- Migration files are applied in ascending version order.

## Applying Migrations

### Automated (CLI)

Run the maintenance script from the command line:

```bash
php tools/maintenance.php
```

The script:

1. Reads database credentials from the INI file (same as the application).
2. Queries the current schema version from `schema_metadata`.
3. Discovers all `schema_v*.sql` files in `setup/` (supports `schema_vNNN.sql` and `schema_vNNN_<name>.sql`).
4. Verifies there are no gaps in the migration chain before applying any changes.
5. Applies migrations sequentially from the current version up to the latest.
6. Verifies the final schema version matches the expected latest version.
7. Reports which migrations were applied.

**Idempotency:** If the database is already at the latest version, the script exits cleanly with no action.

### Manual

Apply a specific migration file directly:

```bash
mysql -u <user> -p mtg_new < setup/schema_v002.sql
```

Then verify:

```sql
SELECT schema_version FROM schema_metadata LIMIT 1;
```

## Creating a New Migration

When adding a new schema change:

1. **Determine the next version number.** Check the latest migration file name and increment.
2. **Create the migration file** in `setup/schema_vNNN.sql` using the template below.
3. **Update `setup/mtg_new.sql`** to include the new schema objects so fresh installations start at the correct version.
4. **Update `CHANGELOG.md`** with the migration entry.
5. **Run the maintenance script** against a test database to verify the migration applies cleanly.
6. **Run PHPCS** on any new PHP files.

### Migration File Template

```sql
-- ============================================================
-- Schema update: version NNN-1 -> NNN
-- Migration ID: YYYYMMDDXXXX  (date + sequence)
-- Date: YYYY-MM-DD
-- Purpose: <brief description of the schema change>
-- Bundled with release: vX.Y.Z
-- ============================================================
-- MySQL DDL auto-commits. Do not wrap in a transaction.
-- ============================================================
--
-- Prerequisites:
--   - MySQL 8+ server.
--   - Current schema version must be NNN-1.
--   - Database user must have CREATE, ALTER, and/or INSERT privileges as needed.
--
-- Deployment sequencing:
--   1. Verify current schema version: SELECT schema_version FROM schema_metadata LIMIT 1;
--   2. Apply this migration.
--   3. Verify: SELECT schema_version FROM schema_metadata LIMIT 1; (should be NNN)
--
-- Fresh-install note:
--   Fresh installations do not need this migration. The schema changes
--   are included in setup/mtg_new.sql.
--
-- Existing table handling:
--   If the target table(s) already exist, the migration will fail with
--   an "already exists" error. This is expected — the migration has
--   already been applied.
--
-- To run manually:
--   mysql -u <user> -p mtg_new < setup/schema_vNNN.sql
--

-- ============================================================
-- Set UTC time zone for deterministic timestamps
-- ============================================================

SET time_zone = '+00:00';

-- ============================================================
-- Schema changes go here
-- ============================================================

-- Example: CREATE TABLE
-- CREATE TABLE `new_table` (...);

-- ============================================================
-- Version bump
-- ============================================================

UPDATE schema_metadata SET schema_version = NNN;

SELECT 'Schema version bumped to NNN. Migration complete.' AS info;

-- ============================================================
-- ROLLBACK (destructive — use only before first use or with
-- verified backup)
-- ============================================================
-- <reverse each schema change in reverse order>
-- UPDATE schema_metadata SET schema_version = NNN-1;
-- ============================================================
```

## Fresh Installations

Fresh installations use `setup/mtg_new.sql` which includes all schema objects up to the current version. The `schema_metadata` table is created and seeded with `schema_version = NNN` (the current version). No migration file is needed for fresh installs.

## Rollback

Rollback is destructive and only appropriate before first use or with a verified backup. Each migration file includes a commented-out rollback section. Rollback steps:

1. Run the rollback statements in the migration file (reverse order of schema changes).
2. Reset `schema_version` to the previous version.
3. Verify the database state.

**Warning:** MySQL DDL auto-commits. There is no transactional rollback for DDL statements.

## Partial Failure Recovery

MySQL DDL auto-commits. If a migration fails mid-execution:

1. Check the current schema version: `SELECT schema_version FROM schema_metadata LIMIT 1;`
2. Check for partially created objects: `SHOW TABLES LIKE '%<partial_name>%';`
3. Clean up any partial objects manually.
4. Re-run the migration — it will fail on duplicate objects.
5. Manually verify the schema state matches expectations.

To avoid partial failures, keep migration files focused on a single logical change.

## Security Considerations

- Migration files contain SQL that runs with full database privileges. Only apply migrations from trusted sources.
- The maintenance script reads database credentials from the INI file. The INI file should be stored outside the web root with restrictive filesystem permissions.
- The maintenance script is CLI-only. It cannot be accessed via HTTP.
- No raw keys, credentials, or sensitive data are logged by the maintenance script.

## File Naming Convention Summary

| Pattern | Description |
|---|---|
| `schema_vNNN.sql` | Versioned migration file (applied sequentially) |
| `schema_vNNN_<name>.sql` | Versioned migration with a descriptive name (optional suffix) |

The maintenance script uses `glob('setup/schema_v*.sql')` to discover migrations and sorts them by filename to ensure correct application order. It supports both naming patterns and will warn on duplicate version numbers (keeping the last file found).
