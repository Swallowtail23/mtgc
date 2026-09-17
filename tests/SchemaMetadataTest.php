<?php

/*
Version:     1.3
Date:        17/09/26
Name:        SchemaMetadataTest.php
Purpose:     Verify schema_metadata table structure, singleton enforcement, and migration behavior.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tests for the schema_metadata table and migration bootstrap.
 *
 * Verifies that the fresh-install schema and upgrade migration
 * create a singleton metadata table with the correct baseline version.
 */
class SchemaMetadataTest extends TestCase
{
    /**
     * Migration file name (versioned naming convention).
     */
    private const MIGRATION_FILE = 'schema_v001.sql';

    /**
     * Extract the version number from a migration filename.
     *
     * Returns 0 if the filename does not match the expected pattern.
     */
    private function extractVersionFromFilename(string $filename): int
    {
        if (preg_match('/schema_v(\d{3})(?:_.+)?\.sql$/', basename($filename), $matches)) {
            return (int) $matches[1];
        }
        return 0;
    }

    /**
     * Get the path to a setup SQL file relative to APP_ROOT.
     */
    private function setupPath(string $fileName): string
    {
        return APP_ROOT . '/setup/' . $fileName;
    }

    /**
     * Discover the highest migration version across all schema_v*.sql files.
     */
    private function latestMigrationVersion(): int
    {
        $versions = [];
        foreach (glob(APP_ROOT . '/setup/schema_v*.sql') ?: [] as $path) {
            $version = $this->extractVersionFromFilename($path);
            if ($version > 0) {
                $versions[] = $version;
            }
        }
        self::assertNotEmpty($versions, 'At least one migration file must exist in setup/');
        return max($versions);
    }

    /**
     * Extract the schema_version value from the fresh-install seed INSERT.
     *
     * Returns 0 if the pattern is not found.
     */
    private function extractFreshInstallSchemaVersion(string $content): int
    {
        $pattern = '/INSERT INTO `schema_metadata`.*?VALUES\s*\(\s*1\s*,\s*(\d+)\s*\)/';
        preg_match($pattern, $content, $matches);
        if (empty($matches)) {
            return 0;
        }
        return (int) $matches[1];
    }

    /**
     * Test that the fresh-install schema contains the schema_metadata table.
     */
    public function testFreshInstallContainsSchemaMetadataTable(): void
    {
        $freshInstallPath = $this->setupPath('mtg_new.sql');
        self::assertFileExists($freshInstallPath);

        $content = file_get_contents($freshInstallPath);
        self::assertNotFalse($content);

        self::assertStringContainsString(
            'CREATE TABLE `schema_metadata`',
            $content,
            'The fresh-install schema must contain the schema_metadata table.'
        );
    }

    /**
     * Test that the fresh-install schema contains the baseline INSERT.
     */
    public function testFreshInstallContainsBaselineSeed(): void
    {
        $freshInstallPath = $this->setupPath('mtg_new.sql');
        self::assertFileExists($freshInstallPath);

        $content = file_get_contents($freshInstallPath);
        self::assertNotFalse($content);

        self::assertStringContainsString(
            'INSERT INTO `schema_metadata`',
            $content,
            'The fresh-install schema must seed the schema_metadata table.'
        );

        $expectedVersion = $this->latestMigrationVersion();
        $actualVersion = $this->extractFreshInstallSchemaVersion($content);

        self::assertGreaterThan(0, $actualVersion, 'The fresh-install schema must seed a positive schema_version.');
        self::assertSame(
            $expectedVersion,
            $actualVersion,
            'The fresh-install schema_version must match the highest migration filename version (expected '
            . $expectedVersion . ', got ' . $actualVersion . '). This catches stale schema when migrations are added.'
        );
    }

    /**
     * Test that the upgrade migration contains the schema_metadata table.
     */
    public function testMigrationContainsSchemaMetadataTable(): void
    {
        $upgradePath = $this->setupPath(self::MIGRATION_FILE);
        self::assertFileExists($upgradePath);

        $content = file_get_contents($upgradePath);
        self::assertNotFalse($content);

        self::assertStringContainsString(
            'CREATE TABLE `schema_metadata`',
            $content,
            'The upgrade migration must contain the schema_metadata table.'
        );
    }

