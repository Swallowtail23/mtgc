<?php 
/* Version:     5.1
    Date:       10/12/23
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
 *
 *  5.1         10/12/23
 *              Add cardid regex filter
 *              Improve error handling back to Ajax
*/
ini_set('session.name', '5VDSjp7k-n-_yS-_');
session_start();
require ('includes/ini.php');                //Initialise and load ini file
require ('includes/error_handling.php');
require ('includes/functions_new.php');      //Includes basic functions for non-secure pages
require ('includes/secpagesetup.php');       //Setup page variables
include 'includes/colour.php';
$msg = new Message;

$cardid = $_GET['cardid'] ?? '';
$cardid = valid_uuid($cardid);
if ($cardid === false):
    $msg->MessageTxt('[ERROR]', $_SERVER['PHP_SELF'], "User $useremail({$_SERVER['REMOTE_ADDR']}) Called with invalid card UUID", $logfile);
    $response['status'] = 'error';
    $response['message'] = "Called with invalid card UUID";
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
endif;

//Process and log new quantity request
if (isset($_GET['newqty'])): 
    $qty = $_GET['newqty']; 
    if(is_int($qty / 1) AND $qty > -1):
        $msg->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"User $useremail({$_SERVER['REMOTE_ADDR']}) Qty update request for $cardid, request: Normal:$qty",$logfile);
    else:
        $msg->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"User $useremail({$_SERVER['REMOTE_ADDR']}) invalid qty $qty passed for normal $cardid",$logfile);
        $response['status'] = 'error';
        $response['message'] = "Invalid normal qty";
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    endif;
elseif (isset($_GET['newfoil'])): 
    $qty = $_GET['newfoil']; 
    if(is_int($qty / 1) AND $qty > -1):
        $msg->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"User $useremail({$_SERVER['REMOTE_ADDR']}) Qty update request for $cardid, request: Foil:$qty",$logfile);
    else:
        $msg->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"User $useremail({$_SERVER['REMOTE_ADDR']}) invalid qty $qty passed for foil $cardid",$logfile);
        $response['status'] = 'error';
        $response['message'] = "Invalid foil qty";
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    endif;
elseif (isset($_GET['newetch'])): 
    $qty = $_GET['newetch']; 
    if(is_int($qty / 1) AND $qty > -1):
        $msg->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"User $useremail({$_SERVER['REMOTE_ADDR']}) Qty update request for $cardid, request: Etched:$qty",$logfile);
    else:
        $msg->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"User $useremail({$_SERVER['REMOTE_ADDR']}) invalid qty $qty passed for etched $cardid",$logfile);
        $response['status'] = 'error';
        $response['message'] = "Invalid etch qty";
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    endif;
else:
    $msg->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"User $useremail({$_SERVER['REMOTE_ADDR']}) called with no arguments",$logfile);
    $response['status'] = 'error';
    $response['message'] = "Invalid call";
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
endif;

//Should only be here if newqty, newfoil or newetch are set
//Set up variables
if (is_numeric($qty) && (int)$qty == $qty) :
    $sqlqty = (int)$qty;
else:
    $response['status'] = 'error';
    $response['message'] = "Invalid qty";
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
endif;

$sqlid = $cardid;

//Check existing quantity
$beforeresultqry = $db->execute_query("SELECT normal, foil, etched FROM $mytable WHERE id = ? LIMIT 1",[$sqlid]);
if($beforeresultqry === false):
    $msg->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"User $useremail({$_SERVER['REMOTE_ADDR']}) Unable to get 'before' values",$logfile);
    $response['status'] = 'error';
    $response['message'] = "SQL update error: $db->error";
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
else:
    $beforeresult = $beforeresultqry->fetch_assoc();
    if (empty($beforeresult['normal'])):
        $myqty = 0;
    else:
        $myqty = $beforeresult['normal'];
    endif;
    if (empty($beforeresult['foil'])):
        $myfoil = 0;
    else:
        $myfoil = $beforeresult['foil'];
    endif;
    if (empty($beforeresult['etched'])):
        $myetch = 0;
    else:
        $myetch = $beforeresult['etched'];
    endif;
    $msg->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"User $useremail({$_SERVER['REMOTE_ADDR']}) Qty update for $sqlid, prior values: Normal:$myqty, Foil:$myfoil, Etched:$myetch",$logfile);
endif;
// Run update
if (isset($_GET['newqty'])): 
    $updatequery = "
        INSERT INTO $mytable (normal,id)
        VALUES (?,?)
        ON DUPLICATE KEY UPDATE 
        normal = ?";
