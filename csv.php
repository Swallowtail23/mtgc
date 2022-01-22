<?php 
/* Version:     2.0
    Date:       17/10/16
    Name:       csv.php
    Purpose:    PHP script to export collection
    Notes:      {none}
        
    1.0
                Initial version
 *  2.0         Migrated to Mysqli_Manager
 */
if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

require ('includes/ini.php');                //Initialise and load ini file
require ('includes/error_handling.php');
require ('includes/functions_new.php');      //Includes basic functions for non-secure pages
require ('includes/secpagesetup.php');       //Setup page variables
$obj = new Message;
$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Loading csv.php...",$logfile);
                
// Page content starts here
if(isset($_GET['table'])):
    $table = filter_input(INPUT_GET, 'table', FILTER_SANITIZE_STRING);
    exportMysqlToCsv($table);
else:
    trigger_error("[ERROR] csv.php: Called with no parameters", E_USER_ERROR);
endif;
?>