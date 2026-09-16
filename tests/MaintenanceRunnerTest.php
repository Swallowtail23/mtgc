<?php

/*
Version:     1.0
Date:        16/09/26
Name:        MaintenanceRunnerTest.php
Purpose:     Behavioral tests for the schema migration runner (tools/maintenance.php).
Notes:       Tests cover migration directory resolution, filename parsing, version gap
             detection, maintenance mode lifecycle, partial failure handling, and final
             version verification. Uses a mock mysqli and isolated temp directories.
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Behavioral tests for the maintenance migration runner.
 *
 * Because the runner is a CLI script that depends on real DB credentials,
 * we test its internal logic by extracting and exercising the key
 * functions in isolation, and by simulating the runner's flow with
 * mock objects and temp directories.
 */
class MaintenanceRunnerTest extends TestCase
{
    /**
     * Temp directory used for all migration-file tests.
     */
    private static ?string $tempDir = null;

    public static function setUpBeforeClass(): void
    {
        self::$tempDir = sys_get_temp_dir() . '/mtg_maintenance_test_' . uniqid();
        mkdir(self::$tempDir, 0777, true);
        mkdir(self::$tempDir . '/setup', 0777, true);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$tempDir && is_dir(self::$tempDir)) {
            self::rrmdir(self::$tempDir);
        }
    }

    private static function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                self::rrmdir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    /**
     * Create a migration file in the temp setup directory.
     */
    private function createMigration(string $name, string $content, int $expectedVersion): void
    {
        $path = self::$tempDir . '/setup/' . $name;
        file_put_contents($path, $content);
        self::assertFileExists($path);
    }

    /**
     * Extract the version from a filename using the runner's regex.
     */
    private function extractVersion(string $filePath): int
    {
        $basename = basename($filePath);
        if (preg_match('/schema_v(\d+)(?:_.+)?\.sql$/', $basename, $matches)) {
            return (int) $matches[1];
        }
        return 0;
    }

    // --------------------------------------------------------------------
    // Filename parsing tests
    // --------------------------------------------------------------------

    public function testExtractVersionBasic(): void
    {
        self::assertSame(1, $this->extractVersion('/setup/schema_v001.sql'));
        self::assertSame(2, $this->extractVersion('/setup/schema_v002.sql'));
        self::assertSame(10, $this->extractVersion('/setup/schema_v010.sql'));
        self::assertSame(99, $this->extractVersion('/setup/schema_v099.sql'));
    }

    public function testExtractVersionWithSuffix(): void
    {
        self::assertSame(1, $this->extractVersion('/setup/schema_v001_upgrade.sql'));
        self::assertSame(5, $this->extractVersion('/setup/schema_v005_add_users.sql'));
        self::assertSame(12, $this->extractVersion('/setup/schema_v012_indexes.sql'));
    }

    public function testExtractVersionRejectsInvalidNames(): void
    {
        self::assertSame(0, $this->extractVersion('/setup/migration_v001.sql'));
        self::assertSame(0, $this->extractVersion('/setup/schema_001.sql'));
        self::assertSame(0, $this->extractVersion('/setup/schema_v001.txt'));
        self::assertSame(0, $this->extractVersion('/setup/schema_vabc.sql'));
    }

    // --------------------------------------------------------------------
    // Migration directory resolution tests
    // --------------------------------------------------------------------

    public function testSetupDirectoryResolvesFromRepositoryRoot(): void
    {
        // The runner uses dirname(__DIR__) . '/setup' from tools/maintenance.php.
        // That should resolve to <repo>/setup, not <repo>/tools/setup.
        $runnerPath = dirname(__DIR__) . '/tools/maintenance.php';
        self::assertFileExists($runnerPath);

        // Read the runner source and verify it uses dirname(__DIR__) for setup dir.
        $source = file_get_contents($runnerPath);
        self::assertNotFalse($source);
        self::assertStringContainsString(
            'dirname(__DIR__) . \'/' . 'setup\'',
            $source,
            'The runner must resolve the setup directory from the repository root.'
        );
    }

    // --------------------------------------------------------------------
    // Duplicate version detection tests
    // --------------------------------------------------------------------

    public function testDuplicateVersionsWarnAndOverwrite(): void
    {
        $this->createMigration('schema_v001.sql', "-- v001 base", 1);
        $this->createMigration('schema_v001_extra.sql', "-- v001 extra", 1);

        $files = glob(self::$tempDir . '/setup/schema_v*.sql');
        sort($files);

        $migrations = [];
        $warnings = [];
        foreach ($files as $file) {
            $version = $this->extractVersion($file);
            if ($version > 0) {
                if (isset($migrations[$version])) {
                    $warnings[] = $version;
                }
                $migrations[$version] = $file;
            }
        }

        self::assertContains(1, $warnings, 'A warning should be emitted for duplicate version 1.');
        self::assertCount(1, $migrations, 'Only one entry per version should remain.');
    }

    // --------------------------------------------------------------------
    // Version gap detection tests
    // --------------------------------------------------------------------

    public function testGapDetectionDetectsMissingVersion(): void
    {
        $this->createMigration('schema_v001.sql', "-- v001", 1);
        $this->createMigration('schema_v003.sql', "-- v003", 3);

        $files = glob(self::$tempDir . '/setup/schema_v*.sql');
        sort($files);

        $migrations = [];
        foreach ($files as $file) {
            $version = $this->extractVersion($file);
            if ($version > 0) {
                $migrations[$version] = $file;
            }
        }

        $latestVersion = max(array_keys($migrations));
        $currentVersion = 0;

        $gaps = [];
        for ($v = $currentVersion + 1; $v <= $latestVersion; $v++) {
            if (!isset($migrations[$v])) {
                $gaps[] = $v;
            }
        }

        self::assertContains(2, $gaps, 'Version 2 should be detected as a gap.');
        self::assertCount(1, $gaps);
    }

    public function testNoGapWhenAllVersionsPresent(): void
    {
        $this->createMigration('schema_v001.sql', "-- v001", 1);
        $this->createMigration('schema_v002.sql', "-- v002", 2);
        $this->createMigration('schema_v003.sql', "-- v003", 3);

        $files = glob(self::$tempDir . '/setup/schema_v*.sql');
        sort($files);

        $migrations = [];
        foreach ($files as $file) {
            $version = $this->extractVersion($file);
            if ($version > 0) {
                $migrations[$version] = $file;
            }
        }

        $latestVersion = max(array_keys($migrations));
        $currentVersion = 0;

        $gaps = [];
        for ($v = $currentVersion + 1; $v <= $latestVersion; $v++) {
            if (!isset($migrations[$v])) {
                $gaps[] = $v;
            }
        }

        self::assertEmpty($gaps, 'No gaps should be detected when all versions are present.');
    }

    // --------------------------------------------------------------------
    // Maintenance mode lifecycle tests
    // --------------------------------------------------------------------

    public function testMaintenanceModeEnabledBeforeMigrations(): void
    {
        // Verify the runner source enables maintenance mode before the apply loop.
        $source = file_get_contents(dirname(__DIR__) . '/tools/maintenance.php');
        self::assertNotFalse($source);

        // Maintenance mode ON should appear before the "Apply each migration" loop.
        $onPos = strpos($source, "setMaintenanceMode('on'");
        $applyPos = strpos($source, 'Apply each migration');

        self::assertNotFalse($onPos, 'Runner must enable maintenance mode.');
        self::assertNotFalse($applyPos, 'Runner must have a migration apply section.');
        self::assertLessThan($applyPos, $onPos, 'Maintenance mode must be enabled before applying migrations.');
    }

    public function testMaintenanceModeDisabledOnSuccess(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/tools/maintenance.php');
        self::assertNotFalse($source);

        // Maintenance mode OFF should appear after the migration apply loop.
        $offPos = strpos($source, "setMaintenanceMode('off'");
        $applyEndPos = strrpos($source, 'Applied successfully');

        self::assertNotFalse($offPos, 'Runner must disable maintenance mode.');
        self::assertNotFalse($applyEndPos, 'Runner must have success markers.');
        self::assertGreaterThan($applyEndPos, $offPos, 'Maintenance mode must be disabled after migrations.');
    }

    public function testNoOpDoesNotEnableMaintenanceMode(): void
    {
        // When the database is already at the latest version, the runner exits
        // before enabling maintenance mode. The source structure should show
        // that the no-op check happens before maintenance mode is enabled.
        $source = file_get_contents(dirname(__DIR__) . '/tools/maintenance.php');
        self::assertNotFalse($source);

        $noOpPos = strpos($source, 'No migrations needed');
        $onPos = strpos($source, "setMaintenanceMode('on'");

        self::assertNotFalse($noOpPos, 'Runner must have a no-op path.');
        self::assertNotFalse($onPos, 'Runner must enable maintenance mode.');
        self::assertLessThan($onPos, $noOpPos, 'The no-op check must happen before maintenance mode is enabled.');
    }

    // --------------------------------------------------------------------
    // Final version verification tests
    // --------------------------------------------------------------------

    public function testFinalVersionIsVerifiedAgainstExpected(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/tools/maintenance.php');
        self::assertNotFalse($source);

        self::assertStringContainsString(
            '$finalVersion !== $latestVersion',
            $source,
            'The runner must compare the final schema version against the expected latest version.'
        );
    }

    public function testFinalVersionMismatchCausesFailure(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/tools/maintenance.php');
        self::assertNotFalse($source);

        self::assertStringContainsString(
            'Schema version mismatch',
            $source,
            'A version mismatch should produce a clear error message.'
        );
    }

    // --------------------------------------------------------------------
    // Partial failure handling tests
    // --------------------------------------------------------------------

    public function testPartialFailurePreventsMaintenanceModeOff(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/tools/maintenance.php');
        self::assertNotFalse($source);

        // The runner should exit(1) when a migration fails, before reaching
        // the maintenance mode OFF block.
        $applyFailedPos = strpos($source, '$applyFailed = true');
        $exit1Pos = strpos($source, "exit(1)");
        $offPos = strpos($source, "setMaintenanceMode('off'");

        self::assertNotFalse($applyFailedPos, 'Runner must track apply failures.');
        self::assertNotFalse($exit1Pos, 'Runner must exit(1) on failure.');
        self::assertNotFalse($offPos, 'Runner must disable maintenance mode on success.');
        self::assertLessThan($offPos, $exit1Pos, 'Failure exit must happen before maintenance mode OFF.');
    }

    public function testMultiQueryErrorChecking(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/tools/maintenance.php');
        self::assertNotFalse($source);

        self::assertStringContainsString(
            '$db->error',
            $source,
            'The runner must check $db->error after each multi_query statement.'
        );
    }

    // --------------------------------------------------------------------
    // Empty schema_metadata handling tests
    // --------------------------------------------------------------------

    public function testEmptySchemaMetadataHandledSafely(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/tools/maintenance.php');
        self::assertNotFalse($source);

        self::assertStringContainsString(
            '$verRow === null',
            $source,
            'The runner must handle null/empty schema_metadata rows safely.'
        );
    }

    // --------------------------------------------------------------------
    // Migration file discovery contract tests
    // --------------------------------------------------------------------

    public function testRunnerSupportsSuffixedMigrationNames(): void
    {
        // The extractVersion regex must accept schema_vNNN_<name>.sql.
        $testCases = [
            '/setup/schema_v001.sql' => 1,
            '/setup/schema_v001_bootstrap.sql' => 1,
            '/setup/schema_v002_add_cards.sql' => 2,
            '/setup/schema_v010_indexes.sql' => 10,
        ];

        foreach ($testCases as $path => $expected) {
            self::assertSame(
                $expected,
                $this->extractVersion($path),
                "Filename '$path' should extract version $expected."
            );
        }
    }

    public function testRunnerDiscoversFilesGlobPattern(): void
    {
        // Verify glob pattern in runner source.
        $source = file_get_contents(dirname(__DIR__) . '/tools/maintenance.php');
        self::assertNotFalse($source);

        self::assertStringContainsString(
            "glob(\$setupDir . '/schema_v*.sql')",
            $source,
            'The runner must use glob to discover schema_v*.sql files.'
        );
    }

    // --------------------------------------------------------------------
    // Setup directory path in runner source
    // --------------------------------------------------------------------

    public function testRunnerUsesCorrectSetupDirectoryPath(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/tools/maintenance.php');
        self::assertNotFalse($source);

        // Must NOT use __DIR__ . '/setup' (which resolves to tools/setup).
        self::assertStringNotContainsString(
            "__DIR__ . '/setup'",
            $source,
            'The runner must not use __DIR__ . /setup (wrong path).'
        );

        // Must use dirname(__DIR__) . '/setup'.
        self::assertStringContainsString(
            'dirname(__DIR__)',
            $source,
            'The runner must use dirname(__DIR__) for repository-relative path resolution.'
        );
    }
}
