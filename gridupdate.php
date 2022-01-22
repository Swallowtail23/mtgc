<?php 
/* Version:     3.0
    Date:       11/01/20
    Name:       gridupdate.php
    Purpose:    Processes updates from Grid/Bulk views of index.php
    Notes:      {none}
    To do:      -
    
    1.0
                Initial version
 *  2.0
 *              Migrated to Mysqli_Manager
 *  3.0
 *              Moved from writelog to Message class
*/

session_start();
require ('includes/ini.php');                //Initialise and load ini file
require ('includes/error_handling.php');
require ('includes/functions_new.php');      //Includes basic functions for non-secure pages
require ('includes/secpagesetup.php');       //Setup page variables
include 'includes/colour.php';

$flipinfo = filter_input(INPUT_GET, 'flip', FILTER_SANITIZE_STRING); 
$flipbackid = filter_input(INPUT_GET, 'flipback', FILTER_SANITIZE_STRING); 
$cardnumber = filter_input(INPUT_GET, 'cardnumber', FILTER_SANITIZE_STRING); 

//Process and log new quantity request
if (isset($_GET['newqty'])): 
    $qty = filter_input(INPUT_GET, 'newqty', FILTER_SANITIZE_STRING); 
    if(is_int($qty / 1) AND $qty > -1):
        $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"User $useremail({$_SERVER['REMOTE_ADDR']}) Qty update request for $cardnumber, request: Normal:$qty",$logfile);
    else:
        $obj = new Message;$obj->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"User $useremail({$_SERVER['REMOTE_ADDR']}) invalid qty $qty passed for normal $cardnumber",$logfile);
        echo "<img src='/images/error.png' alt='error'>";
        exit;
    endif;
elseif (isset($_GET['newfoil'])): 
    $qty = filter_input(INPUT_GET, 'newfoil', FILTER_SANITIZE_STRING); 
    if(is_int($qty / 1) AND $qty > -1):
        $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"User $useremail({$_SERVER['REMOTE_ADDR']}) Qty update request for $cardnumber, request: Foil:$qty",$logfile);
    else:
        $obj = new Message;$obj->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"User $useremail({$_SERVER['REMOTE_ADDR']}) invalid qty $qty passed for foil $cardnumber",$logfile);
        echo "<img src='/images/error.png' alt='error'>";
        exit;
    endif;
else:
    $obj = new Message;$obj->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"User $useremail({$_SERVER['REMOTE_ADDR']}) called with no arguments",$logfile);
    exit();
endif;

//Should only be here if newqty or newfoil are set
//Set up variables
$sqlqty = $db->escape($qty);
$sqlid = $db->escape(filter_input(INPUT_GET, 'cardnumber', FILTER_SANITIZE_STRING));

//Check existing quantity
$beforeresult = $db->select_one('normal, foil',"$mytable","WHERE id = '$sqlid'");
if($beforeresult === false):
    trigger_error('[ERROR] gridupdate.php: Error: '.$db->error, E_USER_ERROR);
else:
    $obj = new Message;
    $obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"User $useremail({$_SERVER['REMOTE_ADDR']}) Qty update for $sqlid, prior values: Normal:{$beforeresult['normal']}, Foil:{$beforeresult['foil']}",$logfile);
endif;
// Run update
if (isset($_GET['newqty'])): 
    $updatequery = "
        INSERT INTO $mytable (normal,id)
        VALUES ($sqlqty,'$sqlid')
        ON DUPLICATE KEY UPDATE 
        normal=$sqlqty";
elseif (isset($_GET['newfoil'])): 
    $updatequery = "
        INSERT INTO $mytable (foil,id)
        VALUES ($sqlqty,'$sqlid')
        ON DUPLICATE KEY UPDATE 
        foil=$sqlqty";
endif;
$sqlupdate = $db->query($updatequery);
if($sqlupdate === false):
    trigger_error('[ERROR] gridupdate.php: Error: '.$db->error, E_USER_ERROR);
else:
    $affected_rows = $db->affected_rows;
    if ($affected_rows === 2):
        $obj = new Message;$obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Update query run for $sqlid, existing entry updated",$logfile);
    elseif ($affected_rows === 1):
        $obj = new Message;$obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Update query run for $sqlid, new row inserted",$logfile);
    endif;
    
endif;

// Retrieve new record to display
$checkresult = $db->select_one('normal, foil',"$mytable","WHERE id = '$sqlid'");
if($checkresult === false):
    trigger_error('[ERROR] gridupdate.php: Error: '.$db->error, E_USER_ERROR);
else:
    if (isset($_GET['newqty'])): 
        if ($sqlqty === $checkresult['normal']): 
            $obj = new Message;
            $obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"User $useremail({$_SERVER['REMOTE_ADDR']}) Qty update completed for $sqlid, new value: Normal:{$checkresult['normal']}",$logfile);
            echo "<img src='/images/success.png' alt='success'>";
        else:
            $obj = new Message;
            $obj->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"User $useremail({$_SERVER['REMOTE_ADDR']}) Grid check FAIL for $sqlid, new value: Normal:{$checkresult['normal']}",$logfile);
            echo "<img src='/images/error.png' alt='error'>"." ".$checkresult['normal'];
        endif;
    elseif (isset($_GET['newfoil'])): 
        if ($sqlqty === $checkresult['foil']): 
            $obj = new Message;
            $obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"User $useremail({$_SERVER['REMOTE_ADDR']}) Grid check completed for $sqlid, new value: Foil: {$checkresult['foil']}",$logfile);
            echo "<img src='/images/success.png' alt='success'>";
        else:
            $obj = new Message;
            $obj->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"User $useremail({$_SERVER['REMOTE_ADDR']}) Grid check FAIL for $sqlid, new value: Foil: {$checkresult['foil']}",$logfile);
            echo "<img src='/images/error.png' alt='error'>"." ".$checkresult['foil'];
        endif;
    endif;
endif;?> 




