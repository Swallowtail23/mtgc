<?php

/*
Version:     1.04
Date:        10/07/26
Name:        scryfall_manifest.php
Purpose:     Bootstrap the Scryfall card manifest import.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use MTG\Bulk\ScryfallManifestImport;

$ctx = require __DIR__ . '/bulk_ini.php';
ScryfallManifestImport::run($ctx);
