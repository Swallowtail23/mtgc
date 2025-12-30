<?php

/*
Version:     1.0
Date:        30/12/25
Name:        manual_bulk_import_test.php
Purpose:     Manual stub to run the bulk import test routine on demand.
Notes:       See tests/README.md for expected output and usage.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

if (PHP_SAPI !== 'cli') :
    die('CLI only');
endif;

if (defined('PHPUNIT_COMPOSER_INSTALL')) :
    echo "PHPUnit detected; manual stub not executed.\n";
    exit(0);
endif;

// Manual stub for the bulk import test routine (see tests/README.md).
$script = __DIR__ . '/../bulk/scryfall_bulk.php';
$cmd = escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($script) . ' test';
passthru($cmd, $exitCode);
exit($exitCode);
