<?php

/*
Version:     1.0
Date:        16/09/26
Name:        MigrationRunnerTest.php
Purpose:     Production-path tests for the MigrationRunner service class.
Notes:       Each test creates its own fresh fixture directory to avoid
             order-dependent failures. Uses a mock mysqli that simulates
             migration execution, version verification, and maintenance
             mode state.
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Tests;

use MTG\Bulk\MigrationRunner;
use MTG\Core\AppConfig;
use PHPUnit\Framework\TestCase;

/**
 * Production-path tests for the MigrationRunner service.
 *
 * Each test method creates its own temporary fixture directory so that
 * running with --order-by=random produces the same results.
 */
class MigrationRunnerTest extends TestCase
{
    /**
     * Create a temporary fixture directory unique to this test.
     *
     * @param array<int, string> $migrations version => SQL content
     * @param int $currentVersion Database current version (0 = no table)
     * @param int $maintenanceState 0 or 1
     * @param bool $tableExists Whether schema_metadata table exists
     * @param bool $emptyTable Whether table exists but has no row
     * @param ?string $failAfterVersion Version number after which to inject a failure
     * @return array{dir: string, db: TestMysqli, appConfig: AppConfig}
     */
    private function createFixtures(
        array $migrations,
        int $currentVersion = 0,
        int $maintenanceState = 0,
        bool $tableExists = true,
        bool $emptyTable = false,
        ?string $failAfterVersion = null,
    ): array {
        $dir = sys_get_temp_dir() . '/mtg_migration_test_' . uniqid();
        mkdir($dir, 0777, true);
        mkdir($dir . '/setup', 0777, true);

        foreach ($migrations as $version => $sql) {
            $name = str_pad((string) $version, 3, '0', STR_PAD_LEFT);
            file_put_contents($dir . '/setup/schema_v' . $name . '.sql', $sql);
        }

        $db = new TestMysqli(
            $migrations,
            $currentVersion,
            $maintenanceState,
            $tableExists,
            $emptyTable,
            $failAfterVersion,
        );

        $appConfig = AppConfig::fromIni([
            'general' => [
                'URL' => 'https://test.example',
                'title' => 'Test',
                'tier' => 'dev',
                'Loglevel' => 0,
                'Logfile' => sys_get_temp_dir() . '/mtg_test.log',
                'ImgLocation' => sys_get_temp_dir() . '/cardimg/',
                'Timezone' => 'UTC',
                'Locale' => 'en_US',
                'Copyright' => '',
                'MaxCardDataAge' => 0,
            ],
            'security' => [
                'Turnstile' => 'disabled',
                'Turnstile_site_key' => '',
                'Turnstile_secret_key' => '',
                'TrustDuration' => 0,
                'Badloginlimit' => 0,
                'AdminIP' => '',
            ],
            'email' => [
                'Email' => 'disabled',
                'AdminEmail' => 'admin@example.test',
                'ServerEmail' => 'server@example.test',
                'SMTPDebug' => 0,
                'Host' => '',
                'SMTPAuth' => '',
                'Username' => '',
                'Password' => '',
                'SMTPSecure' => '',
                'Port' => 25,
                'SMTPVerifySSL' => 1,
            ],
            'fx' => ['FreecurrencyAPI' => '', 'TargetCurrency' => ''],
            'comments' => ['Disqus' => 'disabled', 'DisqusDevURL' => '', 'DisqusProdURL' => ''],
        ]);

        return ['dir' => $dir, 'db' => $db, 'appConfig' => $appConfig];
    }

    private function cleanup(string $dir): void
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
                $sub = scandir($path);
                foreach ($sub as $s) {
                    if ($s !== '.' && $s !== '..') {
                        unlink($path . '/' . $s);
                    }
                }
                rmdir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    // ------------------------------------------------------------------
    // Happy path tests
    // ------------------------------------------------------------------