elseif (isset($_GET['newfoil'])): 
    $updatequery = "
        INSERT INTO $mytable (foil,id)
        VALUES (?,?)
        ON DUPLICATE KEY UPDATE 
        foil = ?";
elseif (isset($_GET['newetch'])): 
    $updatequery = "
        INSERT INTO $mytable (etched,id)
        VALUES (?,?)
        ON DUPLICATE KEY UPDATE 
        etched = ?";
endif;
$params = [$sqlqty,$sqlid,$sqlqty];
$sqlupdate = $db->execute_query($updatequery,$params);
if($sqlupdate === false):
    $msg->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"User $useremail({$_SERVER['REMOTE_ADDR']}) Unable to update: $db->error",$logfile);
    $response['status'] = 'error';
    $response['message'] = "SQL update error: $db->error";
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
else:
    $affected_rows = $db->affected_rows;
    if ($affected_rows === 2):
        $msg->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Update query run for $sqlid, existing entry updated",$logfile);
    elseif ($affected_rows === 1):
        $msg->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Update query run for $sqlid, new row inserted",$logfile);
    endif;
    
endif;
// Update topvalue
$msg->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Updating topvalue based on new quantities",$logfile);
update_topvalue_card($mytable,$sqlid);

// Retrieve new record to display
$response = [];

$checkresultqry = $db->execute_query("SELECT normal, foil, etched FROM $mytable WHERE id = ? LIMIT 1",[$sqlid]);
if ($checkresultqry === false):
    $msg->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"User $useremail({$_SERVER['REMOTE_ADDR']}) Unable to update: $db->error",$logfile);
    $response['status'] = 'error';
    $response['message'] = "SQL update error: $db->error";
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
else:
    $checkresult = $checkresultqry->fetch_assoc();
    if (isset($_GET['newqty'])):
        if ((int)$sqlqty === (int)$checkresult['normal']):
            $msg->MessageTxt('[NOTICE]', $_SERVER['PHP_SELF'], "User $useremail({$_SERVER['REMOTE_ADDR']}) Qty update completed for $sqlid, new value: Normal:{$checkresult['normal']}", $logfile);
            $response['status'] = 'success';
            $response['message'] = "Qty update completed for $sqlid, new value: Normal:{$checkresult['normal']}";
        else:
            $msg->MessageTxt('[ERROR]', $_SERVER['PHP_SELF'], "User $useremail({$_SERVER['REMOTE_ADDR']}) Grid check FAIL for $sqlid, new value: Normal:{$checkresult['normal']}", $logfile);
            $response['status'] = 'error';
            $response['message'] = "Grid check FAIL for $sqlid, new value: Normal:{$checkresult['normal']}";
            http_response_code(400);
        endif;
    elseif (isset($_GET['newfoil'])):
        if ((int)$sqlqty === (int)$checkresult['foil']):
            $msg->MessageTxt('[NOTICE]', $_SERVER['PHP_SELF'], "User $useremail({$_SERVER['REMOTE_ADDR']}) Grid check completed for $sqlid, new value: Foil: {$checkresult['foil']}", $logfile);
            $response['status'] = 'success';
            $response['message'] = "Grid check completed for $sqlid, new value: Foil: {$checkresult['foil']}";
        else:
            $msg->MessageTxt('[ERROR]', $_SERVER['PHP_SELF'], "User $useremail({$_SERVER['REMOTE_ADDR']}) Grid check FAIL for $sqlid, new value: Foil: {$checkresult['foil']}", $logfile);
            $response['status'] = 'error';
            $response['message'] = "Grid check FAIL for $sqlid, new value: Foil: {$checkresult['foil']}";
            http_response_code(400);
        endif;
    elseif (isset($_GET['newetch'])):
        if ((int)$sqlqty === (int)$checkresult['etched']):
            $msg->MessageTxt('[NOTICE]', $_SERVER['PHP_SELF'], "User $useremail({$_SERVER['REMOTE_ADDR']}) Grid check completed for $sqlid, new value: Etched: {$checkresult['etched']}", $logfile);
            $response['status'] = 'success';
            $response['message'] = "Grid check completed for $sqlid, new value: Etched: {$checkresult['etched']}";
        else:
            $msg->MessageTxt('[ERROR]', $_SERVER['PHP_SELF'], "User $useremail({$_SERVER['REMOTE_ADDR']}) Grid check FAIL for $sqlid, new value: Etched: {$checkresult['etched']}", $logfile);
            $response['status'] = 'error';
            $response['message'] = "Grid check FAIL for $sqlid, new value: Etched: {$checkresult['etched']}";
            http_response_code(400);
        endif;
    endif;
endif;

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>