    /**
     * Test that the upgrade migration contains the baseline INSERT.
     */
    public function testMigrationContainsBaselineSeed(): void
    {
        $upgradePath = $this->setupPath(self::MIGRATION_FILE);
        self::assertFileExists($upgradePath);

        $content = file_get_contents($upgradePath);
        self::assertNotFalse($content);

        self::assertStringContainsString(
            'INSERT INTO `schema_metadata`',
            $content,
            'The upgrade migration must seed the schema_metadata table.'
        );

        self::assertStringContainsString(
            '(1, 1)',
            $content,
            'The baseline row must have id=1 and schema_version=1.'
        );
    }

    /**
     * Test that the fresh-install schema_metadata table has the correct columns.
     */
    public function testFreshInstallSchemaMetadataColumns(): void
    {
        $freshInstallPath = $this->setupPath('mtg_new.sql');
        self::assertFileExists($freshInstallPath);

        $content = file_get_contents($freshInstallPath);
        self::assertNotFalse($content);

        // Extract the CREATE TABLE block
        $pattern = '/CREATE TABLE `schema_metadata` \(.*?\) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;/s';
        preg_match($pattern, $content, $matches);
        self::assertNotEmpty($matches, 'CREATE TABLE `schema_metadata` block not found in fresh-install schema.');

        $createTable = $matches[0];

        self::assertStringContainsString(
            '`id` INT UNSIGNED NOT NULL DEFAULT 1',
            $createTable,
            'id must be INT UNSIGNED NOT NULL DEFAULT 1.'
        );

        self::assertStringContainsString(
            '`schema_version` INT UNSIGNED NOT NULL DEFAULT 1',
            $createTable,
            'schema_version must be INT UNSIGNED NOT NULL DEFAULT 1.'
        );

        self::assertStringContainsString(
            '`updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            $createTable,
            'updated_at must be DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP.'
        );

        self::assertStringContainsString(
            'PRIMARY KEY (`id`)',
            $createTable,
            'id must be the PRIMARY KEY.'
        );

        self::assertStringContainsString(
            'CONSTRAINT `chk_schema_metadata_singleton` CHECK (`id` = 1)',
            $createTable,
            'The table must enforce singleton via a CHECK constraint on id=1.'
        );
    }

    /**
     * Test that the upgrade migration schema_metadata table has the correct columns.
     */
    public function testMigrationSchemaMetadataColumns(): void
    {
        $upgradePath = $this->setupPath(self::MIGRATION_FILE);
        self::assertFileExists($upgradePath);

        $content = file_get_contents($upgradePath);
        self::assertNotFalse($content);

        // Extract the CREATE TABLE block
        $pattern = '/CREATE TABLE `schema_metadata` \(.*?\) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;/s';
        preg_match($pattern, $content, $matches);
        self::assertNotEmpty($matches, 'CREATE TABLE `schema_metadata` block not found in upgrade migration.');

        $createTable = $matches[0];

        self::assertStringContainsString(
            '`id` INT UNSIGNED NOT NULL DEFAULT 1',
            $createTable,
            'id must be INT UNSIGNED NOT NULL DEFAULT 1.'
        );

        self::assertStringContainsString(
            '`schema_version` INT UNSIGNED NOT NULL DEFAULT 1',
            $createTable,
            'schema_version must be INT UNSIGNED NOT NULL DEFAULT 1.'
        );

        self::assertStringContainsString(
            '`updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            $createTable,
            'updated_at must be DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP.'
        );

        self::assertStringContainsString(
            'PRIMARY KEY (`id`)',
            $createTable,
            'id must be the PRIMARY KEY.'
        );

        self::assertStringContainsString(
            'CONSTRAINT `chk_schema_metadata_singleton` CHECK (`id` = 1)',
            $createTable,
            'The table must enforce singleton via a CHECK constraint on id=1.'
        );
    }

