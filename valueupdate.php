<?php

/*
Version:     1.4
Date:        25/11/25
Name:        valueupdate.php
Purpose:     PHP script to update topvalue across collection.
Notes:       Currently called after import function is run.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -

History:
    1.0         Initial version
    1.1         Filter table name with regex
    1.2 20/01/24 Move to logMessage
    1.3 25/11/25 Formatting clean-up
    1.4 25/11/25 Standard tidy-up
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
$msg = new Message($logfile);

$msg->logMessage('[NOTICE]', 'Loading valueupdate.php...');

if (isset($_GET['table'])) :
    $table = filter_input(INPUT_GET, 'table', FILTER_SANITIZE_SPECIAL_CHARS);
    if (validTableName($table) !== false) :
        $obj = new PriceManager($db, $logfile, $useremail);
        $obj->updateCollectionValues($table);
    else :
        trigger_error('[ERROR] valueupdate.php: Invalid table format', E_USER_ERROR);
    endif;
else :
    trigger_error('[ERROR] valueupdate.php: Called with no parameters', E_USER_ERROR);
endif;
