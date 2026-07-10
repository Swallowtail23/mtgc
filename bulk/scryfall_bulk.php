<?php

/*
Version:     9.35
Date:        10/07/26
Name:        scryfall_bulk.php
Purpose:     Bootstrap the Scryfall bulk import command.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Bulk\ScryfallBulkCommand;

$ctx = require __DIR__ . '/bulk_ini.php';

$command = new ScryfallBulkCommand($ctx->db(), $ctx->config(), $ctx->rules(), $ctx->message());
exit($command->run($argv));
