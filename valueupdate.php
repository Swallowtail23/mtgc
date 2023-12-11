<?php 
/* Version:     1.1
    Date:       10/12/23
    Name:       valueupdate.php
    Purpose:    PHP script to update topvalue across collection
    Notes:      Currently called after import function is run
        
    1.0
                Initial version
 *  1.1
 *              Filter table name with regex
 */
ini_set('session.name', '5VDSjp7k-n-_yS-_');
session_start();
require ('includes/ini.php');                //Initialise and load ini file
require ('includes/error_handling.php');
require ('includes/functions_new.php');      //Includes basic functions for non-secure pages
require ('includes/secpagesetup.php');       //Setup page variables
include 'includes/colour.php';

$msg = new Message;
$msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Loading valueupdate.php...",$logfile);
                
// Page content starts here
if(isset($_GET['table'])):
    $table = filter_input(INPUT_GET, 'table', FILTER_SANITIZE_SPECIAL_CHARS);
    //Make sure only number+collection is passed as table name
    if (valid_tablename($table) !== false):
        update_collection_values($table);
    else:
        trigger_error("[ERROR] valueupdate.php: Invalid table format", E_USER_ERROR);
    endif;
else:
    trigger_error("[ERROR] valueupdate.php: Called with no parameters", E_USER_ERROR);
endif;
?>