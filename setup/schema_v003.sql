/*
Version:     1.0
Date:        26/09/26
Name:        schema_v003.sql
Purpose:     Migration from schema version 2 to 3. Adds scryfall_game_types
             table for database-backed game-type catalogue and selection state.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

-- ============================================================
-- Schema update: version 2 -> 3
-- Migration ID: 202609180001
-- Date: 2026-09-18
-- Purpose: Add scryfall_game_types table for database-backed
--          game-type catalogue and selection state
-- Bundled with release: TBD
-- ============================================================
-- MySQL DDL auto-commits. Do not wrap in a transaction.
-- ============================================================
--
-- Prerequisites:
--   - MySQL 8+ server.
--   - Current schema version must be 2.
--   - Database user must have:
--       CREATE privilege for the new table,
--       UPDATE privilege on schema_metadata for the version bump.
--
-- Deployment sequencing:
--   1. Verify current schema version: SELECT schema_version FROM schema_metadata LIMIT 1;
--   2. Apply this migration.
--   3. Verify: SELECT schema_version FROM schema_metadata LIMIT 1; (should be 3)
--
-- Fresh-install note:
--   Fresh installations do not need this migration. The table is
--   included in setup/mtg_new.sql.
--
-- Existing table handling:
--   If scryfall_game_types already exists while
--   schema_metadata.version is still 2, treat this as a possible
--   partial or manual migration. Stop and investigate before
--   proceeding.
--
-- To run manually:
--   mysql -u <user> -p mtg_new < setup/schema_v003.sql
--

-- ============================================================
-- Set UTC time zone for deterministic timestamps
-- ============================================================

SET time_zone = '+00:00';

-- ============================================================
-- Create scryfall_game_types table
-- ============================================================

CREATE TABLE `scryfall_game_types` (
  `code` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `label` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `selected` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`code`),
  KEY `idx_scryfall_game_types_selected` (`selected`),
  KEY `idx_scryfall_game_types_sort` (`sort_order`, `code`),
  CONSTRAINT `chk_scryfall_game_types_code` CHECK (code REGEXP '^[a-z][a-z0-9]*$'),
  CONSTRAINT `chk_scryfall_game_types_selected` CHECK (selected IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Seed data: paper and arena selected, mtgo available
-- ============================================================

INSERT INTO `scryfall_game_types` (`code`, `label`, `sort_order`, `selected`) VALUES
  ('arena', 'MtG Arena', 10, 1),
  ('mtgo', 'MtG Online', 20, 0),
  ('paper', 'Paper', 30, 1);

-- ============================================================
-- Version bump
-- ============================================================

UPDATE schema_metadata SET schema_version = 3 WHERE id = 1 AND schema_version = 2;

SELECT 'Schema version bumped to 3. Migration complete.' AS info;

-- ============================================================
-- ROLLBACK (destructive -- use only before first use or with
-- verified backup)
-- ============================================================
-- DROP TABLE `scryfall_game_types`;
-- UPDATE schema_metadata SET schema_version = 2;
-- ============================================================
