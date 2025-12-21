<?php

/*
Version:     1.6
Date:        21/12/25
Name:        valueupdate.php
Purpose:     PHP script to update topvalue across collection.
Notes:       Currently called after import function is run.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

if (file_exists('includes/sessionname.local.php')) :
    require 'includes/sessionname.local.php';
else :
    require 'includes/sessionname_template.php';
endif;
startCustomSession();
require 'includes/ini.php';                // Initialise and load ini file
require 'includes/error_handling.php';
require 'includes/functions.php';          // Includes basic functions for non-secure pages
require 'includes/secpagesetup.php';       // Setup page variables
include 'includes/colour.php';
$msg = new \MTG\Core\Message($logfile);

$msg->logMessage('[NOTICE]', 'Loading valueupdate.php...');

if (isset($_GET['table'])) :
    $table = filter_input(INPUT_GET, 'table', FILTER_SANITIZE_SPECIAL_CHARS);
    if (validTableName($table) !== false) :
        $obj = new PriceManager($db, $logfile, $userEmail);
        $obj->updateCollectionValues($table);
    else :
        throw new Exception('[ERROR] valueupdate.php: Invalid table format');
    endif;
else :
    throw new Exception('[ERROR] valueupdate.php: Called with no parameters');
endif;
