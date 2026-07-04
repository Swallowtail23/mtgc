<?php

/*
Version:     1.00
Date:        04/07/26
Name:        scryfall_sync_state.php
Purpose:     Backfill local Scryfall sync state from current card and manifest data.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use MTG\Bulk\ScryfallImport;

$ctx = require __DIR__ . '/bulk_ini.php';

$db = $ctx->db();
$msg = $ctx->message();

$mode = strtolower(trim($argv[1] ?? 'data-backfill'));
if ($mode !== 'data-backfill') :
    $text = "Scryfall sync state: unsupported mode '$mode'";
    $msg->logMessage('[ERROR]', $text);
    if (PHP_SAPI === 'cli') :
        fwrite(STDERR, $text . PHP_EOL);
    endif;
    exit(1);
endif;

$affected = ScryfallImport::backfillDataSyncState($db, $msg);
if (PHP_SAPI === 'cli') :
    echo "Scryfall sync state: data backfill completed; affected rows: $affected\n";
endif;
