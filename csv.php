<?php

/*
Version:     4.3
Date:        05/12/25
Name:        csv.php
Purpose:     Export collection and redirect from profile.php.
Notes:       Redirects to profile.php if not in SMTP debug, with flag on success/fail.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -

History:
    1.0         Initial version
    2.0         Migrated to Mysqli_Manager
    3.0         PHP 8.1 compatibility; primarily logic, see class/functions
    4.0 13/01/24 Added PHPMailer capability
    4.1 14/01/24 Documentation tweaks; move to logMessage function
    4.2 25/11/25 Standard tidy-up
    4.3 05/12/25 Persist email export success/failure for profile notification
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
$msg = new Message($logfile);
$siteTitleEsc = htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8');

// Page content starts here
if (isset($_GET['table'])) :
    $table = filter_input(INPUT_GET, 'table', FILTER_SANITIZE_SPECIAL_CHARS);
    $msg->logMessage('[NOTICE]', "csv.php running for '$table'");

    $obj = new ImportExport($db, $logfile, $userEmail, $serverEmail, $siteTitleEsc);

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
        trigger_error("[ERROR] csv.php: Called with incorrect parameters", E_USER_ERROR);
    endif;
else :
    $msg->logMessage('[DEBUG]', 'csv.php running, failed');
    trigger_error("[ERROR] csv.php: Called with no parameters", E_USER_ERROR);
endif;
