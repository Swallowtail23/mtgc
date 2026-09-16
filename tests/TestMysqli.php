<?php

/*
Version:     2.0
Date:        16/09/26
Name:        TestMysqli.php
Purpose:     Stateful mysqli test double for MigrationRunner tests.
Notes:       Simulates schema metadata, maintenance mode, advisory locking,
             prepared admin updates, and ordered multi-statement execution.
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Tests;

/**
 * Minimal result object used by TestMysqli.
 */
class TestMysqliResult
{
    public function __construct(private readonly ?array $row)
    {
    }

    public function fetch_assoc(): ?array
    {
        return $this->row;
    }

    public function free(): void
    {
    }
}

/**
 * Prepared-statement double for AdminSettings::setMaintenanceMode().
 */
class TestMysqliStatement
{
    public string $error = '';

    private mixed $boundValue = null;

    public function __construct(private readonly TestMysqli $db)
    {
    }

    public function bind_param(string $types, mixed &...$vars): bool
    {
        if ($vars === []) {
            $this->error = 'No maintenance value was bound';
            return false;
        }

        $this->boundValue =& $vars[0];
        return true;
    }

    public function execute(?array $params = null): bool
    {
        $value = (int) $this->boundValue;
        $success = $this->db->applyMaintenanceUpdate($value);
        if (!$success) {
            $this->error = $this->db->error;
        }
        return $success;
    }

    public function close(): void
    {
    }
}

/**
 * Stateful mysqli-compatible test double for MigrationRunner.
 */
class TestMysqli
{
    public string $error = '';
    public int $currentVersion;
    public int $maintenanceState;

    public bool $lockAcquired = false;
    public bool $lockReleaseAttempted = false;
    public bool $lockReleased = false;
    public bool $maintenanceWasEnabled = false;
    public bool $maintenanceWasDisabled = false;

    /** @var list<int> */
    public array $executedMigrationVersions = [];

    /** @var list<string> */
    public array $executedStatements = [];

    /** @var 1|0|null|false */
    public int|false|null $lockResult = 1;

    public bool $adminQueryFails = false;
    public bool $adminRowAbsent = false;
    public ?int $adminInvalidMtce = null;
    public bool $prepareFails = false;
    public ?int $maintenanceExecuteFailsFor = null;
    public ?int $maintenanceUpdateIgnoredFor = null;
    public bool $lockReleaseThrows = false;
    public ?int $multiQueryFailsForVersion = null;
    public ?int $failAtStatement = null;
    public bool $throwOnStatementFailure = false;

    /** @var list<string> */
    private array $multiStatements = [];

    private int $nextStatementIndex = 0;

    public function __construct(
        int $currentVersion,
        int $maintenanceState,
        private bool $tableExists,
        private bool $metadataRowExists,
    ) {
        $this->currentVersion = $currentVersion;
        $this->maintenanceState = $maintenanceState;
    }

    public function set_charset(string $charset): bool
    {
        return true;
    }

    public function query(string $query, int $resultMode = MYSQLI_STORE_RESULT): TestMysqliResult|bool
    {
        $this->error = '';
        $lower = strtolower($query);

        if (str_contains($lower, 'get_lock')) {
            if ($this->lockResult === false) {
                $this->error = 'Lock query failed';
                return false;
            }
            if ($this->lockResult === null) {
                return new TestMysqliResult(null);
            }
            $this->lockAcquired = $this->lockResult === 1;
            return new TestMysqliResult(['locked' => $this->lockResult]);
        }

        if (str_contains($lower, 'release_lock')) {
            $this->lockReleaseAttempted = true;
            if ($this->lockReleaseThrows) {
                throw new \mysqli_sql_exception('Simulated lock release failure', 1205);
            }
            $this->lockReleased = true;
            return new TestMysqliResult(['release_lock' => 1]);
        }

        if (str_contains($lower, 'information_schema.tables')) {
            return new TestMysqliResult(['c' => $this->tableExists ? 1 : 0]);
        }

        if (
            str_contains($lower, 'select schema_version')
            && str_contains($lower, 'schema_metadata')
        ) {
            if (!$this->tableExists || !$this->metadataRowExists) {
                return new TestMysqliResult(null);
            }
            return new TestMysqliResult(['schema_version' => $this->currentVersion]);
        }

        if (str_contains($lower, 'select mtce') && str_contains($lower, 'from admin')) {
            if ($this->adminQueryFails) {
                $this->error = 'Admin query failed';
                return false;
            }
            if ($this->adminRowAbsent) {
                return new TestMysqliResult(null);
            }
            $value = $this->adminInvalidMtce ?? $this->maintenanceState;
            return new TestMysqliResult(['mtce' => $value]);
        }

        if (str_contains($lower, 'set time_zone')) {
            return true;
        }

        $this->error = 'Unmocked query: ' . $query;
        return false;
    }

