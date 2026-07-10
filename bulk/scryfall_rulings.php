<?php

/*
Version:     3.26
Date:        10/07/26
Name:        scryfall_rulings.php
Purpose:     Bootstrap the Scryfall rulings import.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
History:     See git history / CHANGELOG.md
To do:       -
*/

use MTG\Bulk\ScryfallRulingsImport;

$ctx = require __DIR__ . '/bulk_ini.php';
ScryfallRulingsImport::run($ctx);
