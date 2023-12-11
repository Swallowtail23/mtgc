<?php 
/* Version:     3.0
    Date:       25/03/23
    Name:       csv.php
    Purpose:    PHP script to export collection
    Notes:      {none}
        
    1.0
                Initial version
 *  2.0         
 *              Migrated to Mysqli_Manager
 *  3.0
 *              PHP 8.1 compatibility
 */
if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

require ('includes/ini.php');                //Initialise and load ini file
require ('includes/error_handling.php');
require ('includes/functions_new.php');      //Includes basic functions for non-secure pages
require ('includes/secpagesetup.php');       //Setup page variables
$msg = new Message;
$msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Loading csv.php...",$logfile);
                
// Page content starts here
if(isset($_GET['table'])):
    $table = filter_input(INPUT_GET, 'table', FILTER_SANITIZE_SPECIAL_CHARS);
    $obj = new ImportExport($db,$logfile);
    $obj->exportCollectionToCsv($table);
else:
    trigger_error("[ERROR] csv.php: Called with no parameters", E_USER_ERROR);
endif;
?>