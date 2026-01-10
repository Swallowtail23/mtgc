<?php

/*
Version:     4.9
Date:        10/01/26
Name:        csv.php
Purpose:     Export collection and redirect from profile.php.
Notes:       Redirects to profile.php if not in SMTP debug, with flag on success/fail.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
    die('Direct access prohibited');
endif;

if (file_exists('includes/sessionname.local.php')) :
    require 'includes/sessionname.local.php';
else :
    require 'includes/sessionname_template.php';
endif;

startCustomSession();
require 'includes/ini.php'; // Initialise and load ini file
require 'includes/error_handling.php';
require 'includes/functions.php'; // Includes basic functions for non-secure pages
require 'includes/secpagesetup.php'; // Setup page variables
$msg = new \MTG\Core\Message($logfile);

// Page content starts here
$requestedTable = filter_input(INPUT_GET, 'table', FILTER_UNSAFE_RAW);
if ($requestedTable !== null) :
    $requestedTable = trim($requestedTable);
endif;
if ($requestedTable !== null && $requestedTable !== '') :
    $msg->logMessage(
        '[DEBUG]',
        "csv.php requested table '$requestedTable' by user $userEmail, admin status $admin"
    );
    $validatedTable = validTableName($requestedTable);
    if ($validatedTable === false) :
        $msg->logMessage('[ERROR]', "csv.php invalid table '$requestedTable' requested by $userEmail");
        throw new Exception("[ERROR] csv.php: Invalid table requested");
    endif;
    if ($admin == 1) :
        $table = $validatedTable;
        $msg->logMessage('[DEBUG]', "csv.php admin export allowed for '$table'");
    else :
        if ($validatedTable !== $mytable) :
            $msg->logMessage(
                '[ERROR]',
                "csv.php blocked export for '$validatedTable' by $userEmail (user table '$mytable')"
            );
            throw new Exception("[ERROR] csv.php: Unauthorized table requested");
        endif;
        $table = $mytable;
        $msg->logMessage('[DEBUG]', "csv.php exporting own table '$table'");
    endif;

    $msg->logMessage('[NOTICE]', "csv.php running for '$table'");

    $obj = new \MTG\Cards\ImportExport($db, $logfile, $userEmail, $serverEmail, $siteTitle);

    // Can be called with type 'echo', 'email'
    // Difference is that 'echo' outputs to browser for download, 'email' triggers email output
    // In email mode, if SMTP is set to debug and site is in Debug log level, the SMTP output
    // will also be output to screen
    if (isset($_GET['type']) && $_GET['type'] === 'echo') :
        $msg->logMessage('[DEBUG]', "csv.php running for '$table', output ('{$_GET['type']}')");
        $obj->exportCollectionToCsv($table, $myURL, $smtpParameters, 'echo');
    elseif (isset($_GET['type']) && $_GET['type'] === 'email') :
        $msg->logMessage('[DEBUG]', "csv.php running for '$table', output ('{$_GET['type']}')");
        $mailexport = $obj->exportCollectionToCsv($table, $myURL, $smtpParameters, 'email');
        if ($smtpParameters['SMTPDebug'] !== 'SMTP::DEBUG_OFF' && $smtpParameters['globalDebug'] == 3) :
            $msg->logMessage('[DEBUG]', 'In debug, not redirecting');
        else :
            // If not in debug mode, redirect back to the calling page
            $msg->logMessage('[DEBUG]', 'Not in SMTP/site debug, redirecting back to referrer');
            $returnUrl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'profile.php';
            // If the mailexport was successful
            if ($mailexport === true) :
                $_SESSION['csv_status'] = 'true';
                header("Location: {$returnUrl}?csvsuccess=true");
            else :
                $_SESSION['csv_status'] = 'false';
                header("Location: {$returnUrl}?csvsuccess=false");
            endif;
            exit;
        endif;
    else :
        $msg->logMessage('[DEBUG]', "csv.php called for '$table', output type unclear ('{$_GET['type']}')");
        throw new Exception("[ERROR] csv.php: Called with incorrect parameters");
    endif;
else :
    $msg->logMessage('[DEBUG]', 'csv.php running, failed');
    throw new Exception("[ERROR] csv.php: Called with no parameters");
endif;