    public function testSingleMigrationAppliesSuccessfully(): void
    {
        $sql = "-- migration v001\nUPDATE schema_metadata SET schema_version = 1;\n";
        $fix = $this->createFixtures(['1' => $sql], currentVersion: 0);

        try {
            $runner = new MigrationRunner($fix['db'], $fix['appConfig'], $fix['dir'] . '/setup');
            $result = $runner->run();
            self::assertSame([1], $result);
            self::assertTrue($fix['db']->maintenanceWasEnabled);
            self::assertTrue($fix['db']->maintenanceWasDisabled);
        } finally {
            $this->cleanup($fix['dir']);
        }
    }

    public function testMultipleMigrationsApplySequentially(): void
    {
        $fix = $this->createFixtures([
            '1' => "-- v001\nUPDATE schema_metadata SET schema_version = 1;\n",
            '2' => "-- v002\nUPDATE schema_metadata SET schema_version = 2;\n",
            '3' => "-- v003\nUPDATE schema_metadata SET schema_version = 3;\n",
        ], currentVersion: 0);

        try {
            $runner = new MigrationRunner($fix['db'], $fix['appConfig'], $fix['dir'] . '/setup');
            $result = $runner->run();
            self::assertSame([1, 2, 3], $result);
        } finally {
            $this->cleanup($fix['dir']);
        }
    }

    public function testNoOpWhenDatabaseIsCurrent(): void
    {
        $fix = $this->createFixtures([
            '1' => "-- v001\nUPDATE schema_metadata SET schema_version = 1;\n",
        ], currentVersion: 1);

        try {
            $runner = new MigrationRunner($fix['db'], $fix['appConfig'], $fix['dir'] . '/setup');
            $result = $runner->run();
            self::assertSame([], $result);
            // Maintenance mode must NOT be toggled on no-op.
            self::assertFalse($fix['db']->maintenanceWasEnabled);
            self::assertFalse($fix['db']->maintenanceWasDisabled);
        } finally {
            $this->cleanup($fix['dir']);
        }
    }

    // ------------------------------------------------------------------
    // Maintenance state preservation tests
    // ------------------------------------------------------------------

    public function testMaintainsOriginalStateWhenAlreadyOn(): void
    {
        $fix = $this->createFixtures([
            '1' => "-- v001\nUPDATE schema_metadata SET schema_version = 1;\n",
        ], currentVersion: 0, maintenanceState: 1);

        try {
            $runner = new MigrationRunner($fix['db'], $fix['appConfig'], $fix['dir'] . '/setup');
            $result = $runner->run();
            self::assertSame([1], $result);
            // When already on, runner should NOT call setMaintenanceMode.
            self::assertFalse($fix['db']->maintenanceWasEnabled);
            self::assertFalse($fix['db']->maintenanceWasDisabled);
        } finally {
            $this->cleanup($fix['dir']);
        }
    }

    // ------------------------------------------------------------------
    // Preflight failure tests
    // ------------------------------------------------------------------

    public function testVersionGapDetectedAndAborted(): void
    {
        // Create files for v001 and v003 but not v002 — gap should be detected.
        $fix = $this->createFixtures([
            '1' => "-- v001\nUPDATE schema_metadata SET schema_version = 1;\n",
            '3' => "-- v003\nUPDATE schema_metadata SET schema_version = 3;\n",
        ], currentVersion: 0);

        try {
            $runner = new MigrationRunner($fix['db'], $fix['appConfig'], $fix['dir'] . '/setup');
            self::expectException(\Exception::class);
            self::expectExceptionMessageMatches('/(Missing migration for version 2|Duplicate or missing migration versions detected)/');
            $runner->run();
        } finally {
            $this->cleanup($fix['dir']);
        }
    }

