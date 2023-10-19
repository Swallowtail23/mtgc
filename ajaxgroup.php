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

if (!$_SESSION["logged"] == TRUE): ?>
    <table class='ajaxshow'>
        <tr>
            <td class="name">Your session is expired, or</td>
        </tr>
        <tr>
            <td class="name">you have been logged out.</td>
        </tr>
        <tr>
            <td class="name"><a href=login.php>Click here to log in again.</a></td>
        </tr>
    </table>
    <?php 
else: 
    //Need to run these as secpagesetup not run (see page notes)
    $user = check_logged();
    $mytable = $user."collection"; 
    $useremail = str_replace("'","",$_SESSION['useremail']);
    //
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