    /**
     * Test that the fresh-install and upgrade migration CREATE TABLE blocks are identical.
     */
    public function testFreshInstallAndUpgradeSchemaMetadataTableAreIdentical(): void
    {
        $freshInstallPath = $this->setupPath('mtg_new.sql');
        $upgradePath = $this->setupPath(self::MIGRATION_FILE);

        self::assertFileExists($freshInstallPath);
        self::assertFileExists($upgradePath);

        $freshPattern = '/CREATE TABLE `schema_metadata` \(.*?\) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;/s';
        $upgradePattern = '/CREATE TABLE `schema_metadata` \(.*?\) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;/s';

        preg_match($freshPattern, file_get_contents($freshInstallPath), $freshMatches);
        preg_match($upgradePattern, file_get_contents($upgradePath), $upgradeMatches);

        self::assertNotEmpty($freshMatches, 'CREATE TABLE block not found in fresh-install schema.');
        self::assertNotEmpty($upgradeMatches, 'CREATE TABLE block not found in upgrade migration.');

        self::assertSame(
            $upgradeMatches[0],
            $freshMatches[0],
            'The CREATE TABLE `schema_metadata` block must match between fresh-install and upgrade SQL.'
        );
    }

    /**
     * Test that the migration file name follows the versioned naming convention.
     */
    public function testMigrationFileFollowsVersionedNamingConvention(): void
    {
        $setupPath = $this->setupPath(self::MIGRATION_FILE);
        self::assertFileExists($setupPath);

        // The filename must match one of:
        //   schema_vNNN.sql       — version-only naming
        //   schema_vNNN_<name>.sql — versioned with a descriptive name
        self::assertMatchesRegularExpression(
            '/^schema_v\d{3}(_.+)?\.sql$/',
            self::MIGRATION_FILE,
            'The migration file must follow the schema_vNNN[_<name>].sql naming convention.'
        );
    }

    /**
     * Test that the migration contains a rollback section.
     */
    public function testMigrationContainsRollbackWarning(): void
    {
        $upgradePath = $this->setupPath(self::MIGRATION_FILE);
        self::assertFileExists($upgradePath);

        $content = file_get_contents($upgradePath);
        self::assertNotFalse($content);

        self::assertStringContainsStringIgnoringCase(
            'rollback',
            $content,
            'The migration must contain a rollback section.'
        );

        self::assertStringContainsString(
            'destructive',
            $content,
            'The rollback section must be marked as destructive.'
        );

        self::assertStringContainsString(
            'DROP TABLE',
            $content,
            'The rollback section must contain the DROP TABLE statement.'
        );
    }

    /**
     * Test that the migration documents its bundled release using a stable format.
     */
    public function testMigrationDocumentsBundledRelease(): void
    {
        $upgradePath = $this->setupPath(self::MIGRATION_FILE);
        self::assertFileExists($upgradePath);

        $content = file_get_contents($upgradePath);
        self::assertNotFalse($content);

        self::assertMatchesRegularExpression(
            '/^-- Bundled with release: v\d+\.\d+\.\d+$/m',
            $content,
            'The migration must document a semantic bundled release version.'
        );
    }

    /**
     * Test that the migration does not use CREATE TABLE IF NOT EXISTS.
     */
    public function testMigrationDoesNotUseIfNotExists(): void
    {
        $upgradePath = $this->setupPath(self::MIGRATION_FILE);
        self::assertFileExists($upgradePath);

        $content = file_get_contents($upgradePath);
        self::assertNotFalse($content);

        // Strip comments before checking
        $sqlOnly = preg_replace('/--.*$/m', '', $content);
        $sqlOnly = preg_replace('/\/\*.*?\*\//s', '', $sqlOnly);

        self::assertStringNotContainsString(
            'IF NOT EXISTS',
            $sqlOnly,
            'The migration must not use CREATE TABLE IF NOT EXISTS in executable SQL.'
        );
    }