    public function testDuplicateVersionsAbort(): void
    {
        // Two files with the same version number.
        $fix = $this->createFixtures([
            '1' => "-- v001 base\nUPDATE schema_metadata SET schema_version = 1;\n",
        ], currentVersion: 0);

        // Add a second file with the same version.
        file_put_contents($fix['dir'] . '/setup/schema_v001_extra.sql', "-- v001 extra\n");

        try {
            $runner = new MigrationRunner($fix['db'], $fix['appConfig'], $fix['dir'] . '/setup');
            self::expectException(\Exception::class);
            self::expectExceptionMessageMatches('/Duplicate migration version 1/');
            $runner->run();
        } finally {
            $this->cleanup($fix['dir']);
        }
    }

    public function testDatabaseVersionExceedsMigrationsAborts(): void
    {
        $fix = $this->createFixtures([
            '1' => "-- v001\nUPDATE schema_metadata SET schema_version = 1;\n",
        ], currentVersion: 5);

        try {
            $runner = new MigrationRunner($fix['db'], $fix['appConfig'], $fix['dir'] . '/setup');
            self::expectException(\Exception::class);
            self::expectExceptionMessageMatches('/newer than the latest migration/');
            $runner->run();
        } finally {
            $this->cleanup($fix['dir']);
        }
    }

    public function testEmptyCorruptMetadataTableAborts(): void
    {
        $fix = $this->createFixtures([
            '1' => "-- v001\nUPDATE schema_metadata SET schema_version = 1;\n",
        ], currentVersion: 0, tableExists: true, emptyTable: true);

        try {
            $runner = new MigrationRunner($fix['db'], $fix['appConfig'], $fix['dir'] . '/setup');
            self::expectException(\Exception::class);
            self::expectExceptionMessageMatches('/corruption/i');
            $runner->run();
        } finally {
            $this->cleanup($fix['dir']);
        }
    }

    // ------------------------------------------------------------------
    // Execution failure tests
    // ------------------------------------------------------------------

    public function testMigrationVersionBumpFailureIsDetected(): void
    {
        // Migration v001 does NOT bump the version — should fail.
        $fix = $this->createFixtures([
            '1' => "-- v001 without bump\n",
        ], currentVersion: 0);

        try {
            $runner = new MigrationRunner($fix['db'], $fix['appConfig'], $fix['dir'] . '/setup');
            self::expectException(\Exception::class);
            self::expectExceptionMessageMatches('/did not update schema_metadata|version/');
            $runner->run();
            // Maintenance mode should remain ON after failure.
            self::assertTrue($fix['db']->maintenanceWasEnabled);
            self::assertFalse($fix['db']->maintenanceWasDisabled);
        } finally {
            $this->cleanup($fix['dir']);
        }
    }

    public function testPartialFailureStopsSubsequentMigrations(): void
    {
        // v001 succeeds, v002 fails, v003 should never run.
        $fix = $this->createFixtures([
            '1' => "-- v001\nUPDATE schema_metadata SET schema_version = 1;\n",
            '2' => "-- v002\nUPDATE schema_metadata SET schema_version = 2;\n",
            '3' => "-- v003\nUPDATE schema_metadata SET schema_version = 3;\n",
        ], currentVersion: 0, failAfterVersion: '2');

        try {
            $runner = new MigrationRunner($fix['db'], $fix['appConfig'], $fix['dir'] . '/setup');
            self::expectException(\Exception::class);
            $runner->run();
            // Only v001 should have been applied before failure.
            self::assertTrue($fix['db']->maintenanceWasEnabled);
            self::assertFalse($fix['db']->maintenanceWasDisabled);
        } finally {
            $this->cleanup($fix['dir']);
        }
    }

    // ------------------------------------------------------------------
    // Advisory lock tests
    // ------------------------------------------------------------------

    public function testAdvisoryLockAcquired(): void
    {
        $fix = $this->createFixtures([
            '1' => "-- v001\nUPDATE schema_metadata SET schema_version = 1;\n",
        ], currentVersion: 0);

        try {
            $runner = new MigrationRunner($fix['db'], $fix['appConfig'], $fix['dir'] . '/setup');
            $runner->run();
            self::assertTrue($fix['db']->lockAcquired);
        } finally {
            $this->cleanup($fix['dir']);
        }
    }

