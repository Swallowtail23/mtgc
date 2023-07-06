<?php 
/* Version:     5.0
    Date:       06/07/23
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
 *  4.0
 *              PHP 8.1 compatibility
 *  5.0
 *              Added third card finish (etched)
*/

session_start();
require ('includes/ini.php');                //Initialise and load ini file
require ('includes/error_handling.php');
require ('includes/functions_new.php');      //Includes basic functions for non-secure pages
require ('includes/secpagesetup.php');       //Setup page variables
include 'includes/colour.php';

$cardid = filter_input(INPUT_GET, 'cardid', FILTER_SANITIZE_SPECIAL_CHARS); 

//Process and log new quantity request
if (isset($_GET['newqty'])): 
    $qty = $_GET['newqty']; 
    if(is_int($qty / 1) AND $qty > -1):
        $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"User $useremail({$_SERVER['REMOTE_ADDR']}) Qty update request for $cardid, request: Normal:$qty",$logfile);
    else:
        $obj = new Message;$obj->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"User $useremail({$_SERVER['REMOTE_ADDR']}) invalid qty $qty passed for normal $cardid",$logfile);
        echo "<img src='/images/error.png' alt='error'>";
        exit;
    endif;
elseif (isset($_GET['newfoil'])): 
    $qty = $_GET['newfoil']; 
    if(is_int($qty / 1) AND $qty > -1):
        $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"User $useremail({$_SERVER['REMOTE_ADDR']}) Qty update request for $cardid, request: Foil:$qty",$logfile);
    else:
        $obj = new Message;$obj->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"User $useremail({$_SERVER['REMOTE_ADDR']}) invalid qty $qty passed for foil $cardid",$logfile);
        echo "<img src='/images/error.png' alt='error'>";
        exit;
    endif;
elseif (isset($_GET['newetch'])): 
    $qty = $_GET['newetch']; 
    if(is_int($qty / 1) AND $qty > -1):
        $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"User $useremail({$_SERVER['REMOTE_ADDR']}) Qty update request for $cardid, request: Etched:$qty",$logfile);
    else:
        $obj = new Message;$obj->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"User $useremail({$_SERVER['REMOTE_ADDR']}) invalid qty $qty passed for etched $cardid",$logfile);
        echo "<img src='/images/error.png' alt='error'>";
        exit;
    endif;
else:
    $obj = new Message;$obj->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"User $useremail({$_SERVER['REMOTE_ADDR']}) called with no arguments",$logfile);
    exit();
endif;

//Should only be here if newqty, newfoil or newetch are set
//Set up variables
$sqlqty = $db->escape($qty);
$sqlid = $db->escape(filter_input(INPUT_GET, 'cardid', FILTER_SANITIZE_SPECIAL_CHARS));

//Check existing quantity
$beforeresult = $db->select_one('normal, foil, etched',"$mytable","WHERE id = '$sqlid'");
if($beforeresult === false):
    trigger_error('[ERROR] gridupdate.php: Error: '.$db->error, E_USER_ERROR);
else:
    if (empty($beforeresult['normal'])):
        $myqty = 0;
    else:
        $myqty = $db->escape($beforeresult['normal'],'int');
    endif;
    if (empty($beforeresult['foil'])):
        $myfoil = 0;
    else:
        $myfoil = $db->escape($beforeresult['foil'],'int');
    endif;
    if (empty($beforeresult['etched'])):
        $myetch = 0;
    else:
        $myetch = $db->escape($beforeresult['etched'],'int');
    endif;
    $obj = new Message;
    $obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"User $useremail({$_SERVER['REMOTE_ADDR']}) Qty update for $sqlid, prior values: Normal:$myqty, Foil:$myfoil, Etched:$myetch",$logfile);
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
elseif (isset($_GET['newetch'])): 
    $updatequery = "
        INSERT INTO $mytable (etched,id)
        VALUES ($sqlqty,'$sqlid')
        ON DUPLICATE KEY UPDATE 
        etched=$sqlqty";
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
$checkresult = $db->select_one('normal, foil, etched',"$mytable","WHERE id = '$sqlid'");
if($checkresult === false):
    trigger_error('[ERROR] gridupdate.php: Error: '.$db->error, E_USER_ERROR);
else:
    if (isset($_GET['newqty'])): 
        if ($sqlqty === $checkresult['normal']): 
            $obj = new Message;
            $obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"User $useremail({$_SERVER['REMOTE_ADDR']}) Qty update completed for $sqlid, new value: Normal:{$checkresult['normal']}",$logfile);
            echo "<span class='material-icons md-18 green'>check</span>";
        else:
            $obj = new Message;
            $obj->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"User $useremail({$_SERVER['REMOTE_ADDR']}) Grid check FAIL for $sqlid, new value: Normal:{$checkresult['normal']}",$logfile);
            echo "<span class='material-icons md-18 red'>clear</span>"." ".$checkresult['normal'];
        endif;
    elseif (isset($_GET['newfoil'])): 
        if ($sqlqty === $checkresult['foil']): 
            $obj = new Message;
            $obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"User $useremail({$_SERVER['REMOTE_ADDR']}) Grid check completed for $sqlid, new value: Foil: {$checkresult['foil']}",$logfile);
            echo "<span class='material-icons md-18 green'>check</span>";
        else:
            $obj = new Message;
            $obj->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"User $useremail({$_SERVER['REMOTE_ADDR']}) Grid check FAIL for $sqlid, new value: Foil: {$checkresult['foil']}",$logfile);
            echo "<span class='material-icons md-18 red'>clear</span>"." ".$checkresult['foil'];
        endif;
    elseif (isset($_GET['newetch'])): 
        if ($sqlqty === $checkresult['etched']): 
            $obj = new Message;
            $obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"User $useremail({$_SERVER['REMOTE_ADDR']}) Grid check completed for $sqlid, new value: Etched: {$checkresult['etched']}",$logfile);
            echo "<span class='material-icons md-18 green'>check</span>";
        else:
            $obj = new Message;
            $obj->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"User $useremail({$_SERVER['REMOTE_ADDR']}) Grid check FAIL for $sqlid, new value: Etched: {$checkresult['etched']}",$logfile);
            echo "<span class='material-icons md-18 red'>clear</span>"." ".$checkresult['etched'];
        endif;
    endif;
endif;?> 




