<?php

/*
Version:     1.2
Date:        16/09/26
Name:        TestMysqli.php
Purpose:     Mock mysqli and mysqli_result classes for MigrationRunner testing.
Notes:       Simulates migration execution, version verification,
             maintenance mode state, and advisory locking.
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Tests;

/**
 * Mock mysqli_result for test fixtures.
 */
class TestMysqliResult extends \mysqli_result
{
    private array $row;

    public function __construct(array $row)
    {
        $this->row = $row;
    }

    public function fetch_assoc(): ?array
    {
        return empty($this->row) ? null : $this->row;
    }

    public function free(): void
    {
    }
}

/**
 * Mock mysqli for MigrationRunner tests.
 *
 * Simulates a MySQL database with schema_metadata table, admin table,
 * and advisory locking for testing the MigrationRunner service.
 */
class TestMysqli
{
    /**
     * Current error message (mimics mysqli::$error property).
     */
    public string $error = '';

    public bool $lockAcquired = false;
    public bool $maintenanceWasEnabled = false;
    public bool $maintenanceWasDisabled = false;

    /**
     * @param array<int, string> $migrations version => SQL content
     * @param int $currentVersion Current schema version in the database
     * @param int $maintenanceState Current mtce value (0 or 1)
     * @param bool $tableExists Whether schema_metadata table exists
     * @param bool $emptyTable Whether table exists but has no row
     * @param ?string $failAfterVersion Version after which to inject a failure
     */
    public function __construct(
        private readonly array $migrations,
        private int $currentVersion,
        private int $maintenanceState,
        private readonly bool $tableExists,
        private readonly bool $emptyTable,
        private readonly ?string $failAfterVersion,
    ) {
    }

    public function set_charset(string $charset): bool
    {
        return true;
    }

    public function connect_error(): ?string
    {
        return null;
    }

    public function query(string $query, int $result_mode = MYSQLI_STORE_RESULT): \mysqli_result|bool
    {
        $lower = strtolower($query);

        // Information schema: check for schema_metadata table.
        if (
            strpos($lower, 'information_schema.tables') !== false
            && strpos($lower, 'schema_metadata') !== false
        ) {
            return new TestMysqliResult(['c' => $this->tableExists ? 1 : 0]);
        }

        // SELECT schema_version FROM schema_metadata.
        if (
            strpos($lower, 'schema_metadata') !== false
            && strpos($lower, 'schema_version') !== false
            && strpos($lower, 'select') !== false
        ) {
            if ($this->emptyTable) {
                return new TestMysqliResult([]);
            }
            return new TestMysqliResult(['schema_version' => $this->currentVersion]);
        }

        // SELECT mtce FROM admin.
        if (strpos($lower, 'admin') !== false && strpos($lower, 'mtce') !== false) {
            return new TestMysqliResult(['mtce' => $this->maintenanceState]);
        }

        // SELECT GET_LOCK / RELEASE_LOCK.
        if (strpos($lower, 'get_lock') !== false) {
            $this->lockAcquired = true;
            return new TestMysqliResult(['locked' => 1]);
        }
        if (strpos($lower, 'release_lock') !== false) {
            return new TestMysqliResult(['release_lock' => 1]);
        }

        // UPDATE schema_metadata SET schema_version = N.
        if (
            strpos($lower, 'update') !== false
            && strpos($lower, 'schema_metadata') !== false
            && strpos($lower, 'schema_version') !== false
        ) {
            if (preg_match('/schema_version\s*=\s*(\d+)/i', $query, $matches)) {
                $this->currentVersion = (int) $matches[1];
            }
            return new TestMysqliResult([]);
        }

        // SET time_zone.
        if (strpos($lower, 'set time_zone') !== false) {
            return true;
        }

        // SELECT 1 (generic).
        if (preg_match('/^select\s+/i', $query)) {
            return new TestMysqliResult(['schema_version' => $this->currentVersion]);
        }

        // Generic UPDATE.
        if (strpos($lower, 'update') !== false) {
            return new TestMysqliResult([]);
        }

        // Generic CREATE TABLE.
        if (strpos($lower, 'create table') !== false) {
            return true;
        }

        $this->error = 'Unmocked query: ' . $query;
        return false;
    }

    /**
     * Simulate multi_query execution.
     */
    public function multi_query(string $query): bool
    {
        // Check if this migration should fail.
        $bumpedVersion = null;
        if (preg_match('/schema_version\s*=\s*(\d+)/i', $query, $matches)) {
            $bumpedVersion = (int) $matches[1];
        }

        // If we should fail after this version, inject an error.
        if ($this->failAfterVersion !== null && $bumpedVersion === (int) $this->failAfterVersion) {
            $this->error = "Simulated failure after version $this->failAfterVersion";
            return false;
        }

        // Execute version bumps and SET time_zone.
        if (preg_match('/schema_version\s*=\s*(\d+)/i', $query, $matches)) {
            $this->currentVersion = (int) $matches[1];
        }
        if (strpos(strtolower($query), 'set time_zone') !== false) {
            // No-op.
        }

        return true;
    }

    public function more_results(): bool
    {
        return false;
    }

    public function next_result(): bool
    {
        return false;
    }

    public function store_result(): ?TestMysqliResult
    {
        return null;
    }

    public function errno(): int
    {
        return $this->error !== '' ? 1 : 0;
    }

    /**
     * Mock prepared statement for maintenance mode toggle.
     *
     * Tracks maintenance mode state directly on this instance.
     */
    public function prepare(string $query): object|false
    {
        if (
            strpos(strtolower($query), 'update admin') !== false
            && strpos(strtolower($query), 'mtce') !== false
        ) {
            // First call is 'on', subsequent is 'off'.
            if (!$this->maintenanceWasEnabled) {
                $this->maintenanceWasEnabled = true;
            } else {
                $this->maintenanceWasDisabled = true;
            }
            return new class {
                public function bind_param(string $types, mixed &...$vars): bool
                {
                    return true;
                }
                public function execute(?array $params = null): bool
                {
                    return true;
                }
                public function close(): void
                {
                }
            };
        }
        $this->error = 'Unmocked prepare: ' . $query;
        return false;
    }

    public function close(): void
    {
    }
}
