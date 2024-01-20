<?php 
/* Version:     1.2
    Date:       10/12/23
    Name:       valueupdate.php
    Purpose:    PHP script to update topvalue across collection
    Notes:      Currently called after import function is run
        
    1.0
                Initial version
 *  1.1
 *              Filter table name with regex
 * 
 *  1.2         20/01/24
 *              Move to logMessage
 */
if (file_exists('includes/sessionname.php')):
    require('includes/sessionname.php');
else:
    require('includes/sessionname_template.php');
endif;
startCustomSession();
require ('includes/ini.php');                //Initialise and load ini file
require ('includes/error_handling.php');
require ('includes/functions.php');      //Includes basic functions for non-secure pages
require ('includes/secpagesetup.php');       //Setup page variables
include 'includes/colour.php';
$msg = new Message($logfile);

$msg->logMessage('[NOTICE]',"Loading valueupdate.php...");
                
// Page content starts here
if(isset($_GET['table'])):
    $table = filter_input(INPUT_GET, 'table', FILTER_SANITIZE_SPECIAL_CHARS);
    //Make sure only number+collection is passed as table name
    if (valid_tablename($table) !== false):
        $obj = new PriceManager($db,$logfile,$useremail);
        $obj->updateCollectionValues($table);
    else:
        trigger_error("[ERROR] valueupdate.php: Invalid table format", E_USER_ERROR);
    endif;
else:
    trigger_error("[ERROR] valueupdate.php: Called with no parameters", E_USER_ERROR);
endif;
?>