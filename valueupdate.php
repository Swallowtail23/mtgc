<?php

/*
Version:     1.15
Date:        11/01/26
Name:        valueupdate.php
Purpose:     PHP script to update topvalue across collection.
Notes:       Currently called after import function is run.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Cards\PriceManager;
use MTG\Core\Validation;

// Bootstrap
$appContext = require 'bootstrap_secure.php';

// Content
$msg->logMessage('[DEBUG]', 'Loading valueupdate.php...');

if (isset($_GET['table'])) :
    $table = filter_input(INPUT_GET, 'table', FILTER_SANITIZE_SPECIAL_CHARS);
    if (Validation::validTableName($table, $appConfig) !== false) :
        $obj = new PriceManager($db, $appConfig, $userEmail);
        $obj->updateCollectionValues($table);
    else :
        throw new Exception('[ERROR] valueupdate.php: Invalid table format');
    endif;
else :
    throw new Exception('[ERROR] valueupdate.php: Called with no parameters');
endif;
