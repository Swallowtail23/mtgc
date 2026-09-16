<?php

/*
Version:     1.0
Date:        16/09/26
Name:        MigrationRunner.php
Purpose:     Testable schema migration runner service.
Notes:       Handles migration discovery, preflight validation, version gap
             detection, per-migration execution with error checking, advisory
             locking for concurrent runner prevention, and maintenance state
             preservation/restoration.
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Bulk;

use MTG\Admin\AdminSettings;
use MTG\Core\AppConfig;

/**
 * Schema migration runner service.
 *
 * @phpstan-type MigrationEntry array{version: int, file: string}
 */
class MigrationRunner
{
    /**
     * Migration file naming regex — exactly three zero-padded digits, optional suffix.
     */
    private const VERSION_PATTERN = '/^schema_v(\d{3})(?:_.+)?\.sql$/';

    /**
     * Advisory lock name for concurrent runner prevention.
     */
    private const LOCK_NAME = 'mtg_schema_migration';

    /**
     * Lock wait timeout in seconds.
     */
    private const LOCK_WAIT_TIMEOUT = 10;

    /**
     * @param \mysqli|object $db
     */
    public function __construct(
        private readonly object $db,
        private readonly AppConfig $appConfig,
        private readonly string $setupDir,
    ) {
    }

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    /**
     * Run all pending migrations.
     *
     * Returns an array of applied version numbers.
     *
     * @return list<int>
     * @throws \Exception on fatal preflight or execution errors
     */
    public function run(): array
    {
        $lockAcquired = $this->acquireLock();
        try {
            $preflight = $this->preflight();

            // Nothing to do — database is current.
            if ($preflight->noWork) {
                return [];
            }

            // Nothing to do — no migration files found but database has version.
            if (empty($preflight->migrations)) {
                throw new \Exception('No migration files found but database is at version ' . $preflight->currentVersion);
            }

            // No migrations to apply (gap-free but already applied).
            if (empty($preflight->pending)) {
                return [];
            }

            // Preserve original maintenance state so we can restore it.
            $originalMaintenanceState = $this->readMaintenanceState();

            // Enable maintenance mode before applying changes.
            $wasAlreadyOn = $originalMaintenanceState === 1;
            if (!$wasAlreadyOn) {
                AdminSettings::setMaintenanceMode('on', $this->db, $this->appConfig);
            }

            try {
                $applied = $this->applyMigrations($preflight->pending);

                // Verify final version matches expected.
                $this->verifyFinalVersion($preflight->latestVersion);

                // Disable maintenance mode only if we turned it on.
                if (!$wasAlreadyOn) {
                    $ok = AdminSettings::setMaintenanceMode('off', $this->db, $this->appConfig);
                    if (!$ok) {
                        throw new \Exception('Failed to disable maintenance mode after migrations');
                    }
                }

                return $applied;
            } catch (\Throwable $e) {
                // Leave maintenance mode ON — site stays protected.
                fwrite(STDERR, "Migration failed. Maintenance mode remains enabled.\n");
                throw $e;
            }
        } finally {
            if ($lockAcquired) {
                $this->releaseLock();
            }
        }
    }

    // ------------------------------------------------------------------
    // Preflight
    // ------------------------------------------------------------------