    public function multi_query(string $query): bool
    {
        $this->error = '';
        $this->multiStatements = array_values(array_filter(
            array_map('trim', explode(';', $query)),
            static fn(string $statement): bool => $statement !== ''
        ));
        $this->nextStatementIndex = 0;

        $version = $this->extractUpdatedVersion($query);
        if ($version !== null && $version === $this->multiQueryFailsForVersion) {
            $this->error = "Simulated failure before version $version";
            return false;
        }

        if ($this->multiStatements === []) {
            return true;
        }

        return $this->executeNextStatement();
    }

    public function more_results(): bool
    {
        return $this->nextStatementIndex < count($this->multiStatements);
    }

    public function next_result(): bool
    {
        if (!$this->more_results()) {
            return false;
        }
        return $this->executeNextStatement();
    }

    public function store_result(): ?TestMysqliResult
    {
        return null;
    }

    public function prepare(string $query): TestMysqliStatement|false
    {
        if ($this->prepareFails) {
            $this->error = 'Simulated prepare failure';
            return false;
        }
        if (!str_contains(strtolower($query), 'update admin set mtce')) {
            $this->error = 'Unmocked prepare: ' . $query;
            return false;
        }
        return new TestMysqliStatement($this);
    }

    public function applyMaintenanceUpdate(int $value): bool
    {
        if ($value !== 0 && $value !== 1) {
            $this->error = "Invalid maintenance value: $value";
            return false;
        }
        if ($this->maintenanceExecuteFailsFor === $value) {
            $this->error = "Simulated maintenance update failure for value $value";
            return false;
        }
        if ($this->maintenanceUpdateIgnoredFor !== $value) {
            $this->maintenanceState = $value;
        }
        if ($value === 1) {
            $this->maintenanceWasEnabled = true;
        } else {
            $this->maintenanceWasDisabled = true;
        }
        return true;
    }

    public function close(): void
    {
    }

    private function executeNextStatement(): bool
    {
        $statementNumber = $this->nextStatementIndex + 1;
        $statement = $this->multiStatements[$this->nextStatementIndex];
        $this->nextStatementIndex++;

        if ($this->failAtStatement === $statementNumber) {
            $this->error = "Simulated failure at statement $statementNumber";
            if ($this->throwOnStatementFailure) {
                throw new \mysqli_sql_exception($this->error, 1064);
            }
            return false;
        }

        $this->error = '';
        $this->executedStatements[] = $statement;

        if (preg_match('/CREATE\s+TABLE\s+`?schema_metadata`?/i', $statement)) {
            if ($this->tableExists) {
                $this->error = 'Table schema_metadata already exists';
                return false;
            }
            $this->tableExists = true;
            $this->metadataRowExists = false;
            return true;
        }

        if (preg_match('/INSERT\s+INTO\s+`?schema_metadata`?.*VALUES\s*\(\s*1\s*,\s*(\d+)\s*\)/is', $statement, $matches)) {
            if (!$this->tableExists) {
                $this->error = 'Table schema_metadata does not exist';
                return false;
            }
            $this->currentVersion = (int) $matches[1];
            $this->metadataRowExists = true;
            $this->executedMigrationVersions[] = $this->currentVersion;
            return true;
        }

        $version = $this->extractUpdatedVersion($statement);
        if ($version !== null) {
            if (!$this->tableExists || !$this->metadataRowExists) {
                $this->error = 'schema_metadata row does not exist';
                return false;
            }
            $this->currentVersion = $version;
            $this->executedMigrationVersions[] = $version;
        }

        return true;
    }

    private function extractUpdatedVersion(string $sql): ?int
    {
        $matched = preg_match(
            '/UPDATE\s+`?schema_metadata`?\s+SET\s+`?schema_version`?\s*=\s*(\d+)/i',
            $sql,
            $matches
        );
        if ($matched !== 1) {
            return null;
        }
        return (int) $matches[1];
    }
}
