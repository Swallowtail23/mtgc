-- ============================================================
-- Schema update: version 1 -> 2
-- Migration ID: 202609170001
-- Date: 2026-09-17
-- Purpose: Add user_api_keys table for API key management
-- Bundled with release: TBD
-- ============================================================
-- MySQL DDL auto-commits. Do not wrap in a transaction.
-- ============================================================
--
-- Prerequisites:
--   - MySQL 8+ server.
--   - Current schema version must be 1.
--   - Database user must have:
--       CREATE privilege for the new table,
--       REFERENCES privilege on the users table for the foreign key,
--       UPDATE privilege on schema_metadata for the version bump.
--
-- Deployment sequencing:
--   1. Verify current schema version: SELECT schema_version FROM schema_metadata LIMIT 1;
--   2. Apply this migration.
--   3. Verify: SELECT schema_version FROM schema_metadata LIMIT 1; (should be 2)
--
-- Fresh-install note:
--   Fresh installations do not need this migration. The user_api_keys
--   table is included in setup/mtg_new.sql.
--
-- Existing table handling:
--   If user_api_keys already exists while schema_metadata.version is still 1,
--   treat this as a possible partial or manual migration. Stop and investigate
--   before proceeding.
--
-- To run manually:
--   mysql -u <user> -p mtg_new < setup/schema_v002.sql
--

-- ============================================================
-- Set UTC time zone for deterministic timestamps
-- ============================================================

SET time_zone = '+00:00';

-- ============================================================
-- Create user_api_keys table (FK inline is valid here because
-- the users table already exists in the database)
-- ============================================================

CREATE TABLE `user_api_keys` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usernumber` SMALLINT NOT NULL,
  `key_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `key_prefix` VARCHAR(23) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `label` VARCHAR(128) DEFAULT NULL,
  `scope` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'collection:read',
  `created_at` DATETIME NOT NULL,
  `last_used_at` DATETIME DEFAULT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `revoked_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_api_keys_hash` (`key_hash`),
  KEY `idx_user_api_keys_owner` (`usernumber`, `revoked_at`),
  KEY `idx_user_api_keys_last_used` (`last_used_at`),
  CONSTRAINT `fk_user_api_keys_user`
    FOREIGN KEY (`usernumber`) REFERENCES `users` (`usernumber`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Version bump
-- ============================================================

UPDATE schema_metadata SET schema_version = 2 WHERE id = 1 AND schema_version = 1;

SELECT 'Schema version bumped to 2. Migration complete.' AS info;

-- ============================================================
-- ROLLBACK (destructive — use only before first use or with
-- verified backup)
-- ============================================================
-- DROP TABLE `user_api_keys`;
-- UPDATE schema_metadata SET schema_version = 1;
-- ============================================================