    /**
     * @return MigrationPreflight
     */
    private function preflight(): MigrationPreflight
    {
        $migrations = $this->discoverMigrations();
        $latestVersion = max(array_keys($migrations));

        $result = new MigrationPreflight();
        $result->latestVersion = $latestVersion;

        // Check schema_metadata table exists.
        $tableCheck = $this->db->query(
            "SELECT COUNT(*) AS c FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'schema_metadata'"
        );
        if ($tableCheck === false) {
            throw new \Exception('Unable to check for schema_metadata table');
        }
        $tableRow = $tableCheck->fetch_assoc();
        $tableExists = (int) ($tableRow['c'] ?? 0) > 0;

        if ($tableExists) {
            $verCheck = $this->db->query('SELECT schema_version FROM schema_metadata LIMIT 1');
            if ($verCheck === false) {
                throw new \Exception('Unable to read current schema version');
            }
            $verRow = $verCheck->fetch_assoc();
            if ($verRow === null || !isset($verRow['schema_version'])) {
                // Table exists but has no row — corrupt state.
                throw new \Exception(
                    'schema_metadata table exists but has no row. '
                    . 'This indicates corruption. Restore from backup or manually INSERT INTO schema_metadata (id, schema_version) VALUES (1, <version>).'
                );
            }
            $result->currentVersion = (int) $verRow['schema_version'];
        } else {
            $result->currentVersion = 0;
        }

        // Already at latest — no work needed.
        if ($result->currentVersion === $latestVersion) {
            $result->noWork = true;
            return $result;
        }

        // Database version exceeds available migrations.
        if ($result->currentVersion > $latestVersion) {
            throw new \Exception(
                "Database schema version ({$result->currentVersion}) is newer than the latest migration ({$latestVersion}). "
                . 'The database may need to be restored from a backup.'
            );
        }

        // Detect duplicates (warn already emitted during discovery).
        if (count($migrations) !== $latestVersion) {
            $duplicates = [];
            for ($v = 1; $v <= $latestVersion; $v++) {
                if (!isset($migrations[$v])) {
                    $duplicates[] = $v;
                }
            }
            if (!empty($duplicates)) {
                throw new \Exception(
                    'Duplicate or missing migration versions detected: '
                    . implode(', ', $duplicates) . '. Remove duplicate files or add missing migrations.'
                );
            }
        }

        // Build pending list and check for gaps.
        $pending = [];
        for ($v = $result->currentVersion + 1; $v <= $latestVersion; $v++) {
            if (!isset($migrations[$v])) {
                $available = implode(', ', array_keys($migrations));
                throw new \Exception(
                    "Missing migration for version $v (gap detected). Available versions: $available"
                );
            }
            $pending[$v] = $migrations[$v];
        }

        $result->migrations = $migrations;
        $result->pending = $pending;
        return $result;
    }

    // ------------------------------------------------------------------
    // Migration discovery
    // ------------------------------------------------------------------

    /**
     * @return array<int, string> version => filepath
     */
    private function discoverMigrations(): array
    {
        $files = glob($this->setupDir . '/schema_v*.sql');
        if ($files === false || $files === []) {
            throw new \Exception('No migration files found matching schema_v*.sql in ' . $this->setupDir);
        }
        sort($files);

        $migrations = [];
        foreach ($files as $file) {
            $basename = basename($file);
            if (!preg_match(self::VERSION_PATTERN, $basename, $matches)) {
                // Skip non-matching files silently.
                continue;
            }
            $version = (int) $matches[1];
            if (isset($migrations[$version])) {
                $existing = basename($migrations[$version]);
                fwrite(STDERR, "Warning: Duplicate migration version $version — using $basename (ignoring $existing)\n");
                throw new \Exception(
                    "Duplicate migration version $version: $basename and $existing. "
                    . 'Remove or rename one of the files.'
                );
            }
            $migrations[$version] = $file;
        }

        if (empty($migrations)) {
            throw new \Exception('No valid migration files found in ' . $this->setupDir);
        }

        return $migrations;
    }

    // ------------------------------------------------------------------
    // Migration execution
    // ------------------------------------------------------------------

