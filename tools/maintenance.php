#!/usr/bin/env php
<?php

/*
Version:     1.2
Date:        16/09/26
Name:        maintenance.php
Purpose:     Apply schema update migrations from the CLI.
Notes:       CLI-only entry point. Reads DB credentials from the INI file,
             discovers available migrations in setup/, and applies them
             sequentially from the current schema version up to the latest.
             Designed to handle both single-version and multi-version
             migration files.
             Sets maintenance mode ON before applying migrations and turns
             it OFF only if all migrations succeed.
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
require_once __DIR__ . '/../vendor/autoload.php';

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

// Enable maintenance mode before applying migrations
try {
    \MTG\Admin\AdminSettings::setMaintenanceMode('on', $db, $appConfig);
    echo "Maintenance mode enabled.\n";
} catch (\Exception $e) {
    fwrite(STDERR, "Warning: Failed to enable maintenance mode: " . $e->getMessage() . "\n");
    // Continue anyway — schema migration is the primary goal
}

// Determine the setup directory
$setupDir = __DIR__ . '/setup';
if (!is_dir($setupDir)) {
    fwrite(STDERR, "Error: Setup directory not found at $setupDir\n");
    exit(1);
}

// Discover available migration files (schema_vNNN*.sql)
$files = glob($setupDir . '/schema_v*.sql');
if ($files === false || empty($files)) {
    fwrite(STDERR, "Error: No migration files found matching schema_v*.sql in $setupDir\n");
    exit(1);
}

// Sort files by name (version number) to ensure correct order
sort($files);

// Extract version number from filename (e.g., schema_v001.sql -> 1)
function extractVersion(string $filePath): int
{
    $basename = basename($filePath);
    if (preg_match('/schema_v(\d+)\.sql$/', $basename, $matches)) {
        return (int) $matches[1];
    }
    return 0;
}

// Build a version => file map
$migrations = [];
foreach ($files as $file) {
    $version = extractVersion($file);
    if ($version > 0) {
        $migrations[$version] = $file;
    }
}

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
    $currentVersion = (int) $verRow['schema_version'];
}

// Determine the latest available version
$latestVersion = max(array_keys($migrations));

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

// Build the list of migrations to apply (in order)
$migrationsToApply = [];
for ($v = $currentVersion + 1; $v <= $latestVersion; $v++) {
    if (isset($migrations[$v])) {
        $migrationsToApply[$v] = $migrations[$v];
    }
}

if (empty($migrationsToApply)) {
    echo "No migrations found to apply.\n";
    $db->close();
    exit(0);
}

// Apply each migration in sequence
$applied = [];
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
        exit(1);
    }

    // Drain any result sets from multi_query
    do {
        if ($result = $db->store_result()) {
            $result->free();
        }
    } while ($db->more_results() && $db->next_result());

    $applied[] = $version;
    echo "  -> Applied successfully.\n";
}

// Verify the final schema version
$verify = $db->query("SELECT schema_version FROM schema_metadata LIMIT 1");
if ($verify === false) {
    fwrite(STDERR, "Error: Unable to verify schema version after migrations\n");
    exit(1);
}
$verRow = $verify->fetch_assoc();
$finalVersion = (int) $verRow['schema_version'];

echo "\nMigrations complete.\n";
echo "Applied: " . implode(', ', $applied) . "\n";
echo "Schema version: $finalVersion\n";

// Disable maintenance mode only on successful completion
try {
    \MTG\Admin\AdminSettings::setMaintenanceMode('off', $db, $appConfig);
    echo "Maintenance mode disabled.\n";
} catch (\Exception $e) {
    fwrite(STDERR, "Warning: Failed to disable maintenance mode: " . $e->getMessage() . "\n");
}

$db->close();
exit(0);
