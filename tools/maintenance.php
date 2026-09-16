<?php

/*
Version:     2.0
Date:        16/09/26
Name:        maintenance.php
Purpose:     CLI entry point to apply schema update migrations.
Notes:       Thin wrapper around MTG\Bulk\MigrationRunner.
              - Reads DB credentials from the INI file.
              - Delegates all logic to MigrationRunner service.
              - CLI-only guard; should not be web-accessible.
              - Add /tools access denial to Apache config.
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

// CLI-only guard — must be first executable code.
if (PHP_SAPI !== 'cli') {
    if (function_exists('fwrite') && function_exists('STDERR')) {
        fwrite(STDERR, "This script must be run from the command line.\n");
    }
    exit(1);
}

// Load Composer autoloader for app classes.
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Resolve INI path (same as bootstrap.php).
$iniPath = getenv('MTG_INI_PATH');
if ($iniPath === false || $iniPath === '') {
    $iniPath = '/opt/mtg/mtg_new.ini';
}

if (!file_exists($iniPath)) {
    fwrite(STDERR, "Error: INI file not found at $iniPath\n");
    exit(1);
}

// Parse INI for database credentials.
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

// Build AppConfig from INI data.
$appConfig = \MTG\Core\AppConfig::fromIni($iniData);

// Connect to MySQL.
$db = new \mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($db->connect_error) {
    fwrite(STDERR, "Error: Failed to connect to MySQL: " . $db->connect_error . "\n");
    exit(1);
}
$db->set_charset('utf8mb4');

// Determine the setup directory (repository root's setup/ subdirectory).
$setupDir = dirname(__DIR__) . '/setup';

// Delegate to the MigrationRunner service.
$runner = new \MTG\Bulk\MigrationRunner($db, $appConfig, $setupDir);

try {
    $applied = $runner->run();
    if (empty($applied)) {
        echo "Database is already at the latest schema version. No migrations needed.\n";
    } else {
        echo "\nMigrations complete.\n";
        echo "Applied: " . implode(', ', $applied) . "\n";
    }
    $db->close();
    exit(0);
} catch (\Exception $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    $db->close();
    exit(1);
} catch (\Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    $db->close();
    exit(1);
}
