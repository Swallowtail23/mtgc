<?php 
/* Version:     1.0
    Date:       19/10/23
    Name:       ajaxgroup.php
    Purpose:    PHP script to turn ajax group on/off
    Notes:      The page does not run standard secpagesetup as it breaks 
                the ajax login catch.
    To do:      -

    1.0
                Initial version
*/

session_start();
require ('includes/ini.php');
require ('includes/error_handling.php');
require ('includes/functions_new.php');
include 'includes/colour.php';

if (!isset($_SESSION["logged"]) OR $_SESSION["logged"] != TRUE OR !isset($_SESSION['user']) OR !$_SESSION["logged"]): ?>
    <table class='ajaxshow'>
        <tr>
            <td class="name">You have been logged out.</td>
        </tr>
    </table>
    <?php 
    echo "<meta http-equiv='refresh' content='2;url=login.php'>";               // check if user is logged in; else redirect to login.php
    exit(); 
else: 
    //Need to run these as secpagesetup not run (see page notes)
    $sessionManager = new SessionManager($db,$adminip,$_SESSION, $fxAPI, $fxLocal, $logfile);
    $userArray = $sessionManager->getUserInfo();
    $user = $userArray['usernumber'];
    $mytable = $userArray['table'];
    $useremail = str_replace("'","",$_SESSION['useremail']);

    if (isset($_POST['group'])):
        $group = $db->escape($_POST['group']);
        if ($group == 'OPT OUT'):
            $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Call to opt out of groups",$logfile);
            $updatedata = array (
                'grpinout' => '0'
            );
            $optinquery = $db->update('users',$updatedata,"WHERE usernumber='$user'");
            if($optinquery === false):
                trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
            else:    
                $obj = new Message;
                $obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Group opt-out run for $useremail",$logfile);
            endif;
        elseif ($group == 'OPT IN'):
            $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Call to opt in to groups",$logfile);
            $updatedata = array (
                'grpinout' => 1
            );
            $optoutquery = $db->update('users',$updatedata,"WHERE usernumber='$user'");
            if($optoutquery === false):
                trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
            else:    
                $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Group opt-in run for $useremail",$logfile);
            endif;
        endif;
    endif;
endif;
?>