    /**
     * Test that the migration notes MySQL DDL auto-commit behavior.
     */
    public function testMigrationNotesAutoCommit(): void
    {
        $upgradePath = $this->setupPath(self::MIGRATION_FILE);
        self::assertFileExists($upgradePath);

        $content = file_get_contents($upgradePath);
        self::assertNotFalse($content);

        self::assertStringContainsString(
            'DDL auto-commits',
            $content,
            'The migration must note that MySQL DDL auto-commits.'
        );
    }

    /**
     * Test that the migration sets UTC time zone for deterministic timestamps.
     */
    public function testMigrationSetsUtcTimezone(): void
    {
        $upgradePath = $this->setupPath(self::MIGRATION_FILE);
        self::assertFileExists($upgradePath);

        $content = file_get_contents($upgradePath);
        self::assertNotFalse($content);

        self::assertStringContainsString(
            "SET time_zone = '+00:00'",
            $content,
            'The migration must set the time zone to UTC for deterministic timestamps.'
        );
    }

    /**
     * Test that the schema_metadata table uses a singleton id=1 primary key.
     */
    public function testSchemaMetadataUsesSingletonId(): void
    {
        $freshInstallPath = $this->setupPath('mtg_new.sql');
        self::assertFileExists($freshInstallPath);

        $content = file_get_contents($freshInstallPath);
        self::assertNotFalse($content);

        // The table must have id as PRIMARY KEY with DEFAULT 1
        self::assertStringContainsString(
            'PRIMARY KEY (`id`)',
            $content,
            'schema_metadata must use id as PRIMARY KEY.'
        );

        $expectedVersion = $this->latestMigrationVersion();
        $actualVersion = $this->extractFreshInstallSchemaVersion($content);

        self::assertGreaterThan(0, $actualVersion, 'The fresh-install schema must seed a positive schema_version.');
        self::assertSame(
            $expectedVersion,
            $actualVersion,
            'The fresh-install schema_version must match the highest migration filename version (expected '
            . $expectedVersion . ', got ' . $actualVersion . '). This catches stale schema when migrations are added.'
        );
    }

    /**
     * Test that the schema_metadata table does not have AUTO_INCREMENT.
     *
     * A singleton table should not auto-increment — it must always have id=1.
     */
    public function testSchemaMetadataNoAutoIncrement(): void
    {
        $freshInstallPath = $this->setupPath('mtg_new.sql');
        self::assertFileExists($freshInstallPath);

        $content = file_get_contents($freshInstallPath);
        self::assertNotFalse($content);

        // Extract the CREATE TABLE block
        $pattern = '/CREATE TABLE `schema_metadata` \(.*?\) ENGINE=InnoDB/s';
        preg_match($pattern, $content, $matches);
        self::assertNotEmpty($matches, 'CREATE TABLE `schema_metadata` block not found.');

        $createTable = $matches[0];

        self::assertStringNotContainsString(
            'AUTO_INCREMENT',
            $createTable,
            'The schema_metadata table must not use AUTO_INCREMENT.'
        );
    }

    /**
     * Contract test: verify the CHECK constraint for singleton enforcement.
     */
    public function testSchemaMetadataCheckConstraintExists(): void
    {
        $freshInstallPath = $this->setupPath('mtg_new.sql');
        self::assertFileExists($freshInstallPath);

        $content = file_get_contents($freshInstallPath);
        self::assertNotFalse($content);

        self::assertStringContainsString(
            'CONSTRAINT `chk_schema_metadata_singleton` CHECK (`id` = 1)',
            $content,
            'The fresh-install schema must enforce singleton via a CHECK constraint.'
        );
    }

    /**
     * Contract test: verify the migration CHECK constraint for singleton enforcement.
     */
    public function testMigrationCheckConstraintExists(): void
    {
        $upgradePath = $this->setupPath(self::MIGRATION_FILE);
        self::assertFileExists($upgradePath);

        $content = file_get_contents($upgradePath);
        self::assertNotFalse($content);

        self::assertStringContainsString(
            'CONSTRAINT `chk_schema_metadata_singleton` CHECK (`id` = 1)',
            $content,
            'The upgrade migration must enforce singleton via a CHECK constraint.'
        );
    }
}
