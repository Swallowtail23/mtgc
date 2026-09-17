<?php

/*
Version:     1.0
Date:        17/09/26
Name:        SchemaApiKeysTest.php
Purpose:     Verify user_api_keys table structure in both the migration file and fresh-install schema.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tests for the user_api_keys table schema.
 *
 * Verifies that the fresh-install schema and upgrade migration
 * create the user_api_keys table with correct columns, indexes,
 * foreign key, and collation settings.
 */
class SchemaApiKeysTest extends TestCase
{
    /**
     * Migration file name (versioned naming convention).
     */
    private const MIGRATION_FILE = 'schema_v002.sql';

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
     * Extract a CREATE TABLE block from SQL content.
     *
     * @return array<string, string> ['content' => ..., 'name' => ...] or empty array
     */
    private function extractCreateTable(string $content, string $tableName): array
    {
        $pattern = '/CREATE TABLE `' . preg_quote($tableName, '/') . '` \(.*?\) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;/s';
        preg_match($pattern, $content, $matches);
        if (empty($matches)) {
            return [];
        }
        return ['content' => $matches[0], 'name' => $tableName];
    }

    // ------------------------------------------------------------------
    // Presence tests
    // ------------------------------------------------------------------

    public function testFreshInstallContainsUserApiKeysTable(): void
    {
        $freshInstallPath = $this->setupPath('mtg_new.sql');
        self::assertFileExists($freshInstallPath);

        $content = file_get_contents($freshInstallPath);
        self::assertNotFalse($content);

        self::assertStringContainsString(
            'CREATE TABLE `user_api_keys`',
            $content,
            'The fresh-install schema must contain the user_api_keys table.'
        );
    }

    public function testMigrationContainsUserApiKeysTable(): void
    {
        $upgradePath = $this->setupPath(self::MIGRATION_FILE);
        self::assertFileExists($upgradePath);

        $content = file_get_contents($upgradePath);
        self::assertNotFalse($content);

        self::assertStringContainsString(
            'CREATE TABLE `user_api_keys`',
            $content,
            'The upgrade migration must contain the user_api_keys table.'
        );
    }

