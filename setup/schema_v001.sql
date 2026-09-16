-- ============================================================
-- Schema update: bootstrap schema version 1
-- Migration ID: 202609160001
-- Date: 2026-09-16
-- Purpose: Create schema_metadata table and seed baseline version
-- Bundled with release: v0.7.0
-- ============================================================
-- MySQL DDL auto-commits. Do not wrap in a transaction.
-- ============================================================
--
-- Prerequisites:
--   - MySQL 8.0.16+ server with existing tables (users, admin, etc.).
--   - Database user must have CREATE and INSERT privileges.
--
-- Deployment sequencing:
--   1. Apply this migration when adding schema version tracking to an
--      existing installation that has no schema_metadata table.
--   2. Run before deploying any code that reads schema_version.
--
-- Fresh-install note:
--   Fresh installations do not need this migration. The schema_metadata
--   table and baseline row are included in setup/mtg_new.sql.
--
-- Existing table handling:
--   If schema_metadata already exists, the migration will fail with
--   an "already exists" error. This is expected — the table indicates
--   schema versioning is already in place.
--
-- To run manually:
--   mysql -u <user> -p mtg_new < setup/schema_v001.sql
--
-- To verify after running:
--   SELECT schema_version FROM schema_metadata LIMIT 1; (should be 1)
--

-- ============================================================
-- Ensure UTC time zone for deterministic timestamps
-- ============================================================

SET time_zone = '+00:00';

-- ============================================================
-- Create schema_metadata table (singleton metadata row)
-- ============================================================

CREATE TABLE `schema_metadata` (
  `id` INT UNSIGNED NOT NULL DEFAULT 1,
  `schema_version` INT UNSIGNED NOT NULL DEFAULT 1,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `chk_schema_metadata_singleton` CHECK (`id` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Seed baseline: version 1
-- ============================================================

INSERT INTO `schema_metadata` (`id`, `schema_version`) VALUES (1, 1);

-- ============================================================
-- Rollback (destructive — use only before first use or with
-- verified backup)
-- ============================================================
-- DROP TABLE `schema_metadata`;
-- ============================================================
