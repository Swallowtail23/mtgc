<?php

/*
Version:     1.00
Date:        04/07/26
Name:        scryfall_reset_data.php
Purpose:     Destructively clear local Scryfall-managed data tables.
Notes:       Manual use only.
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

$ctx = require __DIR__ . '/bulk_ini.php';

$db = $ctx->db();
$msg = $ctx->message();

$confirm = strtolower(trim($argv[1] ?? ''));
if ($confirm !== 'confirm') :
    $text = "Scryfall reset data: refusing to run without 'confirm' argument";
    $msg->logMessage('[ERROR]', $text);
    if (PHP_SAPI === 'cli') :
        fwrite(STDERR, $text . PHP_EOL);
    endif;
    exit(1);
endif;

$tables = [
    'cards_scry',
    'rulings_scry',
    'migrations',
    'scryfall_manifest',
    'sets',
    'scryfall_sync_state',
];

$msg->logMessage('[WARNING]', 'Scryfall reset data: truncating Scryfall-managed data tables');

foreach ($tables as $table) :
    $sql = "TRUNCATE TABLE `$table`";
    if ($db->query($sql) === false) :
        $text = "Scryfall reset data: failed truncating $table: " . $db->error;
        $msg->logMessage('[ERROR]', $text);
        if (PHP_SAPI === 'cli') :
            fwrite(STDERR, $text . PHP_EOL);
        endif;
        exit(1);
    endif;
    $msg->logMessage('[NOTICE]', "Scryfall reset data: truncated $table");
    if (PHP_SAPI === 'cli') :
        echo "Truncated $table\n";
    endif;
endforeach;

$msg->logMessage('[NOTICE]', 'Scryfall reset data: completed');
