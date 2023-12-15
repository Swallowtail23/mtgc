<?php
/* Version:     1.0
    Date:       02/12/23
    Name:       ajax/ajaxsetimg.php
    Purpose:    Trigger reload all images for a set
    Notes:      The page does not run standard secpagesetup as it breaks 
                the ajax login catch.
    To do:      -

    1.0
                Initial version
*/
ini_set('session.name', '5VDSjp7k-n-_yS-_');
session_start();
require ('../includes/ini.php');
require ('../includes/error_handling.php');
require ('../includes/functions_new.php');
include '../includes/colour.php';

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== TRUE): 
    // Return an error in JSON format
    echo json_encode(["status" => "error", "message" => "You are not logged in."]);
    header("Refresh: 2; url=login.php"); // check if the user is logged in; else redirect to login.php
    exit(); 
else: 
    // Need to run these as secpagesetup not run (see page notes)
    $sessionManager = new SessionManager($db, $adminip, $_SESSION, $fxAPI, $fxLocal, $logfile);
    $userArray = $sessionManager->getUserInfo();
    $user = $userArray['usernumber'];
    $mytable = $userArray['table'];
    $useremail = str_replace("'", "", $_SESSION['useremail']);
    
    if (isset($_POST['setcode'])):
        $setcode = $_POST['setcode'];
        $root = $_SERVER['DOCUMENT_ROOT'];
        $msg = new Message;
        $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Called with set $setcode", $logfile);
        $cmd = "php $root/bulk/setimgreload.php '$setcode'> /dev/null 2>&1 &";
        $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": running $cmd", $logfile);
        exec($cmd);
        echo json_encode(["status" => "success", "message" => "Image reloading started for set $setcode - result will be emailed to server admin"]);
    else:
        $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": No setcode supplied", $logfile);
        echo json_encode(["status" => "error", "message" => "No setcode supplied"]);
    endif;
endif;
?>