<?php

/*
Version:     1.14
Date:        11/01/26
Name:        valueupdate.php
Purpose:     PHP script to update topvalue across collection.
Notes:       Currently called after import function is run.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Cards\PriceManager;
use MTG\Core\Message;
use MTG\Core\Validation;

if (file_exists('includes/sessionname.local.php')) :
    require 'includes/sessionname.local.php';
else :
    require 'includes/sessionname_template.php';
endif;
startCustomSession();
require 'includes/ini.php';                // Initialise and load ini file
require 'includes/error_handling.php';
require 'includes/secpagesetup.php';       // Setup page variables
$msg = new Message($appConfig);

$msg->logMessage('[NOTICE]', 'Loading valueupdate.php...');

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
