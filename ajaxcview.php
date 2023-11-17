<?php 
/* Version:     1.0
    Date:       19/10/23
    Name:       ajaxcview.php
    Purpose:    PHP script to turn ajax collection view on/off
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
    $sessionManager = new SessionManager($db,$adminip,$_SESSION);
    $userArray = $sessionManager->checkLogged();
    $user = $userArray['usernumber'];
    $mytable = $userArray['table'];
    $useremail = str_replace("'","",$_SESSION['useremail']);

    if(isset($_POST['collection_view'])):
        $coll_view = $db->escape($_POST['collection_view']);
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Called with collection view query $coll_view",$logfile);
        if ($coll_view == 'TURN OFF'):
            $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Call to turn off collection view",$logfile);
            $updatedata = array (
                'collection_view' => '0'
            );
            $cviewquery = $db->update('users',$updatedata,"WHERE usernumber='$user'");
            if($cviewquery === false):
                trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
            else:    
                $obj = new Message;
                $obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Call to turn off collection view run for $useremail",$logfile);
            endif;
        elseif ($coll_view == 'TURN ON'):
            $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Call to turn on collection view",$logfile);
            $updatedata = array (
                'collection_view' => 1
            );
            $cviewquery = $db->update('users',$updatedata,"WHERE usernumber='$user'");
            if($cviewquery === false):
                trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
            else:    
                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Call to turn off collection view run for $useremail",$logfile);
            endif;
        endif;
    endif;
endif;
?>