    /**
     * @param array<int, string> $pending
     * @return list<int>
     */
    private function applyMigrations(array $pending): array
    {
        $applied = [];

        foreach ($pending as $expectedVersion => $file) {
            echo "Applying migration: " . basename($file) . " (version $expectedVersion)\n";

            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new \Exception('Unable to read migration file ' . $file);
            }

            // Set UTC time zone for deterministic timestamps.
            $this->db->query("SET time_zone = '+00:00'");

            // Execute with try/catch for strict mysqli mode.
            try {
                $success = $this->db->multi_query($sql);
            } catch (\mysqli_sql_exception $e) {
                fwrite(STDERR, "Error: Failed to execute migration " . basename($file) . "\n");
                fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
                throw $e;
            }

            if ($success === false) {
                throw new \Exception(
                    "Failed to execute migration " . basename($file) . ": " . $this->db->error
                );
            }

            // Drain all result sets and check for errors on each statement.
            $hasError = false;
            do {
                if ($result = $this->db->store_result()) {
                    $result->free();
                }
                if ($this->db->error !== '') {
                    $hasError = true;
                    break;
                }
                // Move to next statement; check error after next_result().
                if ($this->db->more_results()) {
                    $nextOk = $this->db->next_result();
                    if ($nextOk === false && $this->db->error !== '') {
                        $hasError = true;
                        break;
                    }
                }
            } while ($this->db->more_results());

            if ($hasError) {
                throw new \Exception(
                    "Migration " . basename($file) . " failed during execution: " . $this->db->error
                );
            }

            // Per-migration version verification.
            $verifyRow = $this->db->query('SELECT schema_version FROM schema_metadata LIMIT 1');
            if ($verifyRow === false) {
                throw new \Exception('Unable to verify schema version after migration ' . basename($file));
            }
            $vr = $verifyRow->fetch_assoc();
            if ($vr === null || !isset($vr['schema_version'])) {
                throw new \Exception(
                    "Migration " . basename($file) . " did not update schema_metadata. "
                    . 'The migration must bump schema_version.'
                );
            }
            $actualVersion = (int) $vr['schema_version'];
            if ($actualVersion !== $expectedVersion) {
                throw new \Exception(
                    "Migration " . basename($file) . " left schema_version at $actualVersion, expected $expectedVersion"
                );
            }

            $applied[] = $expectedVersion;
            echo "  -> Applied successfully.\n";
        }

        return $applied;
    }

    /**
     * Verify final schema version matches the expected latest version.
     */
    private function verifyFinalVersion(int $expectedVersion): void
    {
        $verify = $this->db->query('SELECT schema_version FROM schema_metadata LIMIT 1');
        if ($verify === false) {
            throw new \Exception('Unable to verify schema version after migrations');
        }
        $verRow = $verify->fetch_assoc();
        if ($verRow === null || !isset($verRow['schema_version'])) {
            throw new \Exception('schema_metadata table has no row after migrations');
        }
        $finalVersion = (int) $verRow['schema_version'];

        if ($finalVersion !== $expectedVersion) {
            throw new \Exception(
                "Schema version mismatch — expected $expectedVersion but got $finalVersion. "
                . 'Migrations may have been partially applied. Check database state.'
            );
        }
    }

    // ------------------------------------------------------------------
    // Advisory locking
    // ------------------------------------------------------------------

    private function acquireLock(): bool
    {
        $result = $this->db->query(
            "SELECT GET_LOCK('" . self::LOCK_NAME . "', " . self::LOCK_WAIT_TIMEOUT . ') AS locked'
        );
        if ($result === false) {
            return false;
        }
        $row = $result->fetch_assoc();
        return (int) ($row['locked'] ?? 0) === 1;
    }

    private function releaseLock(): void
    {
        $this->db->query("SELECT RELEASE_LOCK('" . self::LOCK_NAME . "')");
    }

    // ------------------------------------------------------------------
    // Maintenance state preservation
    // ------------------------------------------------------------------

    /**
     * Read the current mtce value from the admin table.
     *
     * @return int 0 or 1, or -1 on error
     */
    private function readMaintenanceState(): int
    {
        $result = $this->db->query('SELECT mtce FROM admin LIMIT 1');
        if ($result === false) {
            return -1;
        }
        $row = $result->fetch_assoc();
        return (int) ($row['mtce'] ?? 0);
    }
}

/**
 * Holds preflight results for the migration runner.
 */
class MigrationPreflight
{
    /** @var int Current schema version from the database */
    public int $currentVersion = 0;

    /** @var int Latest available migration version */
    public int $latestVersion = 0;

    /** @var bool True when database is already at the latest version */
    public bool $noWork = false;

    /** @var array<int, string> All discovered migrations (version => filepath) */
    public array $migrations = [];

    /** @var array<int, string> Pending migrations to apply (version => filepath) */
    public array $pending = [];
}
