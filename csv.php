<?php 
/* Version:     4.1
    Date:       14/01/24
    Name:       csv.php
    Purpose:    PHP script to export collection and redirect, called from profile.php
    Notes:      Redirects to profile.php if not in SMTP debug, with flag on success/fail
        
    1.0
                Initial version
 *  2.0         
 *              Migrated to Mysqli_Manager
 *  3.0
 *              PHP 8.1 compatibility
 *              Primarily now just logic, see class/functions
 *  
 *  4.0         13/01/24
 *              Added PHPMailer capability
 *
 *  4.1         14/01/24
 *              Documentation tweaks and minor changes
 */
      
if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

require ('includes/ini.php');                //Initialise and load ini file
require ('includes/error_handling.php');
require ('includes/functions.php');          //Includes basic functions for non-secure pages
require ('includes/secpagesetup.php');       //Setup page variables
$msg = new Message;
                
// Page content starts here
if(isset($_GET['table'])):
    $table = filter_input(INPUT_GET, 'table', FILTER_SANITIZE_SPECIAL_CHARS);
    $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"csv.php running for '$table'",$logfile);
    
    $obj = new ImportExport($db,$logfile,$useremail,$serveremail);
    
    // Can be called with type 'echo', 'email'
    // Difference is that 'echo' outputs to browser for download, 'email' triggers email output
    // In email mode, if SMTP is set to debug (PHPMailer) and site is in Debug log level, the SMTP output will also be output to screen
    if(isset($_GET['type']) && $_GET['type'] === 'echo'):
        $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"csv.php running for '$table', output ('{$_GET['type']}')",$logfile);
        $obj->exportCollectionToCsv($table, $myURL, $smtpParameters, 'echo');
    elseif(isset($_GET['type']) && $_GET['type'] === 'email'):
        $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"csv.php running for '$table', output ('{$_GET['type']}')",$logfile);
        $mailexport = $obj->exportCollectionToCsv($table, $myURL, $smtpParameters, 'email');
        if($smtpParameters['SMTPDebug'] !== 'SMTP::DEBUG_OFF' && $smtpParameters['globalDebug'] == 3):
            $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"In debug, not redirecting",$logfile);
        else:
            // If not in debug mode, redirect back to profile.php
            $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Not in SMTP/site debug, redirecting back to profile.php",$logfile);
            // If the mailexport was successful
            if($mailexport === TRUE):
                header('Location: profile.php?csvsuccess=true');
            else:
                header('Location: profile.php?csvsuccess=false');
            endif;
            exit;
        endif;
    else:
        $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"csv.php called for '$table', output type unclear ('{$_GET['type']}')",$logfile);
        trigger_error("[ERROR] csv.php: Called with incorrect parameters", E_USER_ERROR);
    endif;
else:
    $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"csv.php running, failed",$logfile);
    trigger_error("[ERROR] csv.php: Called with no parameters", E_USER_ERROR);
endif;
?>