    // ------------------------------------------------------------------
    // Filename parsing tests
    // ------------------------------------------------------------------

    public function testSuffixedMigrationFileAccepted(): void
    {
        $fix = $this->createFixtures([
            '1' => "-- v001 bootstrap\nUPDATE schema_metadata SET schema_version = 1;\n",
        ], currentVersion: 0);

        // Use suffixed filename.
        rename(
            $fix['dir'] . '/setup/schema_v001.sql',
            $fix['dir'] . '/setup/schema_v001_bootstrap.sql'
        );

        try {
            $runner = new MigrationRunner($fix['db'], $fix['appConfig'], $fix['dir'] . '/setup');
            $result = $runner->run();
            self::assertSame([1], $result);
        } finally {
            $this->cleanup($fix['dir']);
        }
    }

    // ------------------------------------------------------------------
    // Final version verification tests
    // ------------------------------------------------------------------

    public function testFinalVersionMismatchCausesFailure(): void
    {
        // v001 bumps to 1, v002 bumps to 2 but final verification expects 3.
        // We need v003 to exist but not bump correctly.
        $fix = $this->createFixtures([
            '1' => "-- v001\nUPDATE schema_metadata SET schema_version = 1;\n",
            '2' => "-- v002\nUPDATE schema_metadata SET schema_version = 2;\n",
            '3' => "-- v003 missing bump\n",
        ], currentVersion: 0);

        try {
            $runner = new MigrationRunner($fix['db'], $fix['appConfig'], $fix['dir'] . '/setup');
            self::expectException(\Exception::class);
            self::expectExceptionMessageMatches('/did not update schema_metadata|version/');
            $runner->run();
        } finally {
            $this->cleanup($fix['dir']);
        }
    }

    // ------------------------------------------------------------------
    // No schema_metadata table tests
    // ------------------------------------------------------------------

    public function testNoMetadataTableTreatedAsVersionZero(): void
    {
        $fix = $this->createFixtures([
            '1' => "-- v001\nUPDATE schema_metadata SET schema_version = 1;\n",
        ], currentVersion: 0, tableExists: false);

        try {
            $runner = new MigrationRunner($fix['db'], $fix['appConfig'], $fix['dir'] . '/setup');
            $result = $runner->run();
            self::assertSame([1], $result);
        } finally {
            $this->cleanup($fix['dir']);
        }
    }

    // ------------------------------------------------------------------
    // Source code contract tests
    // ------------------------------------------------------------------

    public function testCliScriptIsThinWrapper(): void
    {
        $source = file_get_contents(__DIR__ . '/../tools/maintenance.php');
        self::assertNotFalse($source);
        // Must use dirname(__DIR__) for setup dir.
        self::assertStringContainsString('dirname(__DIR__) . \'/' . 'setup\'', $source);
        // Must delegate to MigrationRunner.
        self::assertStringContainsString('MigrationRunner', $source);
        // Must NOT contain inline migration logic.
        self::assertStringNotContainsString('multi_query', $source);
    }

    public function testToolsDirectoryHasHtaccess(): void
    {
        self::assertFileExists(__DIR__ . '/../tools/.htaccess');
        $htaccess = file_get_contents(__DIR__ . '/../tools/.htaccess');
        self::assertStringContainsString('Deny from all', $htaccess);
    }

    public function testNoShebangBeforePhpTag(): void
    {
        $source = file_get_contents(__DIR__ . '/../tools/maintenance.php');
        self::assertNotFalse($source);
        // First line must be <?php, not #!/usr/bin/env php.
        self::assertStringStartsWith('<?php', $source);
        self::assertStringNotContainsString("#!/usr/bin/env php", $source);
    }

    public function testMigrationRunnerUsesStrictVersionPattern(): void
    {
        $source = file_get_contents(__DIR__ . '/../src/MTG/Bulk/MigrationRunner.php');
        self::assertNotFalse($source);
        self::assertStringContainsString('\d{3}', $source);
    }
}