    public function testFreshInstallSchemaMetadataVersionMatchesLatestMigration(): void
    {
        $freshInstallPath = $this->setupPath('mtg_new.sql');
        self::assertFileExists($freshInstallPath);

        $content = file_get_contents($freshInstallPath);
        self::assertNotFalse($content);

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

    // ------------------------------------------------------------------
    // Column tests
    // ------------------------------------------------------------------

    public function testFreshInstallUserApiKeysColumns(): void
    {
        $freshInstallPath = $this->setupPath('mtg_new.sql');
        self::assertFileExists($freshInstallPath);

        $content = file_get_contents($freshInstallPath);
        self::assertNotFalse($content);

        $createTable = $this->extractCreateTable($content, 'user_api_keys');
        self::assertNotEmpty($createTable, 'CREATE TABLE `user_api_keys` block not found in fresh-install schema.');

        $createTableContent = $createTable['content'];

        $columns = [
            '`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
            '`usernumber` SMALLINT NOT NULL',
            '`key_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL',
            '`key_prefix` VARCHAR(23) CHARACTER SET ascii COLLATE ascii_bin NOT NULL',
            '`label` VARCHAR(128) DEFAULT NULL',
            '`scope` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT \'collection:read\'',
            '`created_at` DATETIME NOT NULL',
            '`last_used_at` DATETIME DEFAULT NULL',
            '`expires_at` DATETIME DEFAULT NULL',
            '`revoked_at` DATETIME DEFAULT NULL',
        ];

        foreach ($columns as $column) {
            self::assertStringContainsString(
                $column,
                $createTableContent,
                "Column definition not found: $column"
            );
        }
    }

    public function testMigrationUserApiKeysColumns(): void
    {
        $upgradePath = $this->setupPath(self::MIGRATION_FILE);
        self::assertFileExists($upgradePath);

        $content = file_get_contents($upgradePath);
        self::assertNotFalse($content);

        $createTable = $this->extractCreateTable($content, 'user_api_keys');
        self::assertNotEmpty($createTable, 'CREATE TABLE `user_api_keys` block not found in upgrade migration.');

        $createTableContent = $createTable['content'];

        $columns = [
            '`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
            '`usernumber` SMALLINT NOT NULL',
            '`key_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL',
            '`key_prefix` VARCHAR(23) CHARACTER SET ascii COLLATE ascii_bin NOT NULL',
            '`label` VARCHAR(128) DEFAULT NULL',
            '`scope` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT \'collection:read\'',
            '`created_at` DATETIME NOT NULL',
            '`last_used_at` DATETIME DEFAULT NULL',
            '`expires_at` DATETIME DEFAULT NULL',
            '`revoked_at` DATETIME DEFAULT NULL',
        ];

        foreach ($columns as $column) {
            self::assertStringContainsString(
                $column,
                $createTableContent,
                "Column definition not found: $column"
            );
        }
    }

    // ------------------------------------------------------------------
    // Index tests
    // ------------------------------------------------------------------

    public function testFreshInstallUserApiKeysIndexes(): void
    {
        $freshInstallPath = $this->setupPath('mtg_new.sql');
        self::assertFileExists($freshInstallPath);

        $content = file_get_contents($freshInstallPath);
        self::assertNotFalse($content);

        $createTable = $this->extractCreateTable($content, 'user_api_keys');
        self::assertNotEmpty($createTable, 'CREATE TABLE `user_api_keys` block not found in fresh-install schema.');

        $createTableContent = $createTable['content'];

        self::assertStringContainsString(
            'PRIMARY KEY (`id`)',
            $createTableContent,
            'Primary key on id must be defined.'
        );

        self::assertStringContainsString(
            'UNIQUE KEY `uq_user_api_keys_hash` (`key_hash`)',
            $createTableContent,
            'Unique key on key_hash must be defined.'
        );

        self::assertStringContainsString(
            'KEY `idx_user_api_keys_owner` (`usernumber`, `revoked_at`)',
            $createTableContent,
            'Composite index on (usernumber, revoked_at) must be defined.'
        );

        self::assertStringContainsString(
            'KEY `idx_user_api_keys_last_used` (`last_used_at`)',
            $createTableContent,
            'Index on last_used_at must be defined.'
        );
    }

    public function testMigrationUserApiKeysIndexes(): void
    {
        $upgradePath = $this->setupPath(self::MIGRATION_FILE);
        self::assertFileExists($upgradePath);

        $content = file_get_contents($upgradePath);
        self::assertNotFalse($content);

        $createTable = $this->extractCreateTable($content, 'user_api_keys');
        self::assertNotEmpty($createTable, 'CREATE TABLE `user_api_keys` block not found in upgrade migration.');

        $createTableContent = $createTable['content'];

        self::assertStringContainsString(
            'PRIMARY KEY (`id`)',
            $createTableContent,
            'Primary key on id must be defined.'
        );

        self::assertStringContainsString(
            'UNIQUE KEY `uq_user_api_keys_hash` (`key_hash`)',
            $createTableContent,
            'Unique key on key_hash must be defined.'
        );

        self::assertStringContainsString(
            'KEY `idx_user_api_keys_owner` (`usernumber`, `revoked_at`)',
            $createTableContent,
            'Composite index on (usernumber, revoked_at) must be defined.'
        );

        self::assertStringContainsString(
            'KEY `idx_user_api_keys_last_used` (`last_used_at`)',
            $createTableContent,
            'Index on last_used_at must be defined.'
        );
    }

    // ------------------------------------------------------------------
    // Foreign key tests
    // ------------------------------------------------------------------

    public function testFreshInstallUserApiKeysForeignKey(): void
    {
        $freshInstallPath = $this->setupPath('mtg_new.sql');
        self::assertFileExists($freshInstallPath);

        $content = file_get_contents($freshInstallPath);
        self::assertNotFalse($content);

        self::assertStringContainsString(
            'ADD CONSTRAINT `fk_user_api_keys_user`',
            $content,
            'The fresh-install schema must define the FK constraint via ALTER TABLE.'
        );

        self::assertStringContainsString(
            'FOREIGN KEY (`usernumber`) REFERENCES `users` (`usernumber`)',
            $content,
            'The FK must reference users.usernumber.'
        );

        self::assertStringContainsString(
            'ON DELETE CASCADE',
            $content,
            'The FK must have ON DELETE CASCADE.'
        );
    }

    public function testMigrationUserApiKeysForeignKey(): void
    {
        $upgradePath = $this->setupPath(self::MIGRATION_FILE);
        self::assertFileExists($upgradePath);

        $content = file_get_contents($upgradePath);
        self::assertNotFalse($content);

        $createTable = $this->extractCreateTable($content, 'user_api_keys');
        self::assertNotEmpty($createTable, 'CREATE TABLE `user_api_keys` block not found in upgrade migration.');

        $createTableContent = $createTable['content'];

        self::assertStringContainsString(
            'CONSTRAINT `fk_user_api_keys_user`',
            $createTableContent,
            'The migration must define the FK constraint inline.'
        );

        self::assertStringContainsString(
            'FOREIGN KEY (`usernumber`) REFERENCES `users` (`usernumber`)',
            $createTableContent,
            'The FK must reference users.usernumber.'
        );

        self::assertStringContainsString(
            'ON DELETE CASCADE',
            $createTableContent,
            'The FK must have ON DELETE CASCADE.'
        );
    }

    // ------------------------------------------------------------------
    // Collation tests
    // ------------------------------------------------------------------

    public function testFreshInstallUserApiKeysAsciiCollation(): void
    {
        $freshInstallPath = $this->setupPath('mtg_new.sql');
        self::assertFileExists($freshInstallPath);

        $content = file_get_contents($freshInstallPath);
        self::assertNotFalse($content);

        $createTable = $this->extractCreateTable($content, 'user_api_keys');
        self::assertNotEmpty($createTable, 'CREATE TABLE `user_api_keys` block not found in fresh-install schema.');

        $createTableContent = $createTable['content'];

        self::assertStringContainsString(
            'CHARACTER SET ascii COLLATE ascii_bin',
            $createTableContent,
            'ASCII binary collation must be used for key_hash, key_prefix, and scope.'
        );
    }

    public function testMigrationUserApiKeysAsciiCollation(): void
    {
        $upgradePath = $this->setupPath(self::MIGRATION_FILE);
        self::assertFileExists($upgradePath);

        $content = file_get_contents($upgradePath);
        self::assertNotFalse($content);

        $createTable = $this->extractCreateTable($content, 'user_api_keys');
        self::assertNotEmpty($createTable, 'CREATE TABLE `user_api_keys` block not found in upgrade migration.');

        $createTableContent = $createTable['content'];

        self::assertStringContainsString(
            'CHARACTER SET ascii COLLATE ascii_bin',
            $createTableContent,
            'ASCII binary collation must be used for key_hash, key_prefix, and scope.'
        );
    }

    // ------------------------------------------------------------------
    // Migration structure tests
    // ------------------------------------------------------------------

    public function testMigrationContainsVersionBump(): void
    {
        $upgradePath = $this->setupPath(self::MIGRATION_FILE);
        self::assertFileExists($upgradePath);

        $content = file_get_contents($upgradePath);
        self::assertNotFalse($content);

        $expectedVersion = $this->extractVersionFromFilename($upgradePath);
        self::assertGreaterThan(0, $expectedVersion, 'The migration filename must encode a positive version.');

        $previousVersion = $expectedVersion - 1;

        self::assertStringContainsString(
            'UPDATE schema_metadata SET schema_version = ' . $expectedVersion,
            $content,
            'The migration must bump schema_version to ' . $expectedVersion . '.'
        );

        self::assertStringContainsString(
            'WHERE id = 1 AND schema_version = ' . $previousVersion,
            $content,
            'The version bump must use optimistic locking (WHERE schema_version = ' . $previousVersion . ').'
        );
    }

    public function testMigrationContainsRollbackSection(): void
    {
        $upgradePath = $this->setupPath(self::MIGRATION_FILE);
        self::assertFileExists($upgradePath);

        $content = file_get_contents($upgradePath);
        self::assertNotFalse($content);

        self::assertStringContainsString(
            'ROLLBACK',
            $content,
            'The migration must contain a commented rollback section.'
        );

        self::assertStringContainsString(
            'DROP TABLE `user_api_keys`',
            $content,
            'The rollback must include DROP TABLE user_api_keys.'
        );
    }

    public function testMigrationContainsUtcSetup(): void
    {
        $upgradePath = $this->setupPath(self::MIGRATION_FILE);
        self::assertFileExists($upgradePath);

        $content = file_get_contents($upgradePath);
        self::assertNotFalse($content);

        self::assertStringContainsString(
            "SET time_zone = '+00:00'",
            $content,
            'The migration must set UTC timezone.'
        );
    }

    // ------------------------------------------------------------------
    // Fresh-install FK uniqueness tests (regression: catch duplicate FK)
    // ------------------------------------------------------------------

    public function testFreshInstallDefinesFkExactlyOnce(): void
    {
        $freshInstallPath = $this->setupPath('mtg_new.sql');
        self::assertFileExists($freshInstallPath);

        $content = file_get_contents($freshInstallPath);
        self::assertNotFalse($content);

        // Count occurrences of the FK constraint definition
        $count = substr_count($content, 'ADD CONSTRAINT `fk_user_api_keys_user`');
        self::assertSame(
            1,
            $count,
            'The fresh-install schema must define fk_user_api_keys_user exactly once via ADD CONSTRAINT.'
        );
    }

    public function testFreshInstallDoesNotDefineFkInline(): void
    {
        $freshInstallPath = $this->setupPath('mtg_new.sql');
        self::assertFileExists($freshInstallPath);

        $content = file_get_contents($freshInstallPath);
        self::assertNotFalse($content);

        $createTable = $this->extractCreateTable($content, 'user_api_keys');
        self::assertNotEmpty($createTable, 'CREATE TABLE `user_api_keys` block not found in fresh-install schema.');

        $createTableContent = $createTable['content'];

        self::assertStringNotContainsString(
            'CONSTRAINT `fk_user_api_keys_user`',
            $createTableContent,
            'The CREATE TABLE block must NOT define the FK inline. Use ALTER TABLE instead.'
        );
    }
}
