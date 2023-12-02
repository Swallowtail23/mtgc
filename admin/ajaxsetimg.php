<?php
/* Version:     1.0
    Date:       02/12/23
    Name:       admin/ajaxsetimg.php
    Purpose:    Reload all images for a set
    Notes:      The page does not run standard secpagesetup as it breaks 
                the ajax login catch.
    To do:      -

    1.0
                Initial version
*/
session_start();
require ('../includes/ini.php');
require ('../includes/error_handling.php');
require ('../includes/functions_new.php');
include '../includes/colour.php';

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== TRUE): 
    echo "<table class='ajaxshow'><tr><td class='name'>You are not logged in.</td></tr></table>";
    header("Refresh: 2; url=login.php");             // check if user is logged in; else redirect to login.php
    exit(); 
else: 
    //Need to run these as secpagesetup not run (see page notes)
    $sessionManager = new SessionManager($db,$adminip,$_SESSION, $fxAPI, $fxLocal, $logfile);
    $userArray = $sessionManager->getUserInfo();
    $user = $userArray['usernumber'];
    $mytable = $userArray['table'];
    $useremail = str_replace("'","",$_SESSION['useremail']);
    if(isset($_POST['setcode'])):
        $setcode = $db->escape($_POST['setcode']);
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Called with set $setcode",$logfile);
        $query = "SELECT id FROM cards_scry WHERE setcode = ?";
        $stmt = $db->prepare($query);
        if ($stmt):
            $stmt->bind_param("s", $setcode);
            $stmt->execute();
            $stmt->store_result();
            $stmt->bind_result($cardid);

            while ($stmt->fetch()):
                refresh_image($cardid);
            endwhile;
            $stmt->free_result();
            $stmt->close();
        else:
            trigger_error('[ERROR] ajaxsetimg.php: Error: '.$db->error, E_USER_ERROR);
        endif;
        // Close the database connection
        $db->close();
    else:
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"No setcode supplied",$logfile);
    endif;
endif;
?>