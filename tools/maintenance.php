#!/usr/bin/env php
<?php

/*
Version:     1.3
Date:        16/09/26
Name:        maintenance.php
Purpose:     Apply schema update migrations from the CLI.
Notes:       CLI-only entry point. Reads DB credentials from the INI file,
             discovers available migrations in setup/, and applies them
             sequentially from the current schema version up to the latest.
             Supports migration file naming patterns:
               - schema_vNNN.sql
               - schema_vNNN_<name>.sql
             Preflight checks run before maintenance mode is enabled.
             Maintenance mode is only enabled immediately before applying
             migrations, and disabled only on successful completion.
             Verifies the final schema version matches the expected version.
             Errors on version gaps (e.g. v001 applied without v002).
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

// CLI-only guard
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

// Load Composer autoloader for app classes
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Resolve INI path (same as bootstrap.php)
$iniPath = getenv('MTG_INI_PATH');
if ($iniPath === false || $iniPath === '') {
    $iniPath = '/opt/mtg/mtg_new.ini';
}

if (!file_exists($iniPath)) {
    fwrite(STDERR, "Error: INI file not found at $iniPath\n");
    exit(1);
}

// Parse INI for database credentials
$iniData = parse_ini_file($iniPath, true);
if ($iniData === false) {
    fwrite(STDERR, "Error: Unable to parse INI file at $iniPath\n");
    exit(1);
}

$dbHost = $iniData['database']['DBServer'] ?? '';
$dbUser = $iniData['database']['DBUser'] ?? '';
$dbPass = $iniData['database']['DBPass'] ?? '';
$dbName = $iniData['database']['DBName'] ?? '';

if ($dbHost === '' || $dbUser === '' || $dbName === '') {
    fwrite(STDERR, "Error: Missing database credentials in INI file\n");
    exit(1);
}

// Build AppConfig from INI data (required for AdminSettings::setMaintenanceMode)
$appConfig = \MTG\Core\AppConfig::fromIni($iniData);

// Connect to MySQL
$db = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($db->connect_error) {
    fwrite(STDERR, "Error: Failed to connect to MySQL: " . $db->connect_error . "\n");
    exit(1);
}
$db->set_charset('utf8mb4');

// Determine the setup directory (repository root's setup/ subdirectory)
$setupDir = dirname(__DIR__) . '/setup';
if (!is_dir($setupDir)) {
    fwrite(STDERR, "Error: Setup directory not found at $setupDir\n");
    exit(1);
}

// Discover available migration files (schema_vNNN.sql or schema_vNNN_<name>.sql)
$files = glob($setupDir . '/schema_v*.sql');
if ($files === false || empty($files)) {
    fwrite(STDERR, "Error: No migration files found matching schema_v*.sql in $setupDir\n");
    exit(1);
}

// Sort files by name (version number) to ensure correct order
sort($files);

// Extract version number from filename.
// Supports: schema_v001.sql and schema_v001_upgrade.sql
function extractVersion(string $filePath): int
{
    $basename = basename($filePath);
    if (preg_match('/schema_v(\d+)(?:_.+)?\.sql$/', $basename, $matches)) {
        return (int) $matches[1];
    }
    return 0;
}

// Build a version => file map.
// If duplicate versions exist (same NNN in multiple files), warn and keep the last one.
$migrations = [];
foreach ($files as $file) {
    $version = extractVersion($file);
    if ($version > 0) {
        if (isset($migrations[$version])) {
            $existing = basename($migrations[$version]);
            $current = basename($file);
            fwrite(STDERR, "Warning: Duplicate migration version $version — using $current (overwriting $existing)\n");
        }
        $migrations[$version] = $file;
    }
}

if (empty($migrations)) {
    fwrite(STDERR, "Error: No valid migration files found in $setupDir\n");
    exit(1);
}

// Determine the latest available version
$latestVersion = max(array_keys($migrations));

// Determine the current schema version from the database
$currentVersion = 0;
$hasSchemaMetadata = false;

$checkTable = $db->query("SELECT COUNT(*) AS c FROM information_schema.TABLES
                          WHERE TABLE_SCHEMA = DATABASE()
                            AND TABLE_NAME = 'schema_metadata'");
if ($checkTable === false) {
    fwrite(STDERR, "Error: Unable to check for existing schema_metadata table\n");
    exit(1);
}
$row = $checkTable->fetch_assoc();
if ((int) $row['c'] > 0) {
    $hasSchemaMetadata = true;
    $versionCheck = $db->query("SELECT schema_version FROM schema_metadata LIMIT 1");
    if ($versionCheck === false) {
        fwrite(STDERR, "Error: Unable to read current schema version\n");
        exit(1);
    }
    $verRow = $versionCheck->fetch_assoc();
    if ($verRow === null || !isset($verRow['schema_version'])) {
        // Empty table — no row yet
        $currentVersion = 0;
    } else {
        $currentVersion = (int) $verRow['schema_version'];
    }
}

if ($currentVersion === $latestVersion) {
    echo "Database is already at schema version $currentVersion. No migrations needed.\n";
    $db->close();
    exit(0);
}

if ($currentVersion > $latestVersion) {
    $msg = "Error: Database schema version ($currentVersion) is newer than the latest migration ($latestVersion).";
    fwrite(STDERR, "$msg\n");
    fwrite(STDERR, "The database may need to be restored from a backup.\n");
    $db->close();
    exit(1);
}

// Build the list of migrations to apply (in order).
// Check for gaps — if any version between current+1 and latest is missing, fail.
$migrationsToApply = [];
for ($v = $currentVersion + 1; $v <= $latestVersion; $v++) {
    if (!isset($migrations[$v])) {
        $available = implode(', ', array_keys($migrations));
        fwrite(STDERR, "Error: Missing migration for version $v (gap detected). Available: $available\n");
        exit(1);
    }
    $migrationsToApply[$v] = $migrations[$v];
}

if (empty($migrationsToApply)) {
    echo "No migrations found to apply.\n";
    $db->close();
    exit(0);
}

// Enable maintenance mode right before applying migrations (preflight checks are done)
$maintenanceEnabled = false;
try {
    \MTG\Admin\AdminSettings::setMaintenanceMode('on', $db, $appConfig);
    echo "Maintenance mode enabled.\n";
    $maintenanceEnabled = true;
} catch (\Exception $e) {
    fwrite(STDERR, "Error: Failed to enable maintenance mode: " . $e->getMessage() . "\n");
    fwrite(STDERR, "Aborting — cannot proceed without maintenance mode.\n");
    $db->close();
    exit(1);
}

// Apply each migration in sequence
$applied = [];
$applyFailed = false;
foreach ($migrationsToApply as $version => $file) {
    echo "Applying migration: " . basename($file) . " (version $version)\n";

    $sql = file_get_contents($file);
    if ($sql === false) {
        fwrite(STDERR, "Error: Unable to read migration file $file\n");
        exit(1);
    }

    // Set UTC time zone for deterministic timestamps (migration may do this too, but ensure it)
    $db->query("SET time_zone = '+00:00'");

    $success = $db->multi_query($sql);
    if ($success === false) {
        fwrite(STDERR, "Error: Failed to execute migration " . basename($file) . "\n");
        fwrite(STDERR, "Error: " . $db->error . "\n");
        $applyFailed = true;
        break;
    }

    // Drain all result sets and check for errors on each statement
    $hasError = false;
    do {
        if ($result = $db->store_result()) {
            $result->free();
        }
        if ($db->error !== '') {
            $hasError = true;
            break;
        }
    } while ($db->more_results() && $db->next_result());

    if ($hasError) {
        fwrite(STDERR, "Error: Migration " . basename($file) . " failed during execution: " . $db->error . "\n");
        $applyFailed = true;
        break;
    }

    $applied[] = $version;
    echo "  -> Applied successfully.\n";
}

if ($applyFailed) {
    fwrite(STDERR, "\nMigration aborted. Maintenance mode is still enabled.\n");
    fwrite(STDERR, "Applied so far: " . (empty($applied) ? 'none' : implode(', ', $applied)) . "\n");
    $db->close();
    exit(1);
}

// Verify the final schema version matches the expected version
$verify = $db->query("SELECT schema_version FROM schema_metadata LIMIT 1");
if ($verify === false) {
    fwrite(STDERR, "Error: Unable to verify schema version after migrations\n");
    exit(1);
}
$verRow = $verify->fetch_assoc();
if ($verRow === null || !isset($verRow['schema_version'])) {
    fwrite(STDERR, "Error: schema_metadata table has no row after migrations\n");
    exit(1);
}
$finalVersion = (int) $verRow['schema_version'];

if ($finalVersion !== $latestVersion) {
    fwrite(STDERR, "Error: Schema version mismatch — expected $latestVersion but got $finalVersion\n");
    fwrite(STDERR, "Migrations may have been partially applied. Check database state.\n");
    exit(1);
}

echo "\nMigrations complete.\n";
echo "Applied: " . implode(', ', $applied) . "\n";
echo "Schema version: $finalVersion\n";

// Disable maintenance mode only on successful completion
try {
    \MTG\Admin\AdminSettings::setMaintenanceMode('off', $db, $appConfig);
    echo "Maintenance mode disabled.\n";
} catch (\Exception $e) {
    fwrite(STDERR, "Warning: Failed to disable maintenance mode: " . $e->getMessage() . "\n");
    // Site stays in maintenance mode — this is the safe default
}

$db->close();
exit(0);
